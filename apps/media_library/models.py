from __future__ import annotations

from pathlib import Path

from django.conf import settings
from django.core.exceptions import ValidationError
from django.db import models
from PIL import Image, UnidentifiedImageError

ALLOWED_IMAGE_FORMATS = {
    "JPEG": "image/jpeg",
    "PNG": "image/png",
    "WEBP": "image/webp",
}


class MediaAssetQuerySet(models.QuerySet):
    def public(self):
        return self.filter(
            visibility=MediaAsset.Visibility.PUBLIC,
            status=MediaAsset.PublicationStatus.PUBLISHED,
        )


class MediaAsset(models.Model):
    class Visibility(models.TextChoices):
        PUBLIC = "public", "Oeffentlich"
        MEMBERS = "members", "Nur Mitglieder"
        PRIVATE = "private", "Privat"

    class PublicationStatus(models.TextChoices):
        DRAFT = "draft", "Entwurf"
        PUBLISHED = "published", "Veroeffentlicht"
        ARCHIVED = "archived", "Archiviert"

    file = models.ImageField("Datei", upload_to="cms/media/")
    title = models.CharField("Titel", max_length=200)
    alt_text = models.CharField("Alt-Text", max_length=255, blank=True)
    is_decorative = models.BooleanField("Dekoratives Bild", default=False)
    caption = models.CharField("Bildlegende", max_length=255, blank=True)
    credit = models.CharField("Urheber oder Quelle", max_length=255, blank=True)
    captured_on = models.DateField("Aufnahmedatum", null=True, blank=True)
    visibility = models.CharField(
        "Sichtbarkeit",
        max_length=20,
        choices=Visibility.choices,
        default=Visibility.PUBLIC,
    )
    status = models.CharField(
        "Status",
        max_length=20,
        choices=PublicationStatus.choices,
        default=PublicationStatus.DRAFT,
    )
    uploaded_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="uploaded_media_assets",
    )
    file_size = models.PositiveIntegerField("Dateigroesse", default=0, editable=False)
    mime_type = models.CharField("MIME-Type", max_length=100, blank=True, editable=False)
    width = models.PositiveIntegerField("Breite", default=0, editable=False)
    height = models.PositiveIntegerField("Hoehe", default=0, editable=False)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    objects = MediaAssetQuerySet.as_manager()

    class Meta:
        ordering = ["title", "pk"]
        permissions = [
            ("publish_mediaasset", "Can publish media assets"),
            ("manage_private_mediaasset", "Can manage private media assets"),
        ]

    def __str__(self):
        return self.title

    def clean(self):
        super().clean()
        errors = {}

        if self.file:
            max_upload_bytes = getattr(settings, "CMS_MEDIA_MAX_UPLOAD_BYTES", 8 * 1024 * 1024)
            if self.file.size > max_upload_bytes:
                errors["file"] = (
                    "Die Datei ist zu gross. Erlaubt sind hoechstens "
                    f"{max_upload_bytes // (1024 * 1024)} MB."
                )

            suffix = Path(self.file.name).suffix.lower()
            if suffix == ".svg":
                errors["file"] = "SVG-Uploads sind derzeit nicht erlaubt."

            try:
                self.file.seek(0)
                with Image.open(self.file) as image:
                    image_format = (image.format or "").upper()
                    width, height = image.size
            except (UnidentifiedImageError, OSError, ValueError):
                errors["file"] = "Die Datei ist kein gueltiges Bild."
            else:
                mime_type = ALLOWED_IMAGE_FORMATS.get(image_format)
                if mime_type is None:
                    errors["file"] = "Erlaubt sind nur JPEG-, PNG- oder WebP-Bilder."
                else:
                    self.mime_type = mime_type
                    self.width = width
                    self.height = height
                    self.file_size = self.file.size
            finally:
                try:
                    self.file.seek(0)
                except (AttributeError, OSError, ValueError):
                    pass

        if not self.is_decorative and not self.alt_text.strip():
            errors["alt_text"] = (
                "Nicht dekorative Bilder brauchen vor der Veroeffentlichung einen Alt-Text."
            )

        if self.is_decorative and self.alt_text.strip():
            errors["alt_text"] = "Dekorative Bilder sollen keinen Alt-Text enthalten."

        if errors:
            raise ValidationError(errors)

    def save(self, *args, **kwargs):
        self.full_clean()
        return super().save(*args, **kwargs)
