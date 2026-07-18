from __future__ import annotations

from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

from django.conf import settings
from django.contrib.auth.models import Group
from django.core.exceptions import ValidationError
from django.db import models
from django.db.models import Max, Q
from django.utils import timezone

from apps.content.models import TimestampedModel
from apps.media_library.models import MediaAsset


class EventCategory(models.Model):
    name = models.CharField("Name", max_length=120, unique=True)
    slug = models.SlugField("Slug", max_length=140, unique=True)
    description = models.TextField("Beschreibung", blank=True)
    is_active = models.BooleanField("Aktiv", default=True)

    class Meta:
        ordering = ["name", "pk"]
        verbose_name = "Veranstaltungskategorie"
        verbose_name_plural = "Veranstaltungskategorien"

    def __str__(self):
        return self.name


class EventQuerySet(models.QuerySet):
    def released(self, at=None):
        at = at or timezone.now()
        return self.filter(
            Q(
                status__in=[
                    Event.Status.PUBLISHED,
                    Event.Status.CANCELLED,
                    Event.Status.COMPLETED,
                ],
                published_at__lte=at,
            )
            | Q(status=Event.Status.SCHEDULED, scheduled_for__lte=at)
        ).exclude(status=Event.Status.ARCHIVED)

    def public(self, at=None):
        return self.released(at=at).filter(visibility=Event.Visibility.PUBLIC)

    def visible_to(self, user, at=None):
        if not user or not getattr(user, "is_authenticated", False) or not user.is_active:
            return self.public(at=at)
        if user.is_superuser or user.has_perm("events.preview_event"):
            return self.all()
        return (
            self.released(at=at)
            .filter(
                Q(visibility=Event.Visibility.PUBLIC)
                | Q(visibility=Event.Visibility.MEMBERS)
                | Q(
                    visibility=Event.Visibility.SELECTED_GROUPS,
                    allowed_groups__in=user.groups.all(),
                )
                | Q(visibility=Event.Visibility.SELECTED_USERS, allowed_users=user)
            )
            .distinct()
        )

    def upcoming_for(self, user=None, at=None):
        at = at or timezone.now()
        queryset = self.visible_to(user, at=at) if user else self.public(at=at)
        return queryset.filter(end_at__gte=at).order_by("start_at", "title", "pk")

    def past_for(self, user=None, at=None):
        at = at or timezone.now()
        queryset = self.visible_to(user, at=at) if user else self.public(at=at)
        return queryset.filter(end_at__lt=at).order_by("-start_at", "title", "pk")


class EventManager(models.Manager.from_queryset(EventQuerySet)):
    pass


class Event(TimestampedModel):
    class Status(models.TextChoices):
        DRAFT = "draft", "Entwurf"
        REVIEW = "review", "Zur Pruefung"
        SCHEDULED = "scheduled", "Geplant"
        PUBLISHED = "published", "Veroeffentlicht"
        CANCELLED = "cancelled", "Abgesagt"
        COMPLETED = "completed", "Abgeschlossen"
        ARCHIVED = "archived", "Archiviert"

    class Visibility(models.TextChoices):
        PUBLIC = "public", "Oeffentlich"
        MEMBERS = "members", "Nur Mitglieder"
        SELECTED_GROUPS = "selected_groups", "Ausgewaehlte Gruppen"
        SELECTED_USERS = "selected_users", "Ausgewaehlte Benutzer"

    title = models.CharField("Titel", max_length=200)
    slug = models.SlugField("Slug", unique=True)
    short_description = models.CharField("Kurzbeschreibung", max_length=280)
    description = models.TextField("Beschreibung")
    start_at = models.DateTimeField("Beginn")
    end_at = models.DateTimeField("Ende")
    timezone_name = models.CharField("Zeitzone", max_length=64, default="Europe/Zurich")
    location_name = models.CharField("Ort", max_length=200)
    location_address = models.TextField("Adresse", blank=True)
    map_url = models.URLField("Karten- oder externe URL", blank=True)
    hero_image = models.ForeignKey(
        MediaAsset,
        on_delete=models.PROTECT,
        null=True,
        blank=True,
        related_name="event_hero_usages",
    )
    category = models.ForeignKey(
        EventCategory,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="events",
    )
    visibility = models.CharField(
        "Sichtbarkeit",
        max_length=30,
        choices=Visibility.choices,
        default=Visibility.PUBLIC,
    )
    status = models.CharField(
        "Status",
        max_length=20,
        choices=Status.choices,
        default=Status.DRAFT,
    )
    created_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="created_events",
    )
    last_edited_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="edited_events",
    )
    published_at = models.DateTimeField("Veroeffentlicht am", null=True, blank=True)
    scheduled_for = models.DateTimeField("Geplant fuer", null=True, blank=True)
    signup_url = models.URLField("Anmeldelink", blank=True)
    signup_deadline = models.DateTimeField("Anmeldefrist", null=True, blank=True)
    max_participants = models.PositiveIntegerField("Maximale Teilnehmerzahl", null=True, blank=True)
    archived_at = models.DateTimeField("Archiviert am", null=True, blank=True)
    version_number = models.PositiveIntegerField("Versionsnummer", default=1)
    allowed_groups = models.ManyToManyField(
        Group,
        blank=True,
        related_name="visible_events",
        verbose_name="Erlaubte Gruppen",
    )
    allowed_users = models.ManyToManyField(
        settings.AUTH_USER_MODEL,
        blank=True,
        related_name="directly_visible_events",
        verbose_name="Erlaubte Benutzer",
    )

    objects = EventManager()

    class Meta:
        ordering = ["start_at", "title", "pk"]
        permissions = [
            ("publish_event", "Can publish events"),
            ("schedule_event", "Can schedule events"),
            ("cancel_event", "Can cancel events"),
            ("archive_event", "Can archive events"),
            ("preview_event", "Can preview events"),
            ("restore_event_revision", "Can restore event revisions"),
            ("view_eventrevision", "Can view event revisions"),
        ]

    def __str__(self):
        return self.title

    def clean(self):
        super().clean()
        errors = {}
        try:
            ZoneInfo(self.timezone_name or "Europe/Zurich")
        except ZoneInfoNotFoundError:
            errors["timezone_name"] = "Die Zeitzone ist ungueltig."
        if self.end_at and self.start_at and self.end_at < self.start_at:
            errors["end_at"] = "Das Ende darf nicht vor dem Beginn liegen."
        if self.status == self.Status.SCHEDULED and self.scheduled_for is None:
            errors["scheduled_for"] = "Geplante Veranstaltungen brauchen einen Zeitpunkt."
        if self.signup_deadline and self.signup_deadline > self.start_at:
            errors["signup_deadline"] = "Die Anmeldefrist muss vor dem Anlassbeginn liegen."
        if self.max_participants is not None and self.max_participants < 1:
            errors["max_participants"] = "Die maximale Teilnehmerzahl muss mindestens 1 sein."
        if self.hero_image_id and self.hero_image.status == MediaAsset.PublicationStatus.ARCHIVED:
            errors["hero_image"] = (
                "Archivierte Medien duerfen nicht als Titelbild verwendet werden."
            )
        if bool(self.map_url) and not self.map_url.startswith(("http://", "https://")):
            errors["map_url"] = "Die externe URL muss mit http:// oder https:// beginnen."
        if self.status in {self.Status.PUBLISHED, self.Status.CANCELLED, self.Status.COMPLETED}:
            self.published_at = self.published_at or timezone.now()
        if self.status != self.Status.SCHEDULED:
            self.scheduled_for = None
        if self.status == self.Status.ARCHIVED:
            self.archived_at = self.archived_at or timezone.now()
        elif self.archived_at:
            self.archived_at = None
        if errors:
            raise ValidationError(errors)

    @property
    def effective_release_at(self):
        if self.status == self.Status.SCHEDULED:
            return self.scheduled_for
        return self.published_at

    @property
    def is_cancelled(self):
        return self.status == self.Status.CANCELLED

    @property
    def is_archived(self):
        return self.status == self.Status.ARCHIVED

    def is_visible_to(self, user, at=None) -> bool:
        return Event.objects.filter(pk=self.pk).visible_to(user, at=at).exists()

    def to_revision_snapshot(self):
        return {
            "title": self.title,
            "slug": self.slug,
            "short_description": self.short_description,
            "description": self.description,
            "start_at": self.start_at.isoformat() if self.start_at else "",
            "end_at": self.end_at.isoformat() if self.end_at else "",
            "timezone_name": self.timezone_name,
            "location_name": self.location_name,
            "location_address": self.location_address,
            "map_url": self.map_url,
            "hero_image_id": self.hero_image_id,
            "category_id": self.category_id,
            "visibility": self.visibility,
            "status": self.status,
            "published_at": self.published_at.isoformat() if self.published_at else "",
            "scheduled_for": self.scheduled_for.isoformat() if self.scheduled_for else "",
            "signup_url": self.signup_url,
            "signup_deadline": self.signup_deadline.isoformat() if self.signup_deadline else "",
            "max_participants": self.max_participants,
            "archived_at": self.archived_at.isoformat() if self.archived_at else "",
            "version_number": self.version_number,
            "allowed_group_ids": list(
                self.allowed_groups.order_by("pk").values_list("pk", flat=True)
            ),
            "allowed_user_ids": list(
                self.allowed_users.order_by("pk").values_list("pk", flat=True)
            ),
        }

    def create_revision(self, *, actor=None, reason=""):
        next_number = (
            self.revisions.aggregate(max_value=Max("revision_number"))["max_value"] or 0
        ) + 1
        return EventRevision.objects.create(
            event=self,
            revision_number=next_number,
            snapshot=self.to_revision_snapshot(),
            created_by=actor,
            reason=reason,
            status=self.status,
        )


class EventRevision(TimestampedModel):
    event = models.ForeignKey(Event, on_delete=models.CASCADE, related_name="revisions")
    revision_number = models.PositiveIntegerField("Revisionsnummer")
    snapshot = models.JSONField("Snapshot", default=dict)
    created_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="event_revisions",
    )
    reason = models.CharField("Aenderungsgrund", max_length=255, blank=True)
    status = models.CharField("Status", max_length=20, choices=Event.Status.choices)

    class Meta:
        ordering = ["-revision_number", "-created_at"]
        unique_together = [("event", "revision_number")]
