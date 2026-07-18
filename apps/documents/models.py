from __future__ import annotations

import uuid
from pathlib import Path

from django.conf import settings
from django.contrib.auth.models import Group
from django.core.exceptions import ValidationError
from django.db import models
from django.db.models import Q
from django.utils import timezone

from apps.content.models import TimestampedModel

from .storage import private_document_storage


def document_version_upload_to(instance, filename):
    suffix = Path(filename).suffix.lower()
    return f"documents/{uuid.uuid4().hex}{suffix}"


def _user_is_functionary(user) -> bool:
    if not user or not getattr(user, "is_authenticated", False) or not user.is_active:
        return False
    return bool(
        user.is_superuser
        or user.has_perm("members.view_memberprofile")
        or user.has_perm("members.manage_member_profiles")
        or user.has_perm("accounts.add_accountinvitation")
        or user.has_perm("documents.manage_publication_documents")
        or user.has_perm("documents.manage_sensitive_documents")
    )


def _user_can_view_highly_sensitive_documents(user) -> bool:
    if not user or not getattr(user, "is_authenticated", False) or not user.is_active:
        return False
    if (
        user.is_superuser
        or user.has_perm("documents.manage_sensitive_documents")
        or user.has_perm("members.view_sensitive_documents")
    ):
        return True
    profile = getattr(user, "member_profile", None)
    if profile is None:
        return False
    return profile.has_member_role(profile.MemberRole.BURSCH)


class DocumentCategory(models.Model):
    name = models.CharField("Name", max_length=120, unique=True)
    slug = models.SlugField("Slug", max_length=140, unique=True)
    description = models.TextField("Beschreibung", blank=True)
    is_active = models.BooleanField("Aktiv", default=True)

    class Meta:
        ordering = ["name", "pk"]
        verbose_name = "Dokumentkategorie"
        verbose_name_plural = "Dokumentkategorien"

    def __str__(self):
        return self.name


class DocumentQuerySet(models.QuerySet):
    def published(self, at=None):
        at = at or timezone.now()
        return (
            self.filter(
                status=Document.Status.PUBLISHED,
                published_at__lte=at,
                current_version__isnull=False,
            )
            .filter(Q(valid_until__isnull=True) | Q(valid_until__gte=at))
            .exclude(status=Document.Status.ARCHIVED)
        )

    def visible_to(self, user, at=None):
        if not user or not getattr(user, "is_authenticated", False) or not user.is_active:
            return self.none()
        if user.is_superuser or user.has_perm("documents.manage_sensitive_documents"):
            return self.all()
        published = self.published(at=at)
        visibility_filter = (
            Q(visibility=Document.Visibility.ALL_MEMBERS)
            | Q(
                visibility=Document.Visibility.SELECTED_GROUPS,
                allowed_groups__in=user.groups.all(),
            )
            | Q(visibility=Document.Visibility.SELECTED_USERS, allowed_users=user)
        )
        if _user_is_functionary(user):
            visibility_filter |= Q(visibility=Document.Visibility.FUNCTIONARIES)
        if _user_can_view_highly_sensitive_documents(user):
            visibility_filter |= Q(visibility=Document.Visibility.HIGHLY_SENSITIVE)
        return published.filter(visibility_filter).distinct()

    def editable_by(self, user):
        if not user or not getattr(user, "is_authenticated", False) or not user.is_active:
            return self.none()
        if user.is_superuser or user.has_perm("documents.manage_sensitive_documents"):
            return self.all()
        if user.has_perm("documents.manage_publication_documents"):
            return self.exclude(visibility=Document.Visibility.HIGHLY_SENSITIVE)
        return self.none()


class DocumentManager(models.Manager.from_queryset(DocumentQuerySet)):
    pass


class Document(TimestampedModel):
    class Status(models.TextChoices):
        DRAFT = "draft", "Entwurf"
        PUBLISHED = "published", "Veroeffentlicht"
        ARCHIVED = "archived", "Archiviert"

    class Visibility(models.TextChoices):
        ALL_MEMBERS = "all_members", "Alle Mitglieder"
        SELECTED_GROUPS = "selected_groups", "Ausgewaehlte Gruppen"
        SELECTED_USERS = "selected_users", "Ausgewaehlte Benutzer"
        FUNCTIONARIES = "functionaries", "Funktionstraeger"
        HIGHLY_SENSITIVE = "highly_sensitive", "Besonders sensibel"

    title = models.CharField("Titel", max_length=200)
    description = models.TextField("Beschreibung", blank=True)
    category = models.ForeignKey(
        DocumentCategory,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="documents",
    )
    visibility = models.CharField(
        "Sichtbarkeit",
        max_length=30,
        choices=Visibility.choices,
        default=Visibility.ALL_MEMBERS,
    )
    status = models.CharField(
        "Status",
        max_length=20,
        choices=Status.choices,
        default=Status.DRAFT,
    )
    allowed_groups = models.ManyToManyField(
        Group,
        blank=True,
        related_name="visible_documents",
        verbose_name="Erlaubte Gruppen",
    )
    allowed_users = models.ManyToManyField(
        settings.AUTH_USER_MODEL,
        blank=True,
        related_name="directly_visible_documents",
        verbose_name="Erlaubte Benutzer",
    )
    created_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="created_documents",
    )
    last_edited_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="edited_documents",
    )
    published_at = models.DateTimeField("Veroeffentlicht am", null=True, blank=True)
    valid_until = models.DateTimeField("Gueltig bis", null=True, blank=True)
    archived_at = models.DateTimeField("Archiviert am", null=True, blank=True)
    current_version = models.ForeignKey(
        "documents.DocumentVersion",
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="+",
        verbose_name="Aktuelle Version",
    )
    version_counter = models.PositiveIntegerField("Versionszaehler", default=0)

    objects = DocumentManager()

    class Meta:
        ordering = ["title", "pk"]
        permissions = [
            ("manage_publication_documents", "Can manage publication documents"),
            ("manage_sensitive_documents", "Can manage sensitive documents"),
        ]

    def __str__(self):
        return self.title

    def clean(self):
        super().clean()
        errors = {}
        if self.status == self.Status.PUBLISHED and self.published_at is None:
            self.published_at = timezone.now()
        if self.status == self.Status.ARCHIVED:
            self.archived_at = self.archived_at or timezone.now()
        elif self.archived_at:
            self.archived_at = None
        if self.valid_until and self.published_at and self.valid_until < self.published_at:
            errors["valid_until"] = "Das Gueltigkeitsdatum darf nicht vor der Publikation liegen."
        if errors:
            raise ValidationError(errors)

    @property
    def current_version_number(self):
        return self.current_version.version_number if self.current_version_id else 0

    def is_visible_to(self, user) -> bool:
        return Document.objects.filter(pk=self.pk).visible_to(user).exists()


class DocumentVersion(TimestampedModel):
    document = models.ForeignKey(Document, on_delete=models.CASCADE, related_name="versions")
    version_number = models.PositiveIntegerField("Versionsnummer")
    file = models.FileField(
        "Datei",
        storage=private_document_storage,
        upload_to=document_version_upload_to,
    )
    original_filename = models.CharField("Originaldateiname", max_length=255)
    mime_type = models.CharField("MIME-Type", max_length=100)
    file_size = models.PositiveBigIntegerField("Dateigroesse")
    checksum = models.CharField("Pruefsumme", max_length=64)
    change_note = models.CharField("Aenderungsnotiz", max_length=255, blank=True)
    uploaded_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="document_versions_uploaded",
    )

    class Meta:
        ordering = ["-version_number", "-created_at"]
        unique_together = [("document", "version_number")]

    def __str__(self):
        return f"{self.document.title} v{self.version_number}"
