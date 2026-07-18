from __future__ import annotations

from django.contrib import messages
from django.contrib.auth.mixins import (
    LoginRequiredMixin,
    UserPassesTestMixin,
)
from django.core.exceptions import PermissionDenied
from django.db.models import Q
from django.shortcuts import get_object_or_404, redirect
from django.urls import reverse
from django.views import View
from django.views.generic import ListView, TemplateView

from apps.content.views import CmsAccessMixin

from .forms import DocumentForm
from .models import Document, DocumentCategory, DocumentVersion
from .services import (
    activate_document_version,
    archive_document,
    document_download_response,
    save_document_editor,
    user_can_manage_publication_documents,
    user_can_manage_sensitive_documents,
    user_can_view_sensitive_document_area,
)


class DocumentCmsAccessMixin(CmsAccessMixin):
    cms_section = "documents"
    page_title = "Dokumente"


class CmsDocumentListView(DocumentCmsAccessMixin, ListView):
    permission_required = "documents.view_document"
    template_name = "cms/document_list.html"
    context_object_name = "documents"
    paginate_by = 20

    def get_queryset(self):
        queryset = (
            Document.objects.editable_by(self.request.user)
            .select_related("category", "current_version", "last_edited_by")
            .prefetch_related("allowed_groups", "allowed_users")
        )
        query = self.request.GET.get("q", "").strip()
        status_filter = self.request.GET.get("status", "")
        visibility_filter = self.request.GET.get("visibility", "")
        category_filter = self.request.GET.get("category", "")
        if query:
            queryset = queryset.filter(
                Q(title__icontains=query)
                | Q(description__icontains=query)
                | Q(current_version__original_filename__icontains=query)
            )
        if status_filter in {choice[0] for choice in Document.Status.choices}:
            queryset = queryset.filter(status=status_filter)
        if visibility_filter in {choice[0] for choice in Document.Visibility.choices}:
            queryset = queryset.filter(visibility=visibility_filter)
        if category_filter == "none":
            queryset = queryset.filter(category__isnull=True)
        elif category_filter:
            queryset = queryset.filter(category__slug=category_filter)
        return queryset.order_by("title", "pk")

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        context.update(
            self.get_cms_context(
                categories=DocumentCategory.objects.filter(is_active=True).order_by("name"),
                category_filter=self.request.GET.get("category", ""),
                query=self.request.GET.get("q", "").strip(),
                status_choices=Document.Status.choices,
                status_filter=self.request.GET.get("status", ""),
                visibility_choices=Document.Visibility.choices,
                visibility_filter=self.request.GET.get("visibility", ""),
                page_query=self.request.GET.urlencode(),
            )
        )
        return context


class DocumentEditorBaseView(DocumentCmsAccessMixin, TemplateView):
    template_name = "cms/document_form.html"
    permission_required = "documents.change_document"
    object: Document | None = None

    def get_object(self):
        return None

    def get_form(self, *, data=None, files=None):
        return DocumentForm(data=data, files=files, instance=self.object, user=self.request.user)

    def get_workflow_action(self) -> str:
        return self.request.POST.get("workflow_action", "save")

    def render_editor(self, *, form, status=200):
        return self.render_to_response(
            self.get_cms_context(
                document=self.object,
                form=form,
                workflow_action=(
                    self.get_workflow_action() if self.request.method == "POST" else "save"
                ),
            ),
            status=status,
        )

    def get(self, request, *args, **kwargs):
        self.object = self.get_object()
        return self.render_editor(form=self.get_form())

    def post(self, request, *args, **kwargs):
        self.object = self.get_object()
        form = self.get_form(data=request.POST, files=request.FILES)
        if form.is_valid():
            workflow_action = self.get_workflow_action()
            if (
                workflow_action in {"publish", "archive"}
                and not user_can_manage_publication_documents(request.user)
            ):
                raise PermissionDenied
            document = save_document_editor(
                form=form,
                actor=request.user,
                publish=workflow_action == "publish",
                archive=workflow_action == "archive",
            )
            message = "Dokument gespeichert."
            if form.cleaned_data.get("file_upload"):
                message = "Dokument gespeichert und neue Version hochgeladen."
            if workflow_action == "publish":
                message = "Dokument gespeichert und veroeffentlicht."
            elif workflow_action == "archive":
                message = "Dokument wurde archiviert."
            messages.success(request, message)
            return redirect("cms:document_edit", pk=document.pk)
        return self.render_editor(form=form, status=400)


class CmsDocumentCreateView(DocumentEditorBaseView):
    permission_required = "documents.add_document"
    page_title = "Dokument erstellen"


class CmsDocumentUpdateView(DocumentEditorBaseView):
    permission_required = "documents.change_document"
    page_title = "Dokument bearbeiten"

    def get_object(self):
        return get_object_or_404(
            Document.objects.editable_by(self.request.user).select_related(
                "category",
                "current_version",
            ),
            pk=self.kwargs["pk"],
        )


class CmsDocumentArchiveView(DocumentCmsAccessMixin, View):
    permission_required = "documents.change_document"

    def post(self, request, *args, **kwargs):
        if not user_can_manage_publication_documents(request.user):
            raise PermissionDenied
        document = get_object_or_404(Document.objects.editable_by(request.user), pk=kwargs["pk"])
        document = archive_document(document=document, actor=request.user)
        messages.success(request, "Dokument wurde archiviert.")
        return redirect("cms:document_edit", pk=document.pk)


class CmsDocumentVersionListView(DocumentCmsAccessMixin, TemplateView):
    permission_required = "documents.view_documentversion"
    template_name = "cms/document_versions.html"
    page_title = "Dokumentversionen"

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        document = get_object_or_404(
            Document.objects.editable_by(self.request.user).select_related("current_version"),
            pk=self.kwargs["pk"],
        )
        context.update(
            self.get_cms_context(
                document=document,
                versions=document.versions.select_related("uploaded_by"),
                can_activate_versions=user_can_manage_publication_documents(self.request.user),
                can_manage_sensitive=user_can_manage_sensitive_documents(self.request.user),
            )
        )
        return context


class CmsDocumentVersionActivateView(DocumentCmsAccessMixin, View):
    permission_required = "documents.change_document"

    def post(self, request, *args, **kwargs):
        if not user_can_manage_publication_documents(request.user):
            raise PermissionDenied
        document = get_object_or_404(Document.objects.editable_by(request.user), pk=kwargs["pk"])
        version = get_object_or_404(DocumentVersion, document=document, pk=kwargs["version_id"])
        activate_document_version(document=document, version=version, actor=request.user)
        messages.success(
            request,
            f"Version {version.version_number} wurde als aktuelle Dokumentversion aktiviert.",
        )
        return redirect("cms:document_versions", pk=document.pk)


class MemberDocumentListView(LoginRequiredMixin, ListView):
    template_name = "members/documents.html"
    context_object_name = "documents"
    paginate_by = 15
    page_title = "Dokumente | AV Froburger"
    heading = "Dokumente"
    intro = "Protokolle, Reglemente und interne Unterlagen bleiben hier geschuetzt abrufbar."
    page_tag = "Unterlagen"
    sensitive_only = False

    def get_queryset(self):
        queryset = (
            Document.objects.visible_to(self.request.user)
            .select_related("category", "current_version")
            .prefetch_related("allowed_groups")
        )
        if self.sensitive_only:
            queryset = queryset.filter(visibility=Document.Visibility.HIGHLY_SENSITIVE)
        else:
            queryset = queryset.exclude(visibility=Document.Visibility.HIGHLY_SENSITIVE)
        query = self.request.GET.get("q", "").strip()
        category_filter = self.request.GET.get("category", "")
        filetype_filter = self.request.GET.get("type", "")
        if query:
            queryset = queryset.filter(
                Q(title__icontains=query)
                | Q(description__icontains=query)
                | Q(current_version__original_filename__icontains=query)
            )
        if category_filter == "none":
            queryset = queryset.filter(category__isnull=True)
        elif category_filter:
            queryset = queryset.filter(category__slug=category_filter)
        if filetype_filter:
            queryset = queryset.filter(
                current_version__original_filename__iendswith=f".{filetype_filter.lstrip('.')}"
            )
        return queryset.order_by("title", "pk")

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        visible_documents = Document.objects.visible_to(self.request.user)
        if self.sensitive_only:
            visible_documents = visible_documents.filter(
                visibility=Document.Visibility.HIGHLY_SENSITIVE
            )
        else:
            visible_documents = visible_documents.exclude(
                visibility=Document.Visibility.HIGHLY_SENSITIVE
            )
        context.update(
            page_title=self.page_title,
            heading=self.heading,
            document_area_tag=self.page_tag,
            intro=self.intro,
            categories=DocumentCategory.objects.filter(
                is_active=True,
                documents__in=visible_documents,
            ).distinct().order_by("name"),
            category_filter=self.request.GET.get("category", ""),
            filetype_filter=self.request.GET.get("type", ""),
            query=self.request.GET.get("q", "").strip(),
            page_query=self.request.GET.urlencode(),
            can_view_sensitive_documents=user_can_view_sensitive_document_area(self.request.user),
            sensitive_area_url=reverse("members:documents_sensitive"),
            is_sensitive_area=self.sensitive_only,
            available_filetypes=sorted(
                {
                    version.original_filename.rsplit(".", 1)[-1].lower()
                    for version in DocumentVersion.objects.filter(document__in=visible_documents)
                    if "." in version.original_filename
                }
            ),
        )
        return context


class SensitiveDocumentListView(UserPassesTestMixin, MemberDocumentListView):
    raise_exception = True
    page_title = "Sensible Dokumente | AV Froburger"
    heading = "Sensible Dokumente"
    intro = "Besonders geschuetzte Unterlagen bleiben serverseitig und pro Benutzer gefiltert."
    page_tag = "Vertraulich"
    sensitive_only = True

    def test_func(self):
        return user_can_view_sensitive_document_area(self.request.user)


class DocumentDownloadView(LoginRequiredMixin, View):
    def get(self, request, *args, **kwargs):
        document = get_object_or_404(
            Document.objects.select_related("current_version"),
            pk=kwargs["pk"],
        )
        return document_download_response(user=request.user, document=document)
