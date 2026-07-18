from __future__ import annotations

from django.contrib import messages
from django.contrib.auth.mixins import LoginRequiredMixin, PermissionRequiredMixin
from django.contrib.auth.views import redirect_to_login
from django.core.exceptions import PermissionDenied
from django.core.paginator import Paginator
from django.http import Http404
from django.shortcuts import get_object_or_404, redirect
from django.urls import reverse
from django.utils import timezone
from django.views import View
from django.views.generic import ListView, TemplateView

from apps.content.permissions import can_access_cms

from .forms import EventForm
from .models import Event, EventCategory, EventRevision
from .services import (
    build_calendar_feed,
    build_event_preview,
    build_single_event_ics,
    event_ics_response,
    restore_event_revision,
    save_event_editor,
    update_event_status,
)


def _apply_event_workflow(instance, workflow_action: str):
    if workflow_action == "publish":
        instance.status = Event.Status.PUBLISHED
        instance.published_at = instance.published_at or timezone.now()
        instance.scheduled_for = None
    elif workflow_action == "review":
        instance.status = Event.Status.REVIEW
        instance.scheduled_for = None
    elif workflow_action == "schedule":
        instance.status = Event.Status.SCHEDULED
    elif workflow_action == "withdraw":
        instance.status = Event.Status.DRAFT
        instance.scheduled_for = None
    elif workflow_action == "cancel":
        instance.status = Event.Status.CANCELLED
        instance.published_at = instance.published_at or timezone.now()
        instance.scheduled_for = None
    elif workflow_action == "archive":
        instance.status = Event.Status.ARCHIVED
        instance.scheduled_for = None


class EventCmsAccessMixin(LoginRequiredMixin, PermissionRequiredMixin):
    raise_exception = True
    cms_section = "events"
    page_title = "Veranstaltungen"

    def has_permission(self):
        return can_access_cms(self.request.user) and super().has_permission()

    def handle_no_permission(self):
        if not self.request.user.is_authenticated:
            return redirect_to_login(self.request.get_full_path(), self.get_login_url())
        if not self.request.user.is_active:
            raise Http404
        raise PermissionDenied

    def get_cms_context(self, **kwargs):
        kwargs.setdefault("cms_section", self.cms_section)
        kwargs.setdefault("page_title", self.page_title)
        kwargs.setdefault("workflow_action", "save")
        return kwargs


class CmsEventListView(EventCmsAccessMixin, ListView):
    permission_required = "events.view_event"
    template_name = "cms/event_list.html"
    context_object_name = "events"
    page_title = "Veranstaltungen"

    def get_queryset(self):
        queryset = Event.objects.select_related(
            "category",
            "hero_image",
            "last_edited_by",
        ).prefetch_related("allowed_groups", "allowed_users")
        status_filter = self.request.GET.get("status", "")
        category_filter = self.request.GET.get("category", "")
        if status_filter in {choice[0] for choice in Event.Status.choices}:
            queryset = queryset.filter(status=status_filter)
        if category_filter:
            queryset = queryset.filter(category__slug=category_filter)
        return queryset.order_by("start_at", "title", "pk")

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        context.update(
            self.get_cms_context(
                status_filter=self.request.GET.get("status", ""),
                category_filter=self.request.GET.get("category", ""),
                status_choices=Event.Status.choices,
                categories=EventCategory.objects.filter(is_active=True).order_by("name"),
            )
        )
        return context


class EventEditorBaseView(EventCmsAccessMixin, TemplateView):
    template_name = "cms/event_form.html"
    permission_required = "events.change_event"
    object: Event | None = None
    page_title = "Veranstaltung bearbeiten"

    def get_object(self):
        return None

    def get_form(self, *, data=None, files=None):
        return EventForm(data=data, files=files, instance=self.object, user=self.request.user)

    def get_workflow_action(self) -> str:
        return self.request.POST.get("workflow_action", "save")

    def render_editor(self, *, form, status=200):
        return self.render_to_response(
            self.get_cms_context(
                event=self.object,
                form=form,
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
        return self.render_editor(form=self.get_form())

    def post(self, request, *args, **kwargs):
        self.object = self.get_object()
        form = self.get_form(data=request.POST, files=request.FILES)
        if form.is_valid():
            workflow_action = self.get_workflow_action()
            if workflow_action == "schedule" and not request.user.has_perm("events.schedule_event"):
                raise Http404
            if workflow_action in {"publish", "withdraw", "review"} and not request.user.has_perm(
                "events.publish_event"
            ):
                raise Http404
            if workflow_action == "cancel" and not request.user.has_perm("events.cancel_event"):
                raise Http404
            if workflow_action == "archive" and not request.user.has_perm("events.archive_event"):
                raise Http404
            _apply_event_workflow(form.instance, workflow_action)
            event, revision = save_event_editor(form=form, actor=request.user)
            messages.success(
                request,
                f"Veranstaltung gespeichert. Revision {revision.revision_number} wurde erstellt.",
            )
            return redirect("cms:event_edit", pk=event.pk)
        return self.render_editor(form=form, status=400)


class CmsEventCreateView(EventEditorBaseView):
    permission_required = "events.add_event"
    page_title = "Veranstaltung erstellen"


class CmsEventUpdateView(EventEditorBaseView):
    permission_required = "events.change_event"

    def get_object(self):
        return get_object_or_404(
            Event.objects.select_related("category", "hero_image"),
            pk=self.kwargs["pk"],
        )


class EventWorkflowView(EventCmsAccessMixin, View):
    permission_required = "events.publish_event"
    target_status = Event.Status.PUBLISHED
    success_message = "Veranstaltung aktualisiert."

    def post(self, request, *args, **kwargs):
        event = get_object_or_404(Event, pk=kwargs["pk"])
        update_event_status(
            event=event,
            actor=request.user,
            status=self.target_status,
            reason=self.success_message,
        )
        messages.success(request, self.success_message)
        return redirect("cms:event_edit", pk=event.pk)


class CmsEventPublishView(EventWorkflowView):
    permission_required = "events.publish_event"
    target_status = Event.Status.PUBLISHED
    success_message = "Veranstaltung wurde veroeffentlicht."


class CmsEventWithdrawView(EventWorkflowView):
    permission_required = "events.publish_event"
    target_status = Event.Status.DRAFT
    success_message = "Veranstaltung wurde zurueck in den Entwurf gesetzt."


class CmsEventCancelView(EventWorkflowView):
    permission_required = "events.cancel_event"
    target_status = Event.Status.CANCELLED
    success_message = "Veranstaltung wurde als abgesagt markiert."


class CmsEventArchiveView(EventWorkflowView):
    permission_required = "events.archive_event"
    target_status = Event.Status.ARCHIVED
    success_message = "Veranstaltung wurde archiviert."


class CmsEventPreviewView(EventCmsAccessMixin, TemplateView):
    permission_required = "events.preview_event"
    template_name = "public/pages/event_detail.html"
    page_title = "Veranstaltungsvorschau"

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        event = get_object_or_404(
            Event.objects.select_related("category", "hero_image"),
            pk=self.kwargs["pk"],
        )
        context.update(
            build_event_detail_context(
                self.request,
                build_event_preview(event=event),
                preview_mode=True,
                preview_title="Vorschau Veranstaltung",
            )
        )
        return context


class CmsEventRevisionListView(EventCmsAccessMixin, TemplateView):
    permission_required = "events.view_eventrevision"
    template_name = "cms/revision_list.html"
    page_title = "Veranstaltungsrevisionen"

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        event = get_object_or_404(Event, pk=self.kwargs["pk"])
        context.update(
            self.get_cms_context(
                object_label="Veranstaltung",
                object_name=event.title,
                back_url=reverse("cms:event_edit", kwargs={"pk": event.pk}),
                revisions=event.revisions.select_related("created_by"),
            )
        )
        return context


class CmsEventRevisionPreviewView(EventCmsAccessMixin, TemplateView):
    permission_required = "events.preview_event"
    template_name = "public/pages/event_detail.html"
    page_title = "Revisionsvorschau Veranstaltung"

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        event = get_object_or_404(Event, pk=self.kwargs["pk"])
        revision = get_object_or_404(EventRevision, event=event, pk=self.kwargs["revision_id"])
        context.update(
            build_event_detail_context(
                self.request,
                build_event_preview(snapshot=revision.snapshot),
                preview_mode=True,
                preview_title=f"Revision {revision.revision_number}",
            )
        )
        return context


class CmsEventRevisionRestoreView(EventCmsAccessMixin, View):
    permission_required = "events.restore_event_revision"

    def post(self, request, *args, **kwargs):
        event = get_object_or_404(Event, pk=kwargs["pk"])
        revision = get_object_or_404(EventRevision, event=event, pk=kwargs["revision_id"])
        restored = restore_event_revision(event=event, revision=revision, actor=request.user)
        messages.success(
            request,
            f"Revision {revision.revision_number} wurde wiederhergestellt. "
            f"Neue Revision {restored.revision_number} erstellt.",
        )
        return redirect("cms:event_edit", pk=event.pk)


def _events_page_meta(request):
    return {
        "canonical_url": request.build_absolute_uri(request.path),
        "meta_description": (
            "Semesterprogramm der AV Froburger sowie regelmaessige "
            "Treffen und interne Anlaesse."
        ),
        "meta_title": "Anlaesse - AV Froburger",
        "noindex": False,
        "og_image_alt": "Semesterprogramm der AV Froburger",
        "og_image_url": "",
        "page_slug": "events",
        "show_lock_link": True,
    }


def build_event_detail_context(request, event, *, preview_mode=False, preview_title=""):
    is_internal_view = request.resolver_match and request.resolver_match.namespace == "members"
    detail_url_name = "members:event_detail" if is_internal_view else "core:event_detail"
    ics_url_name = "members:event_ics" if is_internal_view else "core:event_ics"
    return {
        "canonical_url": request.build_absolute_uri(request.path)
        if preview_mode
        else request.build_absolute_uri(reverse(detail_url_name, kwargs={"slug": event.slug})),
        "event": event,
        "event_ics_url": reverse(ics_url_name, kwargs={"slug": event.slug}),
        "meta_description": event.short_description,
        "meta_title": f"{event.title} - AV Froburger",
        "noindex": preview_mode,
        "page_slug": "events",
        "preview_mode": preview_mode,
        "preview_title": preview_title,
        "show_lock_link": False if preview_mode else not is_internal_view,
    }


class PublicEventListView(TemplateView):
    template_name = "public/pages/events.html"

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        category_slug = self.request.GET.get("category", "")
        upcoming_queryset = Event.objects.public().select_related("category", "hero_image")
        past_queryset = Event.objects.public().select_related("category", "hero_image")
        if category_slug:
            upcoming_queryset = upcoming_queryset.filter(category__slug=category_slug)
            past_queryset = past_queryset.filter(category__slug=category_slug)
        upcoming_page = Paginator(
            upcoming_queryset.filter(end_at__gte=timezone.now()).order_by(
                "start_at",
                "title",
                "pk",
            ),
            8,
        ).get_page(self.request.GET.get("upcoming_page"))
        past_page = Paginator(
            past_queryset.filter(end_at__lt=timezone.now()).order_by("-start_at", "title", "pk"),
            8,
        ).get_page(self.request.GET.get("past_page"))
        categories = EventCategory.objects.filter(
            is_active=True,
            events__visibility=Event.Visibility.PUBLIC,
        ).distinct().order_by("name")
        context.update(
            _events_page_meta(self.request)
            | {
                "calendar_webcal_url": f"webcal://{self.request.get_host()}{reverse('core:calendar_ics')}",
                "categories": categories,
                "category_filter": category_slug,
                "upcoming_events": upcoming_page,
                "past_events": past_page,
            }
        )
        return context


class PublicEventDetailView(TemplateView):
    template_name = "public/pages/event_detail.html"

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        event = get_object_or_404(
            Event.objects.public().select_related("category", "hero_image"),
            slug=self.kwargs["slug"],
        )
        context.update(build_event_detail_context(self.request, event))
        return context


class PublicEventIcsView(View):
    def get(self, request, *args, **kwargs):
        event = get_object_or_404(
            Event.objects.public(),
            slug=kwargs["slug"],
        )
        return event_ics_response(
            build_single_event_ics(event),
            filename=f"{event.slug or 'anlass'}.ics",
        )


class PublicEventFeedView(View):
    def get(self, request, *args, **kwargs):
        events = Event.objects.public().order_by("start_at", "title", "pk")
        return event_ics_response(
            build_calendar_feed(events, title="AV Froburger Anlasskalender"),
            filename="av-froburger-anlaesse.ics",
        )


class MemberEventListView(LoginRequiredMixin, TemplateView):
    template_name = "members/events.html"

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        category_slug = self.request.GET.get("category", "")
        visible_queryset = Event.objects.visible_to(self.request.user).select_related(
            "category",
            "hero_image",
        )
        if category_slug:
            visible_queryset = visible_queryset.filter(category__slug=category_slug)
        context.update(
            page_title="Veranstaltungen | AV Froburger",
            heading="Veranstaltungen",
            categories=EventCategory.objects.filter(is_active=True).order_by("name"),
            category_filter=category_slug,
            upcoming_events=visible_queryset.filter(end_at__gte=timezone.now()).order_by(
                "start_at",
                "title",
                "pk",
            )[:20],
            past_events=visible_queryset.filter(end_at__lt=timezone.now()).order_by(
                "-start_at",
                "title",
                "pk",
            )[:12],
            internal_feed_url=reverse("members:calendar_ics"),
        )
        return context


class MemberEventDetailView(LoginRequiredMixin, TemplateView):
    template_name = "members/event_detail.html"

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        event = get_object_or_404(
            Event.objects.visible_to(self.request.user).select_related(
                "category",
                "hero_image",
            ),
            slug=self.kwargs["slug"],
        )
        context.update(
            event=event,
            event_ics_url=reverse("members:event_ics", kwargs={"slug": event.slug}),
        )
        return context


class MemberEventIcsView(LoginRequiredMixin, View):
    def get(self, request, *args, **kwargs):
        event = get_object_or_404(
            Event.objects.visible_to(request.user),
            slug=kwargs["slug"],
        )
        return event_ics_response(
            build_single_event_ics(event),
            filename=f"{event.slug or 'anlass'}.ics",
        )


class MemberEventFeedView(LoginRequiredMixin, View):
    def get(self, request, *args, **kwargs):
        events = Event.objects.visible_to(request.user).order_by("start_at", "title", "pk")
        return event_ics_response(
            build_calendar_feed(events, title="AV Froburger interner Kalender"),
            filename="av-froburger-intern-anlaesse.ics",
        )
