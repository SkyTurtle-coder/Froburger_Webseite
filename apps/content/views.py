from __future__ import annotations

from django.contrib import messages
from django.contrib.auth.mixins import LoginRequiredMixin, PermissionRequiredMixin
from django.contrib.auth.views import redirect_to_login
from django.core.exceptions import PermissionDenied
from django.db.models import Count
from django.shortcuts import get_object_or_404, redirect
from django.urls import reverse, reverse_lazy
from django.utils import timezone
from django.views import View
from django.views.generic import CreateView, ListView, TemplateView, UpdateView

from apps.audit.models import AuditLogEntry
from apps.audit.services import record_audit_event
from apps.media_library.models import MediaAsset

from .forms import (
    CarouselForm,
    CarouselItemFormSet,
    HomepagePageForm,
    HomepageSectionFormSet,
    MediaAssetForm,
    PostBlockFormSet,
    PostForm,
)
from .models import (
    Carousel,
    CarouselRevision,
    Page,
    PageRevision,
    Post,
    PostRevision,
    PublishableStatus,
    Visibility,
)
from .services import (
    build_carousel_preview,
    build_page_preview,
    build_post_preview,
    ensure_homepage_page,
    restore_carousel_revision,
    restore_page_revision,
    restore_post_revision,
    save_carousel_editor,
    save_homepage_editor,
    save_post_editor,
    update_post_status,
)


def _user_can_manage_private_media(user) -> bool:
    return bool(
        user and (user.is_superuser or user.has_perm("media_library.manage_private_mediaasset"))
    )


def _editor_media_queryset(user):
    queryset = MediaAsset.objects.exclude(status=MediaAsset.PublicationStatus.ARCHIVED).order_by(
        "title"
    )
    if _user_can_manage_private_media(user):
        return queryset
    return queryset.exclude(visibility=MediaAsset.Visibility.PRIVATE)


def _media_matches_content_visibility(asset: MediaAsset | None, visibility: str) -> bool:
    if asset is None:
        return True
    if visibility == Visibility.PUBLIC:
        return (
            asset.status == MediaAsset.PublicationStatus.PUBLISHED
            and asset.visibility == MediaAsset.Visibility.PUBLIC
        )
    if visibility == Visibility.MEMBERS:
        return (
            asset.status == MediaAsset.PublicationStatus.PUBLISHED
            and asset.visibility in {MediaAsset.Visibility.PUBLIC, MediaAsset.Visibility.MEMBERS}
        )
    return asset.status != MediaAsset.PublicationStatus.ARCHIVED


def _carousel_matches_content_visibility(carousel: Carousel | None, visibility: str) -> bool:
    if carousel is None:
        return True
    if visibility == Visibility.PUBLIC and carousel.visibility != Visibility.PUBLIC:
        return False
    if visibility == Visibility.MEMBERS and carousel.visibility == Visibility.PRIVATE:
        return False
    for item in carousel.items.select_related("image").filter(is_active=True):
        if not _media_matches_content_visibility(item.image, visibility):
            return False
    return True


def _add_formset_error(formset, message: str):
    errors = formset.non_form_errors()
    errors.append(message)
    formset._non_form_errors = errors


def _validate_unique_positions(formset, *, label: str):
    seen_positions = {}
    for index, form in enumerate(formset.forms, start=1):
        cleaned_data = getattr(form, "cleaned_data", None) or {}
        if not cleaned_data or cleaned_data.get("DELETE"):
            continue
        position = cleaned_data.get("position")
        if position in seen_positions:
            form.add_error(
                "position",
                f"{label} mit derselben Position existiert bereits in "
                f"Zeile {seen_positions[position]}.",
            )
        else:
            seen_positions[position] = index


def _apply_publishable_workflow(instance, workflow_action: str):
    if workflow_action == "publish":
        instance.status = PublishableStatus.PUBLISHED
        instance.published_at = instance.published_at or timezone.now()
        instance.scheduled_for = None
    elif workflow_action == "review":
        instance.status = PublishableStatus.REVIEW
        instance.scheduled_for = None
    elif workflow_action == "schedule":
        instance.status = PublishableStatus.SCHEDULED
    elif workflow_action == "withdraw":
        instance.status = PublishableStatus.DRAFT
        instance.scheduled_for = None
    elif workflow_action == "archive":
        instance.status = PublishableStatus.ARCHIVED
        instance.visibility = Visibility.PRIVATE
        instance.scheduled_for = None


class CmsAccessMixin(LoginRequiredMixin, PermissionRequiredMixin):
    raise_exception = True
    cms_section = "dashboard"
    page_title = "Web-X CMS"

    def handle_no_permission(self):
        if not self.request.user.is_authenticated:
            return redirect_to_login(self.request.get_full_path(), self.get_login_url())
        raise PermissionDenied(self.get_permission_denied_message())

    def get_cms_context(self, **kwargs):
        kwargs.setdefault("cms_section", self.cms_section)
        kwargs.setdefault("page_title", self.page_title)
        kwargs.setdefault("workflow_action", "save")
        return kwargs


class CmsDashboardView(CmsAccessMixin, TemplateView):
    permission_required = "content.view_post"
    template_name = "cms/dashboard.html"
    cms_section = "dashboard"
    page_title = "CMS Dashboard"

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        homepage = Page.objects.filter(page_key="homepage").first()
        context.update(
            self.get_cms_context(
                draft_posts=Post.objects.filter(status=PublishableStatus.DRAFT).count(),
                review_posts=Post.objects.filter(status=PublishableStatus.REVIEW).count(),
                scheduled_posts=Post.objects.filter(status=PublishableStatus.SCHEDULED).count(),
                published_posts=Post.objects.filter(status=PublishableStatus.PUBLISHED).count(),
                pinned_posts=Post.objects.filter(is_homepage_pinned=True).count(),
                media_assets=MediaAsset.objects.count(),
                carousels=Carousel.objects.count(),
                recent_posts=Post.objects.select_related("last_edited_by")
                .order_by("-updated_at", "-created_at")[:5],
                recent_media=MediaAsset.objects.select_related("uploaded_by").order_by(
                    "-updated_at", "-created_at"
                )[:5],
                recent_carousels=Carousel.objects.select_related("last_edited_by").order_by(
                    "-updated_at", "-created_at"
                )[:5],
                homepage=homepage,
            )
        )
        return context


class PostListView(CmsAccessMixin, ListView):
    permission_required = "content.view_post"
    template_name = "cms/post_list.html"
    context_object_name = "posts"
    cms_section = "posts"
    page_title = "Beitraege"

    def get_queryset(self):
        queryset = Post.objects.select_related(
            "hero_image", "layout_preset", "last_edited_by", "author"
        ).order_by("-updated_at", "-created_at")
        status_filter = self.request.GET.get("status")
        if status_filter in {choice[0] for choice in PublishableStatus.choices}:
            queryset = queryset.filter(status=status_filter)
        return queryset

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        context.update(
            self.get_cms_context(
                status_filter=self.request.GET.get("status", ""),
                status_choices=PublishableStatus.choices,
            )
        )
        return context


class PostEditorBaseView(CmsAccessMixin, TemplateView):
    template_name = "cms/post_form.html"
    cms_section = "posts"
    page_title = "Beitrag bearbeiten"
    permission_required = "content.change_post"
    object: Post | None = None

    def get_object(self) -> Post | None:
        return None

    def get_form(self, *, data=None, files=None):
        return PostForm(data=data, files=files, instance=self.object, user=self.request.user)

    def get_formset(self, *, data=None, files=None):
        return PostBlockFormSet(
            data=data,
            files=files,
            instance=self.object,
            prefix="blocks",
            form_kwargs={"user": self.request.user},
        )

    def get_workflow_action(self) -> str:
        return self.request.POST.get("workflow_action", "save")

    def validate_editor_forms(self, form, formset):
        _validate_unique_positions(formset, label="Ein Block")
        visibility = form.instance.visibility
        for field_name in ("hero_image", "og_image"):
            asset = getattr(form.instance, field_name)
            if not _media_matches_content_visibility(asset, visibility):
                form.add_error(
                    field_name,
                    "Oeffentliche Inhalte duerfen nur publizierte Medien "
                    "mit passender Sichtbarkeit nutzen.",
                )
        for block_form in formset.forms:
            cleaned_data = getattr(block_form, "cleaned_data", None) or {}
            if not cleaned_data or cleaned_data.get("DELETE"):
                continue
            asset = cleaned_data.get("image")
            carousel = cleaned_data.get("carousel")
            if not _media_matches_content_visibility(asset, visibility):
                block_form.add_error(
                    "image",
                    "Der gewaehlte Block verweist auf ein Medium mit unpassender Sichtbarkeit.",
                )
            if not _carousel_matches_content_visibility(carousel, visibility):
                block_form.add_error(
                    "carousel",
                    "Das verknuepfte Karussell passt nicht zur Sichtbarkeit dieses Beitrags.",
                )

    def render_editor(self, *, form, formset, status=200):
        return self.render_to_response(
            self.get_cms_context(
                form=form,
                formset=formset,
                post=self.object,
                workflow_action=(
                    self.get_workflow_action()
                    if self.request.method == "POST"
                    else "save"
                ),
            ),
            status=status,
        )

    def get(self, request, *args, **kwargs):
        self.object = self.get_object()
        form = self.get_form()
        formset = self.get_formset()
        return self.render_editor(form=form, formset=formset)

    def post(self, request, *args, **kwargs):
        self.object = self.get_object()
        form = self.get_form(data=request.POST, files=request.FILES)
        formset = self.get_formset(data=request.POST, files=request.FILES)
        if form.is_valid() and formset.is_valid():
            workflow_action = self.get_workflow_action()
            if workflow_action in {"publish", "review", "schedule", "withdraw", "archive"}:
                if not request.user.has_perm("content.publish_post"):
                    raise PermissionDenied
            _apply_publishable_workflow(form.instance, workflow_action)
            self.validate_editor_forms(form, formset)
            if not form.errors and all(not inline_form.errors for inline_form in formset.forms):
                post, revision = save_post_editor(form=form, formset=formset, actor=request.user)
                messages.success(
                    request,
                    f"Beitrag gespeichert. Revision {revision.revision_number} wurde erstellt.",
                )
                return redirect("cms:post_edit", pk=post.pk)
        return self.render_editor(form=form, formset=formset, status=400)


class PostCreateView(PostEditorBaseView):
    permission_required = "content.add_post"
    page_title = "Beitrag erstellen"


class PostUpdateView(PostEditorBaseView):
    permission_required = "content.change_post"

    def get_object(self):
        return get_object_or_404(
            Post.objects.select_related("hero_image", "og_image", "layout_preset"),
            pk=self.kwargs["pk"],
        )


class PostWorkflowView(CmsAccessMixin, View):
    permission_required = "content.publish_post"
    target_status = PublishableStatus.PUBLISHED
    success_message = "Beitrag aktualisiert."

    def post(self, request, *args, **kwargs):
        post = get_object_or_404(Post, pk=kwargs["pk"])
        update_post_status(
            post=post,
            actor=request.user,
            status=self.target_status,
            reason=self.success_message,
        )
        messages.success(request, self.success_message)
        return redirect("cms:post_edit", pk=post.pk)


class PostPublishView(PostWorkflowView):
    target_status = PublishableStatus.PUBLISHED
    success_message = "Beitrag wurde veroeffentlicht."


class PostWithdrawView(PostWorkflowView):
    target_status = PublishableStatus.DRAFT
    success_message = "Beitrag wurde zurueck in den Entwurf gesetzt."


class PostArchiveView(PostWorkflowView):
    target_status = PublishableStatus.ARCHIVED
    success_message = "Beitrag wurde archiviert."


class PostPreviewView(CmsAccessMixin, TemplateView):
    permission_required = "content.preview_post"
    template_name = "public/pages/news_detail.html"
    cms_section = "posts"
    page_title = "Beitragsvorschau"

    def get_context_data(self, **kwargs):
        from apps.core.views import build_news_detail_render_context

        context = super().get_context_data(**kwargs)
        post = get_object_or_404(
            Post.objects.select_related("hero_image", "og_image", "layout_preset").prefetch_related(
                "blocks__image",
                "blocks__carousel__items__image",
            ),
            pk=self.kwargs["pk"],
        )
        context.update(
            build_news_detail_render_context(
                request=self.request,
                post=build_post_preview(post=post),
                preview_mode=True,
                preview_title="Vorschau Beitrag",
            )
        )
        return context


class PostRevisionListView(CmsAccessMixin, TemplateView):
    permission_required = "content.view_postrevision"
    template_name = "cms/revision_list.html"
    cms_section = "posts"
    page_title = "Beitragsrevisionen"

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        post = get_object_or_404(Post, pk=self.kwargs["pk"])
        context.update(
            self.get_cms_context(
                object_label="Beitrag",
                object_name=post.title,
                back_url=reverse("cms:post_edit", kwargs={"pk": post.pk}),
                revisions=post.revisions.select_related("created_by"),
            )
        )
        return context


class PostRevisionPreviewView(CmsAccessMixin, TemplateView):
    permission_required = "content.preview_post"
    template_name = "public/pages/news_detail.html"
    cms_section = "posts"
    page_title = "Revisionsvorschau"

    def get_context_data(self, **kwargs):
        from apps.core.views import build_news_detail_render_context

        context = super().get_context_data(**kwargs)
        post = get_object_or_404(Post, pk=self.kwargs["pk"])
        revision = get_object_or_404(PostRevision, post=post, pk=self.kwargs["revision_id"])
        context.update(
            build_news_detail_render_context(
                request=self.request,
                post=build_post_preview(snapshot=revision.snapshot),
                preview_mode=True,
                preview_title=f"Revision {revision.revision_number}",
            )
        )
        return context


class PostRevisionRestoreView(CmsAccessMixin, View):
    permission_required = "content.restore_post_revision"

    def post(self, request, *args, **kwargs):
        post = get_object_or_404(Post, pk=kwargs["pk"])
        revision = get_object_or_404(PostRevision, post=post, pk=kwargs["revision_id"])
        restored_revision = restore_post_revision(post=post, revision=revision, actor=request.user)
        messages.success(
            request,
            f"Revision {revision.revision_number} wurde wiederhergestellt. "
            f"Neue Revision {restored_revision.revision_number} erstellt.",
        )
        return redirect("cms:post_edit", pk=post.pk)


class MediaAssetListView(CmsAccessMixin, ListView):
    permission_required = "media_library.view_mediaasset"
    template_name = "cms/media_list.html"
    context_object_name = "assets"
    cms_section = "media"
    page_title = "Medien"

    def get_queryset(self):
        return _editor_media_queryset(self.request.user).select_related("uploaded_by")

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        context.update(self.get_cms_context())
        return context


class MediaAssetCreateView(CmsAccessMixin, CreateView):
    permission_required = "media_library.add_mediaasset"
    template_name = "cms/media_form.html"
    form_class = MediaAssetForm
    success_url = reverse_lazy("cms:media_list")
    cms_section = "media"
    page_title = "Medium hochladen"

    def get_form_kwargs(self):
        kwargs = super().get_form_kwargs()
        kwargs["user"] = self.request.user
        return kwargs

    def form_valid(self, form):
        asset = form.save(commit=False)
        asset.uploaded_by = self.request.user
        asset.save()
        self.object = asset
        record_audit_event(
            action="media.asset.created",
            actor=self.request.user,
            object_type="MediaAsset",
            object_id=str(asset.pk),
            result=AuditLogEntry.Result.SUCCESS,
            detail=asset.visibility,
        )
        messages.success(self.request, "Das Medium wurde hochgeladen.")
        return redirect("cms:media_edit", pk=asset.pk)

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        context.update(self.get_cms_context(asset=None))
        return context


class MediaAssetUpdateView(CmsAccessMixin, UpdateView):
    permission_required = "media_library.change_mediaasset"
    template_name = "cms/media_form.html"
    form_class = MediaAssetForm
    success_url = reverse_lazy("cms:media_list")
    cms_section = "media"
    page_title = "Medium bearbeiten"

    def get_queryset(self):
        return _editor_media_queryset(self.request.user)

    def get_form_kwargs(self):
        kwargs = super().get_form_kwargs()
        kwargs["user"] = self.request.user
        return kwargs

    def form_valid(self, form):
        response = super().form_valid(form)
        record_audit_event(
            action="media.asset.updated",
            actor=self.request.user,
            object_type="MediaAsset",
            object_id=str(self.object.pk),
            result=AuditLogEntry.Result.SUCCESS,
            detail=self.object.visibility,
        )
        messages.success(self.request, "Das Medium wurde gespeichert.")
        return response

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        context.update(self.get_cms_context(asset=self.object))
        return context


class CarouselListView(CmsAccessMixin, ListView):
    permission_required = "content.view_carousel"
    template_name = "cms/carousel_list.html"
    context_object_name = "carousels"
    cms_section = "carousels"
    page_title = "Karussells"

    def get_queryset(self):
        return Carousel.objects.select_related("last_edited_by").annotate(item_total=Count("items"))

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        context.update(self.get_cms_context())
        return context


class CarouselEditorBaseView(CmsAccessMixin, TemplateView):
    template_name = "cms/carousel_form.html"
    cms_section = "carousels"
    page_title = "Karussell bearbeiten"
    permission_required = "content.change_carousel"
    object: Carousel | None = None

    def get_object(self) -> Carousel | None:
        return None

    def get_form(self, *, data=None, files=None):
        return CarouselForm(data=data, files=files, instance=self.object)

    def get_formset(self, *, data=None, files=None):
        return CarouselItemFormSet(
            data=data,
            files=files,
            instance=self.object,
            prefix="items",
            form_kwargs={"user": self.request.user},
        )

    def validate_editor_forms(self, form, formset):
        _validate_unique_positions(formset, label="Ein Slide")
        visibility = form.instance.visibility
        for item_form in formset.forms:
            cleaned_data = getattr(item_form, "cleaned_data", None) or {}
            if not cleaned_data or cleaned_data.get("DELETE"):
                continue
            asset = cleaned_data.get("image")
            if not _media_matches_content_visibility(asset, visibility):
                item_form.add_error(
                    "image",
                    "Die Bildsichtbarkeit passt nicht zum Karussell.",
                )

    def render_editor(self, *, form, formset, status=200):
        return self.render_to_response(
            self.get_cms_context(form=form, formset=formset, carousel=self.object),
            status=status,
        )

    def get(self, request, *args, **kwargs):
        self.object = self.get_object()
        return self.render_editor(form=self.get_form(), formset=self.get_formset())

    def post(self, request, *args, **kwargs):
        self.object = self.get_object()
        form = self.get_form(data=request.POST, files=request.FILES)
        formset = self.get_formset(data=request.POST, files=request.FILES)
        if form.is_valid() and formset.is_valid():
            self.validate_editor_forms(form, formset)
            if not form.errors and all(not inline_form.errors for inline_form in formset.forms):
                carousel, revision = save_carousel_editor(
                    form=form,
                    formset=formset,
                    actor=request.user,
                )
                messages.success(
                    request,
                    f"Karussell gespeichert. Revision {revision.revision_number} wurde erstellt.",
                )
                return redirect("cms:carousel_edit", pk=carousel.pk)
        return self.render_editor(form=form, formset=formset, status=400)


class CarouselCreateView(CarouselEditorBaseView):
    permission_required = "content.add_carousel"
    page_title = "Karussell erstellen"


class CarouselUpdateView(CarouselEditorBaseView):
    permission_required = "content.change_carousel"

    def get_object(self):
        return get_object_or_404(Carousel.objects.all(), pk=self.kwargs["pk"])


class CarouselPreviewView(CmsAccessMixin, TemplateView):
    permission_required = "content.preview_carousel"
    template_name = "cms/carousel_preview.html"
    cms_section = "carousels"
    page_title = "Karussellvorschau"

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        carousel = get_object_or_404(
            Carousel.objects.prefetch_related("items__image"),
            pk=self.kwargs["pk"],
        )
        context.update(
            self.get_cms_context(
                carousel=build_carousel_preview(carousel=carousel),
                preview_mode=True,
            )
        )
        return context


class CarouselRevisionListView(CmsAccessMixin, TemplateView):
    permission_required = "content.view_carouselrevision"
    template_name = "cms/revision_list.html"
    cms_section = "carousels"
    page_title = "Karussellrevisionen"

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        carousel = get_object_or_404(Carousel, pk=self.kwargs["pk"])
        context.update(
            self.get_cms_context(
                object_label="Karussell",
                object_name=carousel.name,
                back_url=reverse("cms:carousel_edit", kwargs={"pk": carousel.pk}),
                revisions=carousel.revisions.select_related("created_by"),
            )
        )
        return context


class CarouselRevisionPreviewView(CmsAccessMixin, TemplateView):
    permission_required = "content.preview_carousel"
    template_name = "cms/carousel_preview.html"
    cms_section = "carousels"
    page_title = "Karussell-Revisionsvorschau"

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        carousel = get_object_or_404(Carousel, pk=self.kwargs["pk"])
        revision = get_object_or_404(
            CarouselRevision,
            carousel=carousel,
            pk=self.kwargs["revision_id"],
        )
        context.update(
            self.get_cms_context(
                carousel=build_carousel_preview(snapshot=revision.snapshot),
                preview_mode=True,
                revision=revision,
            )
        )
        return context


class CarouselRevisionRestoreView(CmsAccessMixin, View):
    permission_required = "content.restore_carousel_revision"

    def post(self, request, *args, **kwargs):
        carousel = get_object_or_404(Carousel, pk=kwargs["pk"])
        revision = get_object_or_404(
            CarouselRevision,
            carousel=carousel,
            pk=kwargs["revision_id"],
        )
        restored_revision = restore_carousel_revision(
            carousel=carousel,
            revision=revision,
            actor=request.user,
        )
        messages.success(
            request,
            f"Revision {revision.revision_number} wurde wiederhergestellt. "
            f"Neue Revision {restored_revision.revision_number} erstellt.",
        )
        return redirect("cms:carousel_edit", pk=carousel.pk)


class HomepageEditView(CmsAccessMixin, TemplateView):
    permission_required = "content.change_page"
    template_name = "cms/homepage_form.html"
    cms_section = "homepage"
    page_title = "Startseite bearbeiten"

    def get_object(self):
        return ensure_homepage_page(actor=self.request.user)

    def get_form(self, *, page, data=None):
        return HomepagePageForm(data=data, instance=page, user=self.request.user)

    def get_formset(self, *, page, data=None):
        return HomepageSectionFormSet(
            data=data,
            instance=page,
            prefix="sections",
            form_kwargs={"user": self.request.user},
        )

    def validate_editor_forms(self, form, formset):
        _validate_unique_positions(formset, label="Ein Startseitenblock")
        visibility = form.instance.visibility
        for field_name in ("og_image",):
            asset = getattr(form.instance, field_name, None)
            if not _media_matches_content_visibility(asset, visibility):
                form.add_error(
                    field_name,
                    "Oeffentliche Startseiteninhalte duerfen nur publizierte "
                    "Medien mit passender Sichtbarkeit nutzen.",
                )
        for section_form in formset.forms:
            cleaned_data = getattr(section_form, "cleaned_data", None) or {}
            if not cleaned_data or cleaned_data.get("DELETE"):
                continue
            asset = cleaned_data.get("image")
            carousel = cleaned_data.get("carousel")
            if not _media_matches_content_visibility(asset, visibility):
                section_form.add_error(
                    "image",
                    "Der Block verweist auf ein Medium mit unpassender Sichtbarkeit.",
                )
            if not _carousel_matches_content_visibility(carousel, visibility):
                section_form.add_error(
                    "carousel",
                    "Das verknuepfte Karussell passt nicht zur Sichtbarkeit der Startseite.",
                )

    def render_editor(self, *, page, form, formset, status=200):
        return self.render_to_response(
            self.get_cms_context(
                page=page,
                form=form,
                formset=formset,
                workflow_action=self.request.POST.get("workflow_action", "save")
                if self.request.method == "POST"
                else "save",
            ),
            status=status,
        )

    def get(self, request, *args, **kwargs):
        page = self.get_object()
        return self.render_editor(
            page=page,
            form=self.get_form(page=page),
            formset=self.get_formset(page=page),
        )

    def post(self, request, *args, **kwargs):
        page = self.get_object()
        form = self.get_form(page=page, data=request.POST)
        formset = self.get_formset(page=page, data=request.POST)
        if form.is_valid() and formset.is_valid():
            workflow_action = request.POST.get("workflow_action", "save")
            if workflow_action in {"publish", "review", "schedule", "withdraw", "archive"}:
                if not request.user.has_perm("content.publish_page"):
                    raise PermissionDenied
            _apply_publishable_workflow(form.instance, workflow_action)
            self.validate_editor_forms(form, formset)
            if not form.errors and all(not inline_form.errors for inline_form in formset.forms):
                page, revision = save_homepage_editor(
                    form=form,
                    formset=formset,
                    actor=request.user,
                )
                messages.success(
                    request,
                    f"Startseite gespeichert. Revision {revision.revision_number} wurde erstellt.",
                )
                return redirect("cms:homepage_edit")
        return self.render_editor(page=page, form=form, formset=formset, status=400)


class HomepagePreviewView(CmsAccessMixin, TemplateView):
    permission_required = "content.preview_page"
    template_name = "public/pages/home.html"
    cms_section = "homepage"
    page_title = "Startseitenvorschau"

    def get_context_data(self, **kwargs):
        from apps.core.views import build_homepage_render_context

        context = super().get_context_data(**kwargs)
        page = ensure_homepage_page(actor=self.request.user)
        context.update(
            build_homepage_render_context(
                request=self.request,
                preview_page=build_page_preview(page=page),
                preview_mode=True,
                preview_title="Vorschau Startseite",
            )
        )
        return context


class HomepageRevisionListView(CmsAccessMixin, TemplateView):
    permission_required = "content.view_pagerevision"
    template_name = "cms/revision_list.html"
    cms_section = "homepage"
    page_title = "Startseitenrevisionen"

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        page = ensure_homepage_page(actor=self.request.user)
        context.update(
            self.get_cms_context(
                object_label="Startseite",
                object_name=page.title,
                back_url=reverse("cms:homepage_edit"),
                revisions=page.revisions.select_related("created_by"),
            )
        )
        return context


class HomepageRevisionPreviewView(CmsAccessMixin, TemplateView):
    permission_required = "content.preview_page"
    template_name = "public/pages/home.html"
    cms_section = "homepage"
    page_title = "Startseiten-Revisionsvorschau"

    def get_context_data(self, **kwargs):
        from apps.core.views import build_homepage_render_context

        context = super().get_context_data(**kwargs)
        page = ensure_homepage_page(actor=self.request.user)
        revision = get_object_or_404(PageRevision, page=page, pk=self.kwargs["revision_id"])
        context.update(
            build_homepage_render_context(
                request=self.request,
                preview_page=build_page_preview(snapshot=revision.snapshot),
                preview_mode=True,
                preview_title=f"Revision {revision.revision_number}",
            )
        )
        return context


class HomepageRevisionRestoreView(CmsAccessMixin, View):
    permission_required = "content.restore_page_revision"

    def post(self, request, *args, **kwargs):
        page = ensure_homepage_page(actor=request.user)
        revision = get_object_or_404(PageRevision, page=page, pk=kwargs["revision_id"])
        restored_revision = restore_page_revision(page=page, revision=revision, actor=request.user)
        messages.success(
            request,
            f"Revision {revision.revision_number} wurde wiederhergestellt. "
            f"Neue Revision {restored_revision.revision_number} erstellt.",
        )
        return redirect("cms:homepage_edit")
