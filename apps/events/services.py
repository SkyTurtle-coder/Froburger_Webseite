from __future__ import annotations

from zoneinfo import ZoneInfo

from django.db import transaction
from django.http import HttpResponse
from django.utils import timezone
from django.utils.dateparse import parse_datetime

from apps.audit.models import AuditLogEntry
from apps.audit.services import record_audit_event

from .models import Event, EventRevision


def _parse_snapshot_datetime(value: str):
    if not value:
        return None
    parsed = parse_datetime(value)
    if parsed is None:
        return None
    if timezone.is_naive(parsed):
        return timezone.make_aware(parsed, timezone.get_current_timezone())
    return parsed


@transaction.atomic
def save_event_editor(*, form, actor):
    event = form.save(commit=False)
    is_new = event.pk is None
    event.last_edited_by = actor
    if is_new:
        event.created_by = actor
    event.version_number = (event.version_number or 1) + (0 if is_new else 1)
    event.full_clean()
    event.save()
    form.save_m2m()
    if event.visibility != Event.Visibility.SELECTED_GROUPS:
        event.allowed_groups.clear()
    if event.visibility != Event.Visibility.SELECTED_USERS:
        event.allowed_users.clear()
    revision = event.create_revision(actor=actor, reason=form.cleaned_data.get("change_reason", ""))
    record_audit_event(
        action="events.event.created" if is_new else "events.event.updated",
        actor=actor,
        object_type="Event",
        object_id=str(event.pk),
        result=AuditLogEntry.Result.SUCCESS,
        detail=f"status={event.status}"[:255],
    )
    return event, revision


@transaction.atomic
def update_event_status(*, event: Event, actor, status: str, reason: str = ""):
    event.status = status
    if status in {Event.Status.PUBLISHED, Event.Status.CANCELLED, Event.Status.COMPLETED}:
        event.published_at = event.published_at or timezone.now()
    if status != Event.Status.SCHEDULED:
        event.scheduled_for = None
    if status == Event.Status.ARCHIVED:
        event.archived_at = event.archived_at or timezone.now()
    else:
        event.archived_at = None
    event.last_edited_by = actor
    event.version_number += 1
    event.full_clean()
    event.save()
    revision = event.create_revision(actor=actor, reason=reason)
    audit_action_map = {
        Event.Status.PUBLISHED: "events.event.published",
        Event.Status.DRAFT: "events.event.withdrawn",
        Event.Status.SCHEDULED: "events.event.scheduled",
        Event.Status.CANCELLED: "events.event.cancelled",
        Event.Status.COMPLETED: "events.event.completed",
        Event.Status.ARCHIVED: "events.event.archived",
        Event.Status.REVIEW: "events.event.review",
    }
    record_audit_event(
        action=audit_action_map[status],
        actor=actor,
        object_type="Event",
        object_id=str(event.pk),
        result=AuditLogEntry.Result.SUCCESS,
        detail=f"status={status}"[:255],
    )
    return revision


def build_event_preview(event: Event | None = None, snapshot: dict | None = None) -> Event:
    if snapshot is None and event is None:
        raise ValueError("build_event_preview braucht entweder ein Event oder einen Snapshot.")
    if snapshot is None:
        return event

    preview_event = Event(
        title=snapshot["title"],
        slug=snapshot["slug"],
        short_description=snapshot["short_description"],
        description=snapshot["description"],
        start_at=_parse_snapshot_datetime(snapshot["start_at"]),
        end_at=_parse_snapshot_datetime(snapshot["end_at"]),
        timezone_name=snapshot["timezone_name"],
        location_name=snapshot["location_name"],
        location_address=snapshot["location_address"],
        map_url=snapshot["map_url"],
        visibility=snapshot["visibility"],
        status=snapshot["status"],
        published_at=_parse_snapshot_datetime(snapshot.get("published_at", "")),
        scheduled_for=_parse_snapshot_datetime(snapshot.get("scheduled_for", "")),
        signup_url=snapshot["signup_url"],
        signup_deadline=_parse_snapshot_datetime(snapshot.get("signup_deadline", "")),
        max_participants=snapshot["max_participants"],
        archived_at=_parse_snapshot_datetime(snapshot.get("archived_at", "")),
        version_number=snapshot["version_number"],
    )
    preview_event.hero_image_id = snapshot["hero_image_id"]
    preview_event.category_id = snapshot["category_id"]
    preview_event.preview_allowed_group_ids = snapshot.get("allowed_group_ids", [])
    preview_event.preview_allowed_user_ids = snapshot.get("allowed_user_ids", [])
    if preview_event.hero_image_id:
        from apps.media_library.models import MediaAsset

        preview_event.hero_image = MediaAsset.objects.filter(pk=preview_event.hero_image_id).first()
    if preview_event.category_id:
        from .models import EventCategory

        preview_event.category = EventCategory.objects.filter(pk=preview_event.category_id).first()
    return preview_event


@transaction.atomic
def restore_event_revision(*, event: Event, revision: EventRevision, actor):
    event.create_revision(actor=actor, reason="Sicherung vor Wiederherstellung")
    _apply_event_snapshot(event, revision.snapshot, actor=actor)
    restored = event.create_revision(
        actor=actor,
        reason=f"Wiederhergestellt aus Revision {revision.revision_number}",
    )
    record_audit_event(
        action="events.event.restored",
        actor=actor,
        object_type="Event",
        object_id=str(event.pk),
        result=AuditLogEntry.Result.SUCCESS,
        detail=f"revision={revision.revision_number}"[:255],
    )
    return restored


def _apply_event_snapshot(event: Event, snapshot: dict, actor=None):
    event.title = snapshot["title"]
    event.slug = snapshot["slug"]
    event.short_description = snapshot["short_description"]
    event.description = snapshot["description"]
    event.start_at = _parse_snapshot_datetime(snapshot["start_at"])
    event.end_at = _parse_snapshot_datetime(snapshot["end_at"])
    event.timezone_name = snapshot["timezone_name"]
    event.location_name = snapshot["location_name"]
    event.location_address = snapshot["location_address"]
    event.map_url = snapshot["map_url"]
    event.hero_image_id = snapshot["hero_image_id"]
    event.category_id = snapshot["category_id"]
    event.visibility = snapshot["visibility"]
    event.status = snapshot["status"]
    event.published_at = _parse_snapshot_datetime(snapshot.get("published_at", ""))
    event.scheduled_for = _parse_snapshot_datetime(snapshot.get("scheduled_for", ""))
    event.signup_url = snapshot["signup_url"]
    event.signup_deadline = _parse_snapshot_datetime(snapshot.get("signup_deadline", ""))
    event.max_participants = snapshot["max_participants"]
    event.archived_at = _parse_snapshot_datetime(snapshot.get("archived_at", ""))
    event.last_edited_by = actor
    event.version_number += 1
    event.full_clean()
    event.save()
    event.allowed_groups.set(snapshot.get("allowed_group_ids", []))
    event.allowed_users.set(snapshot.get("allowed_user_ids", []))


def _escape_ics(value: str) -> str:
    return (
        (value or "")
        .replace("\\", "\\\\")
        .replace(";", r"\;")
        .replace(",", r"\,")
        .replace("\r\n", r"\n")
        .replace("\n", r"\n")
    )


def _format_ics_datetime(value, timezone_name: str) -> str:
    zone = ZoneInfo(timezone_name or "Europe/Zurich")
    localized = timezone.localtime(value, zone)
    return localized.strftime("%Y%m%dT%H%M%S")


def build_event_uid(event: Event) -> str:
    if event.pk:
        return f"event-{event.pk}@avfroburger.ch"
    return f"event-preview-{event.slug}@avfroburger.ch"


def build_single_event_ics(event: Event) -> str:
    lines = [
        "BEGIN:VCALENDAR",
        "VERSION:2.0",
        "PRODID:-//AV Froburger//Anlasskalender//DE",
        "CALSCALE:GREGORIAN",
        "METHOD:PUBLISH",
        f"X-WR-CALNAME:{_escape_ics(event.title)}",
        f"X-WR-TIMEZONE:{event.timezone_name}",
        "BEGIN:VEVENT",
        f"UID:{build_event_uid(event)}",
        f"DTSTAMP:{timezone.now().strftime('%Y%m%dT%H%M%SZ')}",
        (
            f"DTSTART;TZID={event.timezone_name}:"
            f"{_format_ics_datetime(event.start_at, event.timezone_name)}"
        ),
        (
            f"DTEND;TZID={event.timezone_name}:"
            f"{_format_ics_datetime(event.end_at, event.timezone_name)}"
        ),
        f"SUMMARY:{_escape_ics(event.title)}",
        f"DESCRIPTION:{_escape_ics(event.description)}",
        f"LOCATION:{_escape_ics(event.location_name)}",
    ]
    if event.status == Event.Status.CANCELLED:
        lines.append("STATUS:CANCELLED")
    else:
        lines.append("STATUS:CONFIRMED")
    lines.extend(
        [
            "END:VEVENT",
            "END:VCALENDAR",
        ]
    )
    return "\r\n".join(lines)


def build_calendar_feed(events, *, title: str) -> str:
    lines = [
        "BEGIN:VCALENDAR",
        "VERSION:2.0",
        "PRODID:-//AV Froburger//Anlasskalender//DE",
        "CALSCALE:GREGORIAN",
        "METHOD:PUBLISH",
        f"X-WR-CALNAME:{_escape_ics(title)}",
        "X-WR-TIMEZONE:Europe/Zurich",
    ]
    for event in events:
        lines.extend(
            [
                "BEGIN:VEVENT",
                f"UID:{build_event_uid(event)}",
                f"DTSTAMP:{timezone.now().strftime('%Y%m%dT%H%M%SZ')}",
                (
                    f"DTSTART;TZID={event.timezone_name}:"
                    f"{_format_ics_datetime(event.start_at, event.timezone_name)}"
                ),
                (
                    f"DTEND;TZID={event.timezone_name}:"
                    f"{_format_ics_datetime(event.end_at, event.timezone_name)}"
                ),
                f"SUMMARY:{_escape_ics(event.title)}",
                f"DESCRIPTION:{_escape_ics(event.description)}",
                f"LOCATION:{_escape_ics(event.location_name)}",
                (
                    "STATUS:CANCELLED"
                    if event.status == Event.Status.CANCELLED
                    else "STATUS:CONFIRMED"
                ),
                "END:VEVENT",
            ]
        )
    lines.append("END:VCALENDAR")
    return "\r\n".join(lines)


def event_ics_response(ics_text: str, *, filename: str) -> HttpResponse:
    response = HttpResponse(ics_text, content_type="text/calendar; charset=utf-8")
    response["Content-Disposition"] = f'attachment; filename="{filename}"'
    return response
