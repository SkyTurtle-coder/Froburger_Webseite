from __future__ import annotations

from pathlib import Path

from django.core.files import File
from django.core.files.base import ContentFile
from django.db import transaction
from django.utils import timezone
from django.utils.dateparse import parse_date, parse_datetime

from apps.audit.models import AuditLogEntry
from apps.audit.services import record_audit_event
from apps.media_library.models import MediaAsset

from .models import (
    Carousel,
    CarouselItem,
    LayoutPreset,
    Page,
    PageSection,
    Post,
    PostBlock,
    PublishableStatus,
    Visibility,
)


def get_post_layout(key: str) -> LayoutPreset:
    return LayoutPreset.objects.get(scope=LayoutPreset.Scope.POST, key=key)


def get_block_layout(key: str) -> LayoutPreset:
    return LayoutPreset.objects.get(scope=LayoutPreset.Scope.BLOCK, key=key)


def get_page_layout(key: str) -> LayoutPreset:
    return LayoutPreset.objects.get(scope=LayoutPreset.Scope.PAGE, key=key)


def _parse_snapshot_datetime(value: str):
    if not value:
        return None
    parsed = parse_datetime(value)
    if parsed is None:
        return None
    if timezone.is_naive(parsed):
        return timezone.make_aware(parsed, timezone.get_current_timezone())
    return parsed


def _parse_snapshot_date(value: str):
    if not value:
        return None
    return parse_date(value)


def import_static_image_to_media(
    *,
    relative_static_path: str,
    title: str,
    alt_text: str,
    uploaded_by=None,
) -> MediaAsset:
    existing = MediaAsset.objects.filter(title=title).first()
    if existing:
        return existing

    static_path = Path(__file__).resolve().parents[2] / "static" / relative_static_path
    asset = MediaAsset(
        title=title,
        alt_text=alt_text,
        uploaded_by=uploaded_by,
        status=MediaAsset.PublicationStatus.PUBLISHED,
        visibility=MediaAsset.Visibility.PUBLIC,
    )
    with static_path.open("rb") as source_file:
        asset.file.save(static_path.name, File(source_file), save=False)
    asset.save()
    return asset


def ensure_homepage_page(actor=None) -> Page:
    page, created = Page.objects.get_or_create(
        page_key="homepage",
        defaults={
            "title": "Startseite",
            "slug": "startseite",
            "page_type": Page.PageType.SYSTEM,
            "layout_preset": get_page_layout("homepage"),
            "status": PublishableStatus.PUBLISHED,
            "visibility": Visibility.PUBLIC,
            "published_at": timezone.now(),
            "created_by": actor,
            "last_edited_by": actor,
            "meta_title": "AV Froburger - Akademische Verbindung Uni Basel",
            "meta_description": (
                "AV Froburger: akademische Verbindung an der Universitaet Basel mit "
                "Tradition, Freundschaft und aktuellem Verbindungsleben."
            ),
        },
    )
    if created:
        recap_image = import_static_image_to_media(
            relative_static_path="Bilder/DSC01173-web.jpg",
            title="Homepage Rueckblick",
            alt_text="Froburger in Couleur am Froburgerwochenende 2026",
            uploaded_by=actor,
        )
        PageSection.objects.bulk_create(
            [
                PageSection(
                    page=page,
                    block_type=PageSection.BlockType.TEXT,
                    layout_preset=get_block_layout("text_only"),
                    position=10,
                    anchor_id="homepage-copy",
                    eyebrow="AV Froburger",
                    heading="Was uns verbindet",
                    body=(
                        "Seit Generationen schaffen wir einen Ort fuer Freundschaft, "
                        "persoenliche Entwicklung und gelebte Verbindungskultur."
                    ),
                    is_active=True,
                    options={},
                ),
                PageSection(
                    page=page,
                    block_type=PageSection.BlockType.IMAGE_TEXT,
                    layout_preset=get_block_layout("image_right"),
                    position=20,
                    anchor_id="homepage-recap",
                    eyebrow="Rueckblick",
                    heading="Spargelfahrt 2026: gemeinsam unterwegs.",
                    body=(
                        "Am 25. April fuehrte die Spargelfahrt vom internationalen "
                        "Busbahnhof Basel nach Efringen-Kirchen. Im Restaurant "
                        "Engemuehle trafen sich Aktivitas und Alt-Froburger bei Apero, "
                        "Spargelmenue, Cantus und einer Produktion der Fuxen."
                    ),
                    image=recap_image,
                    link_label="Bericht lesen",
                    link_url="/aktuelles/",
                    is_active=True,
                    options={},
                ),
                PageSection(
                    page=page,
                    block_type=PageSection.BlockType.CAROUSEL,
                    layout_preset=get_block_layout("carousel_slides"),
                    position=30,
                    anchor_id="homepage-carousel",
                    eyebrow="Bilder",
                    heading="Startseitenkarussell",
                    body="",
                    is_active=False,
                    options={"autoplay": False},
                ),
            ]
        )
        page.create_revision(actor=actor, reason="Initiale Startseitenkonfiguration erstellt")
    return page


def sync_inline_children(formset, parent, actor=None):
    instances = formset.save(commit=False)
    for deleted in formset.deleted_objects:
        deleted.delete()
    for instance in instances:
        if hasattr(instance, "post_id"):
            instance.post = parent
        elif hasattr(instance, "page_id"):
            instance.page = parent
        elif hasattr(instance, "carousel_id"):
            instance.carousel = parent
        instance.save()
    formset.save_m2m()


@transaction.atomic
def save_post_editor(*, form, formset, actor):
    post = form.save(commit=False)
    is_new = post.pk is None
    post.last_edited_by = actor
    if is_new:
        post.created_by = actor
        post.author = actor
    post.version_number = (post.version_number or 1) + (0 if is_new else 1)
    post.full_clean()
    post.save()
    sync_inline_children(formset, post, actor=actor)
    revision = post.create_revision(actor=actor, reason=form.cleaned_data.get("change_reason", ""))
    action = "content.post.created" if is_new else "content.post.updated"
    detail = f"status={post.status}"
    record_audit_event(
        action=action,
        actor=actor,
        object_type="Post",
        object_id=str(post.pk),
        result=AuditLogEntry.Result.SUCCESS,
        detail=detail[:255],
    )
    if post.is_homepage_pinned:
        record_audit_event(
            action="content.post.pinned",
            actor=actor,
            object_type="Post",
            object_id=str(post.pk),
            result=AuditLogEntry.Result.SUCCESS,
            detail=f"pin_priority={post.pin_priority}"[:255],
        )
    return post, revision


@transaction.atomic
def save_carousel_editor(*, form, formset, actor):
    carousel = form.save(commit=False)
    is_new = carousel.pk is None
    carousel.last_edited_by = actor
    if is_new:
        carousel.created_by = actor
    carousel.full_clean()
    carousel.save()
    sync_inline_children(formset, carousel, actor=actor)
    revision = carousel.create_revision(
        actor=actor,
        reason=form.cleaned_data.get("change_reason", ""),
    )
    record_audit_event(
        action="content.carousel.created" if is_new else "content.carousel.updated",
        actor=actor,
        object_type="Carousel",
        object_id=str(carousel.pk),
        result=AuditLogEntry.Result.SUCCESS,
        detail=f"active={carousel.is_active}"[:255],
    )
    return carousel, revision


@transaction.atomic
def save_homepage_editor(*, form, formset, actor):
    page = form.save(commit=False)
    page.last_edited_by = actor
    page.version_number = (page.version_number or 1) + 1
    page.full_clean()
    page.save()
    sync_inline_children(formset, page, actor=actor)
    revision = page.create_revision(actor=actor, reason=form.cleaned_data.get("change_reason", ""))
    record_audit_event(
        action="content.homepage.updated",
        actor=actor,
        object_type="Page",
        object_id=str(page.pk),
        result=AuditLogEntry.Result.SUCCESS,
        detail=f"status={page.status}"[:255],
    )
    active_carousel_section = page.sections.filter(
        block_type=PageSection.BlockType.CAROUSEL,
        is_active=True,
        carousel__isnull=False,
    ).first()
    if active_carousel_section:
        record_audit_event(
            action="content.homepage.carousel.selected",
            actor=actor,
            object_type="Page",
            object_id=str(page.pk),
            result=AuditLogEntry.Result.SUCCESS,
            detail=f"carousel={active_carousel_section.carousel_id}"[:255],
        )
    return page, revision


@transaction.atomic
def save_page_editor(*, form, formset, actor):
    page = form.save(commit=False)
    is_new = page.pk is None
    page.last_edited_by = actor
    if is_new:
        page.created_by = actor
    page.version_number = (page.version_number or 1) + (0 if is_new else 1)
    page.full_clean()
    page.save()
    sync_inline_children(formset, page, actor=actor)
    revision = page.create_revision(actor=actor, reason=form.cleaned_data.get("change_reason", ""))
    record_audit_event(
        action="content.page.created" if is_new else "content.page.updated",
        actor=actor,
        object_type="Page",
        object_id=str(page.pk),
        result=AuditLogEntry.Result.SUCCESS,
        detail=f"status={page.status}"[:255],
    )
    return page, revision


@transaction.atomic
def update_post_status(*, post: Post, actor, status: str, reason: str = ""):
    post.status = status
    if status == PublishableStatus.PUBLISHED:
        post.published_at = post.published_at or timezone.now()
    if status != PublishableStatus.SCHEDULED:
        post.scheduled_for = None
    if status == PublishableStatus.ARCHIVED:
        post.visibility = Visibility.PRIVATE
    if status in {PublishableStatus.DRAFT, PublishableStatus.REVIEW, PublishableStatus.ARCHIVED}:
        post.is_homepage_pinned = False
    post.last_edited_by = actor
    post.version_number += 1
    post.full_clean()
    post.save()
    revision = post.create_revision(actor=actor, reason=reason)
    audit_action_map = {
        PublishableStatus.PUBLISHED: "content.post.published",
        PublishableStatus.DRAFT: "content.post.withdrawn",
        PublishableStatus.ARCHIVED: "content.post.archived",
        PublishableStatus.SCHEDULED: "content.post.scheduled",
        PublishableStatus.REVIEW: "content.post.review",
    }
    record_audit_event(
        action=audit_action_map[status],
        actor=actor,
        object_type="Post",
        object_id=str(post.pk),
        result=AuditLogEntry.Result.SUCCESS,
        detail=f"status={status}"[:255],
    )
    return revision


@transaction.atomic
def update_page_status(*, page: Page, actor, status: str, reason: str = ""):
    page.status = status
    if status == PublishableStatus.PUBLISHED:
        page.published_at = page.published_at or timezone.now()
    if status != PublishableStatus.SCHEDULED:
        page.scheduled_for = None
    if status == PublishableStatus.ARCHIVED:
        page.visibility = Visibility.PRIVATE
    page.last_edited_by = actor
    page.version_number += 1
    page.full_clean()
    page.save()
    revision = page.create_revision(actor=actor, reason=reason)
    audit_action_map = {
        PublishableStatus.PUBLISHED: "content.page.published",
        PublishableStatus.DRAFT: "content.page.withdrawn",
        PublishableStatus.ARCHIVED: "content.page.archived",
        PublishableStatus.SCHEDULED: "content.page.scheduled",
        PublishableStatus.REVIEW: "content.page.review",
    }
    record_audit_event(
        action=audit_action_map[status],
        actor=actor,
        object_type="Page",
        object_id=str(page.pk),
        result=AuditLogEntry.Result.SUCCESS,
        detail=f"status={status}"[:255],
    )
    return revision


@transaction.atomic
def restore_post_revision(*, post: Post, revision, actor):
    post.create_revision(actor=actor, reason="Sicherung vor Wiederherstellung")
    _apply_post_snapshot(post, revision.snapshot, actor=actor)
    restored = post.create_revision(
        actor=actor,
        reason=f"Wiederhergestellt aus Revision {revision.revision_number}",
    )
    record_audit_event(
        action="content.post.restored",
        actor=actor,
        object_type="Post",
        object_id=str(post.pk),
        result=AuditLogEntry.Result.SUCCESS,
        detail=f"revision={revision.revision_number}"[:255],
    )
    return restored


@transaction.atomic
def restore_page_revision(*, page: Page, revision, actor):
    page.create_revision(actor=actor, reason="Sicherung vor Wiederherstellung")
    _apply_page_snapshot(page, revision.snapshot, actor=actor)
    restored = page.create_revision(
        actor=actor,
        reason=f"Wiederhergestellt aus Revision {revision.revision_number}",
    )
    record_audit_event(
        action="content.page.restored",
        actor=actor,
        object_type="Page",
        object_id=str(page.pk),
        result=AuditLogEntry.Result.SUCCESS,
        detail=f"revision={revision.revision_number}"[:255],
    )
    return restored


@transaction.atomic
def restore_carousel_revision(*, carousel: Carousel, revision, actor):
    carousel.create_revision(actor=actor, reason="Sicherung vor Wiederherstellung")
    _apply_carousel_snapshot(carousel, revision.snapshot, actor=actor)
    restored = carousel.create_revision(
        actor=actor,
        reason=f"Wiederhergestellt aus Revision {revision.revision_number}",
    )
    record_audit_event(
        action="content.carousel.restored",
        actor=actor,
        object_type="Carousel",
        object_id=str(carousel.pk),
        result=AuditLogEntry.Result.SUCCESS,
        detail=f"revision={revision.revision_number}"[:255],
    )
    return restored


def build_post_preview(post: Post | None = None, snapshot: dict | None = None) -> Post:
    if snapshot is None and post is None:
        raise ValueError("build_post_preview braucht entweder einen Post oder einen Snapshot.")
    if snapshot is None:
        preview_post = post
        preview_post.preview_blocks = list(post.blocks.order_by("position", "pk"))
        return preview_post

    layout = get_post_layout(snapshot["layout_preset"])
    preview_post = Post(
        title=snapshot["title"],
        slug=snapshot["slug"],
        teaser=snapshot["teaser"],
        status=snapshot["status"],
        visibility=snapshot["visibility"],
        layout_preset=layout,
        published_at=_parse_snapshot_datetime(snapshot.get("published_at", "")),
        scheduled_for=_parse_snapshot_datetime(snapshot.get("scheduled_for", "")),
        meta_title=snapshot["meta_title"],
        meta_description=snapshot["meta_description"],
        cta_label=snapshot["cta_label"],
        cta_url=snapshot["cta_url"],
        pin_priority=snapshot["pin_priority"],
        is_homepage_pinned=snapshot["is_homepage_pinned"],
        event_date=_parse_snapshot_date(snapshot.get("event_date", "")),
        event_location=snapshot["event_location"],
        categories=snapshot["categories"],
    )
    preview_post.hero_image_id = snapshot["hero_image_id"]
    preview_post.og_image_id = snapshot["og_image_id"]
    preview_post.preview_blocks = []
    for block_data in snapshot["blocks"]:
        block = PostBlock(
            block_type=block_data["block_type"],
            layout_preset=get_block_layout(block_data["layout_preset"]),
            position=block_data["position"],
            is_active=block_data["is_active"],
            anchor_id=block_data["anchor_id"],
            eyebrow=block_data["eyebrow"],
            heading=block_data["heading"],
            body=block_data["body"],
            link_url=block_data["link_url"],
            link_label=block_data["link_label"],
            options=block_data["options"],
        )
        if block_data["image_id"]:
            block.image = MediaAsset.objects.filter(pk=block_data["image_id"]).first()
        if block_data["carousel_id"]:
            block.carousel = Carousel.objects.filter(pk=block_data["carousel_id"]).first()
        preview_post.preview_blocks.append(block)
    if snapshot["hero_image_id"]:
        preview_post.hero_image = MediaAsset.objects.filter(pk=snapshot["hero_image_id"]).first()
    if snapshot["og_image_id"]:
        preview_post.og_image = MediaAsset.objects.filter(pk=snapshot["og_image_id"]).first()
    return preview_post


def build_page_preview(page: Page | None = None, snapshot: dict | None = None) -> Page:
    if snapshot is None and page is None:
        raise ValueError("build_page_preview braucht entweder eine Seite oder einen Snapshot.")
    if snapshot is None:
        preview_page = page
        preview_page.preview_sections = list(page.sections.order_by("position", "pk"))
        return preview_page

    preview_page = Page(
        title=snapshot["title"],
        slug=snapshot["slug"],
        page_key=snapshot["page_key"],
        page_type=snapshot["page_type"],
        status=snapshot["status"],
        visibility=snapshot["visibility"],
        layout_preset=get_page_layout(snapshot["layout_preset"]),
        meta_title=snapshot["meta_title"],
        meta_description=snapshot["meta_description"],
        published_at=_parse_snapshot_datetime(snapshot.get("published_at", "")),
        scheduled_for=_parse_snapshot_datetime(snapshot.get("scheduled_for", "")),
    )
    preview_page.og_image_id = snapshot["og_image_id"]
    preview_page.preview_sections = []
    for section_data in snapshot["sections"]:
        section = PageSection(
            block_type=section_data["block_type"],
            layout_preset=get_block_layout(section_data["layout_preset"]),
            position=section_data["position"],
            is_active=section_data["is_active"],
            anchor_id=section_data["anchor_id"],
            eyebrow=section_data["eyebrow"],
            heading=section_data["heading"],
            body=section_data["body"],
            link_url=section_data["link_url"],
            link_label=section_data["link_label"],
            options=section_data["options"],
        )
        if section_data["image_id"]:
            section.image = MediaAsset.objects.filter(pk=section_data["image_id"]).first()
        if section_data["carousel_id"]:
            section.carousel = Carousel.objects.filter(pk=section_data["carousel_id"]).first()
        preview_page.preview_sections.append(section)
    if snapshot["og_image_id"]:
        preview_page.og_image = MediaAsset.objects.filter(pk=snapshot["og_image_id"]).first()
    return preview_page


def build_carousel_preview(
    carousel: Carousel | None = None, snapshot: dict | None = None
) -> Carousel:
    if snapshot is None and carousel is None:
        raise ValueError(
            "build_carousel_preview braucht entweder ein Karussell "
            "oder einen Snapshot."
        )
    if snapshot is None:
        preview_carousel = carousel
        preview_carousel.preview_items = list(carousel.items.order_by("position", "pk"))
        return preview_carousel

    preview_carousel = Carousel(
        name=snapshot["name"],
        title=snapshot["title"],
        description=snapshot["description"],
        usage_context=snapshot["usage_context"],
        is_active=snapshot["is_active"],
        visibility=snapshot["visibility"],
    )
    preview_carousel.preview_items = []
    for item_data in snapshot["items"]:
        item = CarouselItem(
            image_id=item_data["image_id"],
            position=item_data["position"],
            is_active=item_data["is_active"],
            heading=item_data["heading"],
            body=item_data["body"],
            link_url=item_data["link_url"],
            link_label=item_data["link_label"],
            starts_at=_parse_snapshot_datetime(item_data.get("starts_at", "")),
            ends_at=_parse_snapshot_datetime(item_data.get("ends_at", "")),
        )
        if item_data["image_id"]:
            item.image = MediaAsset.objects.filter(pk=item_data["image_id"]).first()
        preview_carousel.preview_items.append(item)
    return preview_carousel


def _apply_post_snapshot(post: Post, snapshot: dict, actor=None):
    post.title = snapshot["title"]
    post.slug = snapshot["slug"]
    post.teaser = snapshot["teaser"]
    post.status = snapshot["status"]
    post.visibility = snapshot["visibility"]
    post.published_at = _parse_snapshot_datetime(snapshot.get("published_at", ""))
    post.scheduled_for = _parse_snapshot_datetime(snapshot.get("scheduled_for", ""))
    post.layout_preset = get_post_layout(snapshot["layout_preset"])
    post.hero_image_id = snapshot["hero_image_id"]
    post.og_image_id = snapshot["og_image_id"]
    post.author_id = snapshot["author_id"]
    post.event_date = _parse_snapshot_date(snapshot.get("event_date", ""))
    post.event_location = snapshot["event_location"]
    post.cta_label = snapshot["cta_label"]
    post.cta_url = snapshot["cta_url"]
    post.categories = snapshot["categories"]
    post.meta_title = snapshot["meta_title"]
    post.meta_description = snapshot["meta_description"]
    post.is_homepage_pinned = snapshot["is_homepage_pinned"]
    post.pin_priority = snapshot["pin_priority"]
    post.last_edited_by = actor
    post.version_number += 1
    post.full_clean()
    post.save()
    post.blocks.all().delete()
    for block_data in snapshot["blocks"]:
        PostBlock.objects.create(
            post=post,
            block_type=block_data["block_type"],
            layout_preset=get_block_layout(block_data["layout_preset"]),
            position=block_data["position"],
            is_active=block_data["is_active"],
            anchor_id=block_data["anchor_id"],
            eyebrow=block_data["eyebrow"],
            heading=block_data["heading"],
            body=block_data["body"],
            image_id=block_data["image_id"],
            carousel_id=block_data["carousel_id"],
            link_url=block_data["link_url"],
            link_label=block_data["link_label"],
            options=block_data["options"],
        )


def _apply_page_snapshot(page: Page, snapshot: dict, actor=None):
    page.title = snapshot["title"]
    page.slug = snapshot["slug"]
    page.page_key = snapshot["page_key"]
    page.page_type = snapshot["page_type"]
    page.status = snapshot["status"]
    page.visibility = snapshot["visibility"]
    page.published_at = _parse_snapshot_datetime(snapshot.get("published_at", ""))
    page.scheduled_for = _parse_snapshot_datetime(snapshot.get("scheduled_for", ""))
    page.layout_preset = get_page_layout(snapshot["layout_preset"])
    page.meta_title = snapshot["meta_title"]
    page.meta_description = snapshot["meta_description"]
    page.og_image_id = snapshot["og_image_id"]
    page.last_edited_by = actor
    page.version_number += 1
    page.full_clean()
    page.save()
    page.sections.all().delete()
    for section_data in snapshot["sections"]:
        PageSection.objects.create(
            page=page,
            block_type=section_data["block_type"],
            layout_preset=get_block_layout(section_data["layout_preset"]),
            position=section_data["position"],
            is_active=section_data["is_active"],
            anchor_id=section_data["anchor_id"],
            eyebrow=section_data["eyebrow"],
            heading=section_data["heading"],
            body=section_data["body"],
            image_id=section_data["image_id"],
            carousel_id=section_data["carousel_id"],
            link_url=section_data["link_url"],
            link_label=section_data["link_label"],
            options=section_data["options"],
        )


def _apply_carousel_snapshot(carousel: Carousel, snapshot: dict, actor=None):
    carousel.name = snapshot["name"]
    carousel.title = snapshot["title"]
    carousel.description = snapshot["description"]
    carousel.usage_context = snapshot["usage_context"]
    carousel.is_active = snapshot["is_active"]
    carousel.visibility = snapshot["visibility"]
    carousel.last_edited_by = actor
    carousel.full_clean()
    carousel.save()
    carousel.items.all().delete()
    for item_data in snapshot["items"]:
        CarouselItem.objects.create(
            carousel=carousel,
            image_id=item_data["image_id"],
            position=item_data["position"],
            is_active=item_data["is_active"],
            heading=item_data["heading"],
            body=item_data["body"],
            link_url=item_data["link_url"],
            link_label=item_data["link_label"],
            starts_at=_parse_snapshot_datetime(item_data.get("starts_at", "")),
            ends_at=_parse_snapshot_datetime(item_data.get("ends_at", "")),
        )


def ensure_post_placeholder_image(actor=None) -> MediaAsset:
    existing = MediaAsset.objects.filter(title="CMS Platzhalterbild").first()
    if existing:
        return existing
    asset = MediaAsset(
        title="CMS Platzhalterbild",
        alt_text="Platzhalterbild fuer CMS-Inhalte",
        uploaded_by=actor,
        status=MediaAsset.PublicationStatus.PUBLISHED,
        visibility=MediaAsset.Visibility.PUBLIC,
    )
    png_bytes = (
        b"\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01"
        b"\x08\x02\x00\x00\x00\x90wS\xde\x00\x00\x00\x0cIDATx\x9cc`\xf8\xcf\x00"
        b"\x00\x02\x05\x01\x02\x9a\x9d\x8f\x18\x00\x00\x00\x00IEND\xaeB`\x82"
    )
    asset.file.save("cms-placeholder.png", ContentFile(png_bytes), save=False)
    asset.save()
    return asset
