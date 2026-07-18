from __future__ import annotations

from django.conf import settings
from django.core.exceptions import ValidationError
from django.db import models
from django.db.models import Max, Q
from django.utils import timezone

from apps.media_library.models import MediaAsset

from .constants import BLOCK_OPTION_SCHEMA, BLOCK_TYPE_KEYS, get_layout_preset_definition
from .validators import validate_internal_or_absolute_url


class PublishableQuerySet(models.QuerySet):
    def published(self, at=None):
        at = at or timezone.now()
        return self.filter(
            Q(status=PublishableStatus.PUBLISHED, published_at__lte=at)
            | Q(status=PublishableStatus.SCHEDULED, scheduled_for__lte=at)
        ).exclude(status=PublishableStatus.ARCHIVED)

    def public(self, at=None):
        return self.published(at=at).filter(visibility=Visibility.PUBLIC)

    def visible_to(self, user, at=None):
        if user and getattr(user, "is_authenticated", False):
            if user.is_superuser or user.has_perm("content.preview_unpublished_content"):
                return self.all()
            return self.published(at=at).filter(
                visibility__in=[Visibility.PUBLIC, Visibility.MEMBERS]
            )
        return self.public(at=at)


class PublishableManager(models.Manager.from_queryset(PublishableQuerySet)):
    pass


class PublishableStatus(models.TextChoices):
    DRAFT = "draft", "Entwurf"
    REVIEW = "review", "Zur Pruefung"
    SCHEDULED = "scheduled", "Geplant"
    PUBLISHED = "published", "Veroeffentlicht"
    ARCHIVED = "archived", "Archiviert"


class Visibility(models.TextChoices):
    PUBLIC = "public", "Oeffentlich"
    MEMBERS = "members", "Nur Mitglieder"
    PRIVATE = "private", "Privat"


class TimestampedModel(models.Model):
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        abstract = True


class PublishableModel(TimestampedModel):
    status = models.CharField(
        "Status",
        max_length=20,
        choices=PublishableStatus.choices,
        default=PublishableStatus.DRAFT,
    )
    visibility = models.CharField(
        "Sichtbarkeit",
        max_length=20,
        choices=Visibility.choices,
        default=Visibility.PUBLIC,
    )
    published_at = models.DateTimeField("Veroeffentlicht am", null=True, blank=True)
    scheduled_for = models.DateTimeField("Geplant fuer", null=True, blank=True)

    objects = PublishableManager()

    class Meta:
        abstract = True

    def clean(self):
        super().clean()
        errors = {}
        if self.status == PublishableStatus.PUBLISHED and self.published_at is None:
            self.published_at = timezone.now()
        if self.status == PublishableStatus.SCHEDULED and self.scheduled_for is None:
            errors["scheduled_for"] = "Geplante Inhalte brauchen einen Veroeffentlichungszeitpunkt."
        if self.scheduled_for and self.published_at and self.scheduled_for < self.published_at:
            errors["scheduled_for"] = (
                "Der geplante Veroeffentlichungszeitpunkt darf nicht vor der Publikation liegen."
            )
        if self.status == PublishableStatus.ARCHIVED and self.visibility == Visibility.PUBLIC:
            errors["visibility"] = "Archivierte Inhalte duerfen nicht oeffentlich sichtbar sein."
        if errors:
            raise ValidationError(errors)

    @property
    def is_currently_public(self):
        now = timezone.now()
        if self.status == PublishableStatus.ARCHIVED:
            return False
        if self.visibility != Visibility.PUBLIC:
            return False
        if self.status == PublishableStatus.PUBLISHED:
            return self.published_at is not None and self.published_at <= now
        if self.status == PublishableStatus.SCHEDULED:
            return self.scheduled_for is not None and self.scheduled_for <= now
        return False


class LayoutPreset(TimestampedModel):
    class Scope(models.TextChoices):
        PAGE = "page", "Seite"
        POST = "post", "Beitrag"
        BLOCK = "block", "Block"

    scope = models.CharField("Bereich", max_length=20, choices=Scope.choices)
    key = models.SlugField("Schluessel", max_length=100)
    name = models.CharField("Anzeigename", max_length=150)
    description = models.TextField("Beschreibung", blank=True)
    preview_static_path = models.CharField("Vorschaudatei", max_length=255, blank=True)
    allowed_block_types = models.JSONField("Erlaubte Blocktypen", default=list, blank=True)
    required_fields = models.JSONField("Pflichtfelder", default=list, blank=True)
    max_images = models.PositiveSmallIntegerField("Maximale Bilderzahl", default=1)
    is_active = models.BooleanField("Aktiv", default=True)

    class Meta:
        ordering = ["scope", "name", "key"]
        unique_together = [("scope", "key")]

    def __str__(self):
        return f"{self.name} ({self.get_scope_display()})"

    @property
    def template_name(self):
        definition = get_layout_preset_definition(self.scope, self.key)
        return definition.template_name if definition else ""

    def clean(self):
        super().clean()
        definition = get_layout_preset_definition(self.scope, self.key)
        if definition is None:
            raise ValidationError({"key": "Dieses Layout ist serverseitig nicht freigegeben."})


class Carousel(TimestampedModel):
    name = models.CharField("Interner Name", max_length=150, unique=True)
    title = models.CharField("Titel", max_length=200, blank=True)
    description = models.TextField("Beschreibung", blank=True)
    usage_context = models.CharField("Verwendungszweck", max_length=120, blank=True)
    is_active = models.BooleanField("Aktiv", default=True)
    visibility = models.CharField(
        "Sichtbarkeit",
        max_length=20,
        choices=Visibility.choices,
        default=Visibility.PUBLIC,
    )
    created_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="created_carousels",
    )
    last_edited_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="edited_carousels",
    )

    class Meta:
        ordering = ["name", "pk"]
        permissions = [
            ("preview_carousel", "Can preview carousel"),
            ("restore_carousel_revision", "Can restore carousel revisions"),
        ]

    def __str__(self):
        return self.name

    def to_revision_snapshot(self):
        return {
            "name": self.name,
            "title": self.title,
            "description": self.description,
            "usage_context": self.usage_context,
            "is_active": self.is_active,
            "visibility": self.visibility,
            "items": [
                item.to_snapshot() for item in self.items.order_by("position", "pk")
            ],
        }

    def create_revision(self, *, actor=None, reason=""):
        next_number = (
            self.revisions.aggregate(max_value=Max("revision_number"))["max_value"] or 0
        ) + 1
        return CarouselRevision.objects.create(
            carousel=self,
            revision_number=next_number,
            snapshot=self.to_revision_snapshot(),
            created_by=actor,
            reason=reason,
        )


class Page(PublishableModel):
    class PageType(models.TextChoices):
        SYSTEM = "system", "Systemseite"
        EDITORIAL = "editorial", "Redaktionelle Seite"

    title = models.CharField("Titel", max_length=200)
    slug = models.SlugField("Slug", unique=True)
    page_key = models.SlugField("Interner Seitenschluessel", unique=True)
    page_type = models.CharField(
        "Seitentyp",
        max_length=20,
        choices=PageType.choices,
        default=PageType.EDITORIAL,
    )
    layout_preset = models.ForeignKey(
        LayoutPreset,
        on_delete=models.PROTECT,
        related_name="pages",
        limit_choices_to={"scope": LayoutPreset.Scope.PAGE},
    )
    meta_title = models.CharField("Meta-Titel", max_length=200, blank=True)
    meta_description = models.CharField("Meta-Beschreibung", max_length=255, blank=True)
    og_image = models.ForeignKey(
        MediaAsset,
        on_delete=models.PROTECT,
        null=True,
        blank=True,
        related_name="page_open_graph_usages",
    )
    created_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="created_pages",
    )
    last_edited_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="edited_pages",
    )
    version_number = models.PositiveIntegerField("Versionsnummer", default=1)

    class Meta:
        ordering = ["title", "pk"]
        permissions = [
            ("publish_page", "Can publish pages"),
            ("preview_page", "Can preview pages"),
            ("restore_page_revision", "Can restore page revisions"),
        ]

    def __str__(self):
        return self.title

    def clean(self):
        super().clean()
        errors = {}
        if self.layout_preset_id and self.layout_preset.scope != LayoutPreset.Scope.PAGE:
            errors["layout_preset"] = "Seiten duerfen nur Seitenlayouts verwenden."
        if self.og_image_id and self.og_image.status == MediaAsset.PublicationStatus.ARCHIVED:
            errors["og_image"] = (
                "Archivierte Medien duerfen nicht als Open-Graph-Bild verwendet werden."
            )
        if errors:
            raise ValidationError(errors)

    def to_revision_snapshot(self):
        return {
            "title": self.title,
            "slug": self.slug,
            "page_key": self.page_key,
            "page_type": self.page_type,
            "status": self.status,
            "visibility": self.visibility,
            "published_at": self.published_at.isoformat() if self.published_at else "",
            "scheduled_for": self.scheduled_for.isoformat() if self.scheduled_for else "",
            "layout_preset": self.layout_preset.key if self.layout_preset_id else "",
            "meta_title": self.meta_title,
            "meta_description": self.meta_description,
            "og_image_id": self.og_image_id,
            "version_number": self.version_number,
            "sections": [
                section.to_snapshot() for section in self.sections.order_by("position", "pk")
            ],
        }

    def create_revision(self, *, actor=None, reason=""):
        next_number = (
            self.revisions.aggregate(max_value=Max("revision_number"))["max_value"] or 0
        ) + 1
        return PageRevision.objects.create(
            page=self,
            revision_number=next_number,
            snapshot=self.to_revision_snapshot(),
            created_by=actor,
            reason=reason,
            status=self.status,
        )


class Post(PublishableModel):
    title = models.CharField("Titel", max_length=200)
    slug = models.SlugField("Slug", unique=True)
    teaser = models.CharField("Teaser", max_length=280)
    body_html = models.TextField("Beitrag", blank=True)
    layout_preset = models.ForeignKey(
        LayoutPreset,
        on_delete=models.PROTECT,
        related_name="posts",
        limit_choices_to={"scope": LayoutPreset.Scope.POST},
    )
    hero_image = models.ForeignKey(
        MediaAsset,
        on_delete=models.PROTECT,
        null=True,
        blank=True,
        related_name="post_hero_usages",
    )
    og_image = models.ForeignKey(
        MediaAsset,
        on_delete=models.PROTECT,
        null=True,
        blank=True,
        related_name="post_open_graph_usages",
    )
    author = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="authored_posts",
    )
    created_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="created_posts",
    )
    last_edited_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="edited_posts",
    )
    event_date = models.DateField("Anlassdatum", null=True, blank=True)
    event_location = models.CharField("Ort", max_length=200, blank=True)
    cta_label = models.CharField("Call-to-Action Label", max_length=80, blank=True)
    cta_url = models.CharField(
        "Call-to-Action URL",
        max_length=500,
        blank=True,
        validators=[validate_internal_or_absolute_url],
    )
    categories = models.JSONField("Kategorien", default=list, blank=True)
    meta_title = models.CharField("Meta-Titel", max_length=200, blank=True)
    meta_description = models.CharField("Meta-Beschreibung", max_length=255, blank=True)
    is_homepage_pinned = models.BooleanField("Auf Startseite angepinnt", default=False)
    pin_priority = models.PositiveIntegerField("Pin-Prioritaet", default=0)
    version_number = models.PositiveIntegerField("Versionsnummer", default=1)

    class Meta:
        ordering = ["-published_at", "-created_at", "title"]
        permissions = [
            ("publish_post", "Can publish posts"),
            ("preview_post", "Can preview posts"),
            ("pin_post_homepage", "Can pin posts to the homepage"),
            ("restore_post_revision", "Can restore post revisions"),
            ("preview_unpublished_content", "Can preview unpublished content"),
        ]

    def __str__(self):
        return self.title

    def clean(self):
        super().clean()
        errors = {}
        if self.layout_preset_id and self.layout_preset.scope != LayoutPreset.Scope.POST:
            errors["layout_preset"] = "Beitraege duerfen nur Beitragslayouts verwenden."
        if self.hero_image_id and self.hero_image.status == MediaAsset.PublicationStatus.ARCHIVED:
            errors["hero_image"] = (
                "Archivierte Medien duerfen nicht als Titelbild verwendet werden."
            )
        if self.og_image_id and self.og_image.status == MediaAsset.PublicationStatus.ARCHIVED:
            errors["og_image"] = (
                "Archivierte Medien duerfen nicht als Open-Graph-Bild verwendet werden."
            )
        if bool(self.cta_label) != bool(self.cta_url):
            errors["cta_url"] = (
                "Label und URL fuer den Call-to-Action muessen gemeinsam gepflegt werden."
            )
        if self.is_homepage_pinned and self.visibility != Visibility.PUBLIC:
            errors["visibility"] = "Angepinnte Beitraege muessen oeffentlich sichtbar sein."
        if self.is_homepage_pinned:
            if self.status not in {PublishableStatus.PUBLISHED, PublishableStatus.SCHEDULED}:
                errors["is_homepage_pinned"] = (
                    "Nur veroeffentlichte oder geplante Beitraege "
                    "koennen auf der Startseite erscheinen."
                )
            now = timezone.now()
            other_pins = Post.objects.exclude(pk=self.pk).filter(is_homepage_pinned=True)
            future_scheduled = (
                self.status == PublishableStatus.SCHEDULED
                and self.scheduled_for is not None
                and self.scheduled_for > now
            )
            active_pins = other_pins.exclude(
                status=PublishableStatus.SCHEDULED,
                scheduled_for__gt=now,
            )
            queued_pins = other_pins.filter(
                status=PublishableStatus.SCHEDULED,
                scheduled_for__gt=now,
            )
            if future_scheduled and queued_pins.exists():
                errors["is_homepage_pinned"] = (
                    "Es kann nur ein geplanter Startseitenbeitrag vorgemerkt sein."
                )
            elif not future_scheduled and active_pins.exists():
                errors["is_homepage_pinned"] = (
                    "Es kann nur ein aktueller Startseitenbeitrag hervorgehoben sein."
                )
        if not isinstance(self.categories, list):
            errors["categories"] = "Kategorien muessen als Liste gespeichert werden."
        if errors:
            raise ValidationError(errors)

    def to_revision_snapshot(self):
        return {
            "title": self.title,
            "slug": self.slug,
            "teaser": self.teaser,
            "body_html": self.body_html,
            "status": self.status,
            "visibility": self.visibility,
            "published_at": self.published_at.isoformat() if self.published_at else "",
            "scheduled_for": self.scheduled_for.isoformat() if self.scheduled_for else "",
            "layout_preset": self.layout_preset.key if self.layout_preset_id else "",
            "hero_image_id": self.hero_image_id,
            "og_image_id": self.og_image_id,
            "author_id": self.author_id,
            "event_date": self.event_date.isoformat() if self.event_date else "",
            "event_location": self.event_location,
            "cta_label": self.cta_label,
            "cta_url": self.cta_url,
            "categories": list(self.categories),
            "meta_title": self.meta_title,
            "meta_description": self.meta_description,
            "is_homepage_pinned": self.is_homepage_pinned,
            "pin_priority": self.pin_priority,
            "version_number": self.version_number,
            "blocks": [
                block.to_snapshot() for block in self.blocks.order_by("position", "pk")
            ],
        }

    def create_revision(self, *, actor=None, reason=""):
        next_number = (
            self.revisions.aggregate(max_value=Max("revision_number"))["max_value"] or 0
        ) + 1
        return PostRevision.objects.create(
            post=self,
            revision_number=next_number,
            snapshot=self.to_revision_snapshot(),
            created_by=actor,
            reason=reason,
            status=self.status,
        )

    @property
    def has_body_html(self) -> bool:
        return bool((self.body_html or "").strip())


class StructuredBlockBase(TimestampedModel):
    class BlockType(models.TextChoices):
        HEADING = "heading", "Ueberschrift"
        TEXT = "text", "Fliesstext"
        IMAGE = "image", "Bild"
        IMAGE_TEXT = "image_text", "Bild mit Text"
        HERO_IMAGE = "hero_image", "Grosses Bild"
        GALLERY = "gallery", "Galerie"
        CAROUSEL = "carousel", "Karussell"
        QUOTE = "quote", "Zitat"
        CTA = "cta", "Call-to-Action"
        LINK_LIST = "link_list", "Linkliste"
        DOCUMENT_LIST = "document_list", "Dokumentenliste"
        EVENT_LIST = "event_list", "Veranstaltungsliste"
        POST_LIST = "post_list", "Beitragsliste"
        PEOPLE_LIST = "people_list", "Personenuebersicht"
        TIMELINE = "timeline", "Zeitstrahl"
        DIVIDER = "divider", "Trennbereich"
        NOTICE = "notice", "Hinweis"

    block_type = models.CharField("Blocktyp", max_length=30, choices=BlockType.choices)
    layout_preset = models.ForeignKey(
        LayoutPreset,
        on_delete=models.PROTECT,
        related_name="+",
        limit_choices_to={"scope": LayoutPreset.Scope.BLOCK},
    )
    position = models.PositiveIntegerField("Position", default=0)
    is_active = models.BooleanField("Aktiv", default=True)
    anchor_id = models.SlugField("Anker-ID", max_length=100, blank=True)
    eyebrow = models.CharField("Eyebrow", max_length=120, blank=True)
    heading = models.CharField("Ueberschrift", max_length=200, blank=True)
    body = models.TextField("Inhalt", blank=True)
    image = models.ForeignKey(
        MediaAsset,
        on_delete=models.PROTECT,
        null=True,
        blank=True,
        related_name="+",
    )
    carousel = models.ForeignKey(
        Carousel,
        on_delete=models.PROTECT,
        null=True,
        blank=True,
        related_name="+",
    )
    link_url = models.CharField(
        "Link-URL",
        max_length=500,
        blank=True,
        validators=[validate_internal_or_absolute_url],
    )
    link_label = models.CharField("Link-Label", max_length=80, blank=True)
    options = models.JSONField("Darstellungsoptionen", default=dict, blank=True)

    class Meta:
        abstract = True
        ordering = ["position", "pk"]

    def clean(self):
        super().clean()
        errors = {}
        block_type_keys = set(BLOCK_TYPE_KEYS)
        if self.block_type not in block_type_keys:
            errors["block_type"] = "Dieser Blocktyp ist nicht freigegeben."
        if self.layout_preset_id:
            if self.layout_preset.scope != LayoutPreset.Scope.BLOCK:
                errors["layout_preset"] = "Inhaltsbloecke duerfen nur Blocklayouts verwenden."
            elif self.block_type not in self.layout_preset.allowed_block_types:
                errors["layout_preset"] = (
                    "Dieses Layout ist fuer den gewaehlten Blocktyp nicht erlaubt."
                )
        if bool(self.link_url) != bool(self.link_label):
            errors["link_url"] = "Link-URL und Link-Label muessen gemeinsam gepflegt werden."
        if (
            self.block_type
            in {
                self.BlockType.IMAGE,
                self.BlockType.HERO_IMAGE,
                self.BlockType.IMAGE_TEXT,
            }
            and not self.image_id
        ):
            errors["image"] = "Dieser Blocktyp braucht ein Bild."
        if (
            self.block_type in {self.BlockType.GALLERY, self.BlockType.CAROUSEL}
            and not self.carousel_id
        ):
            errors["carousel"] = "Dieser Blocktyp braucht ein Karussell oder eine Galeriezuweisung."
        if self.block_type == self.BlockType.CTA and not (self.link_url and self.link_label):
            errors["link_url"] = "Call-to-Action-Bloecke brauchen einen Link und ein Label."
        if self.block_type == self.BlockType.HEADING and not self.heading.strip():
            errors["heading"] = "Ueberschriftsbloecke brauchen eine Ueberschrift."
        if (
            self.block_type in {self.BlockType.TEXT, self.BlockType.QUOTE, self.BlockType.NOTICE}
            and not self.body.strip()
        ):
            errors["body"] = "Dieser Blocktyp braucht Textinhalt."
        if not isinstance(self.options, dict):
            errors["options"] = "Darstellungsoptionen muessen als Objekt gespeichert werden."
        else:
            allowed_options = BLOCK_OPTION_SCHEMA.get(self.block_type, {})
            unknown_keys = set(self.options) - set(allowed_options)
            if unknown_keys:
                errors["options"] = "Unbekannte Darstellungsoptionen: " + ", ".join(
                    sorted(unknown_keys)
                )
            else:
                invalid_keys = []
                for key, value in self.options.items():
                    if value not in allowed_options[key]:
                        invalid_keys.append(key)
                if invalid_keys:
                    errors["options"] = "Unzulaessige Werte fuer: " + ", ".join(
                        sorted(invalid_keys)
                    )
        if errors:
            raise ValidationError(errors)

    def to_snapshot(self):
        return {
            "block_type": self.block_type,
            "layout_preset": self.layout_preset.key if self.layout_preset_id else "",
            "position": self.position,
            "is_active": self.is_active,
            "anchor_id": self.anchor_id,
            "eyebrow": self.eyebrow,
            "heading": self.heading,
            "body": self.body,
            "image_id": self.image_id,
            "carousel_id": self.carousel_id,
            "link_url": self.link_url,
            "link_label": self.link_label,
            "options": dict(self.options),
        }


class PageSection(StructuredBlockBase):
    page = models.ForeignKey(Page, on_delete=models.CASCADE, related_name="sections")

    class Meta(StructuredBlockBase.Meta):
        verbose_name = "Seitenblock"
        verbose_name_plural = "Seitenbloecke"


class PostBlock(StructuredBlockBase):
    post = models.ForeignKey(Post, on_delete=models.CASCADE, related_name="blocks")

    class Meta(StructuredBlockBase.Meta):
        verbose_name = "Beitragsblock"
        verbose_name_plural = "Beitragsbloecke"


class CarouselItem(TimestampedModel):
    carousel = models.ForeignKey(Carousel, on_delete=models.CASCADE, related_name="items")
    image = models.ForeignKey(
        MediaAsset,
        on_delete=models.PROTECT,
        related_name="carousel_items",
    )
    position = models.PositiveIntegerField("Position", default=0)
    is_active = models.BooleanField("Aktiv", default=True)
    heading = models.CharField("Ueberschrift", max_length=200, blank=True)
    body = models.TextField("Text", blank=True)
    link_url = models.CharField(
        "Link-URL",
        max_length=500,
        blank=True,
        validators=[validate_internal_or_absolute_url],
    )
    link_label = models.CharField("Link-Label", max_length=80, blank=True)
    starts_at = models.DateTimeField("Startzeitpunkt", null=True, blank=True)
    ends_at = models.DateTimeField("Endzeitpunkt", null=True, blank=True)

    class Meta:
        ordering = ["position", "pk"]

    def __str__(self):
        return f"{self.carousel.name} #{self.position}"

    def clean(self):
        super().clean()
        errors = {}
        if bool(self.link_url) != bool(self.link_label):
            errors["link_url"] = "Link-URL und Link-Label muessen gemeinsam gepflegt werden."
        if self.ends_at and self.starts_at and self.ends_at < self.starts_at:
            errors["ends_at"] = "Das Enddatum darf nicht vor dem Startdatum liegen."
        if errors:
            raise ValidationError(errors)

    def to_snapshot(self):
        return {
            "image_id": self.image_id,
            "position": self.position,
            "is_active": self.is_active,
            "heading": self.heading,
            "body": self.body,
            "link_url": self.link_url,
            "link_label": self.link_label,
            "starts_at": self.starts_at.isoformat() if self.starts_at else "",
            "ends_at": self.ends_at.isoformat() if self.ends_at else "",
        }


class PageRevision(TimestampedModel):
    page = models.ForeignKey(Page, on_delete=models.CASCADE, related_name="revisions")
    revision_number = models.PositiveIntegerField("Revisionsnummer")
    snapshot = models.JSONField("Snapshot", default=dict)
    created_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="page_revisions",
    )
    reason = models.CharField("Aenderungsgrund", max_length=255, blank=True)
    status = models.CharField("Status", max_length=20, choices=PublishableStatus.choices)

    class Meta:
        ordering = ["-revision_number", "-created_at"]
        unique_together = [("page", "revision_number")]


class PostRevision(TimestampedModel):
    post = models.ForeignKey(Post, on_delete=models.CASCADE, related_name="revisions")
    revision_number = models.PositiveIntegerField("Revisionsnummer")
    snapshot = models.JSONField("Snapshot", default=dict)
    created_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="post_revisions",
    )
    reason = models.CharField("Aenderungsgrund", max_length=255, blank=True)
    status = models.CharField("Status", max_length=20, choices=PublishableStatus.choices)

    class Meta:
        ordering = ["-revision_number", "-created_at"]
        unique_together = [("post", "revision_number")]


class CarouselRevision(TimestampedModel):
    carousel = models.ForeignKey(Carousel, on_delete=models.CASCADE, related_name="revisions")
    revision_number = models.PositiveIntegerField("Revisionsnummer")
    snapshot = models.JSONField("Snapshot", default=dict)
    created_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="carousel_revisions",
    )
    reason = models.CharField("Aenderungsgrund", max_length=255, blank=True)

    class Meta:
        ordering = ["-revision_number", "-created_at"]
        unique_together = [("carousel", "revision_number")]
