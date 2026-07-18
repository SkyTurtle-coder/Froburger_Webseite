from datetime import timedelta

import pytest
from django.contrib.auth.models import Group
from django.core.exceptions import ValidationError
from django.core.files.uploadedfile import SimpleUploadedFile
from django.core.management import call_command
from django.utils import timezone

from apps.content.forms import SimplifiedPostForm
from apps.content.models import (
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
from apps.content.rich_text import sanitize_post_body_html
from apps.content.services import feature_post_on_homepage, save_post_editor
from apps.media_library.models import MediaAsset

pytestmark = pytest.mark.django_db


def create_media_asset(*, user, image_upload_factory, title="Bild", alt_text="Bildbeschreibung"):
    asset = MediaAsset(
        title=title,
        file=image_upload_factory(),
        alt_text=alt_text,
        uploaded_by=user,
        status=MediaAsset.PublicationStatus.PUBLISHED,
    )
    asset.save()
    return asset


def test_seeded_layout_presets_are_available():
    assert LayoutPreset.objects.filter(scope=LayoutPreset.Scope.PAGE, key="homepage").exists()
    assert LayoutPreset.objects.filter(
        scope=LayoutPreset.Scope.POST, key="standard_article"
    ).exists()
    assert LayoutPreset.objects.filter(
        scope=LayoutPreset.Scope.BLOCK, key="text_only"
    ).exists()

    post_layout = LayoutPreset.objects.get(scope=LayoutPreset.Scope.POST, key="standard_article")
    assert post_layout.template_name == "content/posts/layouts/standard_article.html"


def test_media_asset_populates_metadata_and_requires_alt_text(
    settings, tmp_path, user_factory, image_upload_factory
):
    settings.MEDIA_ROOT = tmp_path / "test-media"
    user = user_factory()

    asset = create_media_asset(user=user, image_upload_factory=image_upload_factory)

    assert asset.mime_type == "image/png"
    assert asset.width == 40
    assert asset.height == 30
    assert asset.file_size > 0

    invalid_asset = MediaAsset(
        title="Ohne Alt",
        file=image_upload_factory(filename="missing-alt.png"),
        uploaded_by=user,
    )

    with pytest.raises(ValidationError):
        invalid_asset.save()


def test_media_asset_rejects_svg_upload(settings, tmp_path, user_factory):
    settings.MEDIA_ROOT = tmp_path / "test-media"
    user = user_factory()
    asset = MediaAsset(
        title="Logo",
        file=SimpleUploadedFile(
            "logo.svg",
            b"<svg xmlns='http://www.w3.org/2000/svg'></svg>",
            content_type="image/svg+xml",
        ),
        uploaded_by=user,
        alt_text="Logo",
    )

    with pytest.raises(ValidationError):
        asset.save()


def test_post_public_querysets_respect_status_schedule_and_visibility(
    settings, tmp_path, user_factory, image_upload_factory
):
    settings.MEDIA_ROOT = tmp_path / "test-media"
    user = user_factory()
    hero = create_media_asset(user=user, image_upload_factory=image_upload_factory, title="Hero")
    post_layout = LayoutPreset.objects.get(scope=LayoutPreset.Scope.POST, key="standard_article")
    now = timezone.now()

    published = Post.objects.create(
        title="Publiziert",
        slug="publiziert",
        teaser="Schon da",
        layout_preset=post_layout,
        hero_image=hero,
        status=PublishableStatus.PUBLISHED,
        published_at=now - timedelta(days=1),
    )
    Post.objects.create(
        title="Entwurf",
        slug="entwurf",
        teaser="Noch nicht sichtbar",
        layout_preset=post_layout,
        hero_image=hero,
        status=PublishableStatus.DRAFT,
    )
    scheduled_past = Post.objects.create(
        title="Termin erreicht",
        slug="termin-erreicht",
        teaser="Soll sichtbar sein",
        layout_preset=post_layout,
        hero_image=hero,
        status=PublishableStatus.SCHEDULED,
        scheduled_for=now - timedelta(hours=1),
    )
    Post.objects.create(
        title="Termin zukuenftig",
        slug="termin-zukuenftig",
        teaser="Noch verborgen",
        layout_preset=post_layout,
        hero_image=hero,
        status=PublishableStatus.SCHEDULED,
        scheduled_for=now + timedelta(hours=2),
    )
    members_only = Post.objects.create(
        title="Intern",
        slug="intern",
        teaser="Nur fuer Mitglieder",
        layout_preset=post_layout,
        hero_image=hero,
        status=PublishableStatus.PUBLISHED,
        visibility=Visibility.MEMBERS,
        published_at=now - timedelta(days=1),
    )

    assert list(Post.objects.public().order_by("slug")) == [published, scheduled_past]
    assert set(Post.objects.visible_to(user)) == {published, scheduled_past, members_only}


def test_homepage_pin_limit_is_enforced(settings, tmp_path, user_factory, image_upload_factory):
    settings.MEDIA_ROOT = tmp_path / "test-media"
    user = user_factory()
    hero = create_media_asset(user=user, image_upload_factory=image_upload_factory, title="Hero")
    post_layout = LayoutPreset.objects.get(scope=LayoutPreset.Scope.POST, key="standard_article")
    now = timezone.now()

    for index in range(1, 4):
        Post.objects.create(
            title=f"Post {index}",
            slug=f"post-{index}",
            teaser="Pinned",
            layout_preset=post_layout,
            hero_image=hero,
            status=PublishableStatus.PUBLISHED,
            published_at=now,
            is_homepage_pinned=True,
            pin_priority=index,
        )

    fourth = Post(
        title="Post 4",
        slug="post-4",
        teaser="Pinned",
        layout_preset=post_layout,
        hero_image=hero,
        status=PublishableStatus.PUBLISHED,
        published_at=now,
        is_homepage_pinned=True,
        pin_priority=4,
    )

    with pytest.raises(ValidationError):
        fourth.full_clean()


def test_page_section_validates_layout_compatibility(
    settings, tmp_path, user_factory, image_upload_factory
):
    settings.MEDIA_ROOT = tmp_path / "test-media"
    user = user_factory()
    hero = create_media_asset(user=user, image_upload_factory=image_upload_factory, title="Hero")
    page_layout = LayoutPreset.objects.get(scope=LayoutPreset.Scope.PAGE, key="standard_page")
    text_only = LayoutPreset.objects.get(scope=LayoutPreset.Scope.BLOCK, key="text_only")
    gallery_grid = LayoutPreset.objects.get(scope=LayoutPreset.Scope.BLOCK, key="gallery_grid")

    page = Page.objects.create(
        title="Ueber uns",
        slug="ueber-uns-redaktionell",
        page_key="about-redaktionell",
        layout_preset=page_layout,
        status=PublishableStatus.DRAFT,
        created_by=user,
    )

    valid = PageSection(
        page=page,
        block_type=PageSection.BlockType.TEXT,
        layout_preset=text_only,
        body="Ein valider Textblock.",
        options={},
    )
    valid.full_clean()

    invalid = PageSection(
        page=page,
        block_type=PageSection.BlockType.TEXT,
        layout_preset=gallery_grid,
        body="Ein ungueltiger Textblock.",
        options={},
        image=hero,
    )

    with pytest.raises(ValidationError):
        invalid.full_clean()


def test_cta_blocks_allow_relative_and_contact_links(user_factory):
    user = user_factory()
    page_layout = LayoutPreset.objects.get(scope=LayoutPreset.Scope.PAGE, key="standard_page")
    cta_layout = LayoutPreset.objects.get(scope=LayoutPreset.Scope.BLOCK, key="cta_primary")
    page = Page.objects.create(
        title="Mitmachen",
        slug="mitmachen",
        page_key="mitmachen",
        layout_preset=page_layout,
        status=PublishableStatus.DRAFT,
        created_by=user,
    )

    relative_cta = PageSection(
        page=page,
        block_type=PageSection.BlockType.CTA,
        layout_preset=cta_layout,
        heading="Mehr erfahren",
        link_label="Mitglied werden",
        link_url="/mitglied-werden/",
        options={},
    )
    relative_cta.full_clean()

    mailto_cta = PageSection(
        page=page,
        block_type=PageSection.BlockType.CTA,
        layout_preset=cta_layout,
        heading="Kontakt",
        link_label="E-Mail senden",
        link_url="mailto:info@example.invalid",
        options={},
    )
    mailto_cta.full_clean()

    invalid_cta = PageSection(
        page=page,
        block_type=PageSection.BlockType.CTA,
        layout_preset=cta_layout,
        heading="Ungueltig",
        link_label="Fehler",
        link_url="mitglied-werden",
        options={},
    )

    with pytest.raises(ValidationError):
        invalid_cta.full_clean()


def test_simplified_post_slug_and_seo_are_generated(
    settings,
    tmp_path,
    user_factory,
):
    settings.MEDIA_ROOT = tmp_path / "test-media"
    author = user_factory()
    post_layout = LayoutPreset.objects.get(scope=LayoutPreset.Scope.POST, key="simple_classic")
    Post.objects.create(
        title="Sommeranlass am Rhein",
        slug="sommeranlass-am-rhein",
        teaser="Kurzbeschreibung fuer den ersten Beitrag.",
        body_html="<p>Ein erster Beitrag.</p>",
        layout_preset=post_layout,
        status=PublishableStatus.DRAFT,
        visibility=Visibility.PUBLIC,
        author=author,
        created_by=author,
        last_edited_by=author,
        meta_title="Sommeranlass am Rhein",
        meta_description="Kurzbeschreibung fuer den ersten Beitrag.",
    )

    form = SimplifiedPostForm(
        data={
            "event_date": "2026-07-18",
            "title": "Sommeranlass am Rhein",
            "teaser": "Noch eine Kurzbeschreibung.",
            "body_html": "<p>Ein zweiter Beitrag.</p>",
            "version_number": "1",
            "change_reason": "",
        },
        instance=Post(),
        user=author,
        layout_key="simple_classic",
    )
    assert form.is_valid(), form.errors

    post, revision = save_post_editor(form=form, actor=author)

    assert post.slug == "sommeranlass-am-rhein-2"
    assert post.meta_title == "Sommeranlass am Rhein"
    assert post.meta_description == "Noch eine Kurzbeschreibung."
    assert post.author == author
    assert revision.snapshot["body_html"] == "<p>Ein zweiter Beitrag.</p>"


def test_homepage_feature_allows_one_current_and_one_upcoming_pin(
    settings,
    tmp_path,
    user_factory,
):
    settings.MEDIA_ROOT = tmp_path / "test-media"
    author = user_factory()
    post_layout = LayoutPreset.objects.get(scope=LayoutPreset.Scope.POST, key="simple_classic")
    current = Post.objects.create(
        title="Aktuell sichtbar",
        slug="aktuell-sichtbar",
        teaser="Bereits auf der Startseite.",
        body_html="<p>Text</p>",
        layout_preset=post_layout,
        status=PublishableStatus.PUBLISHED,
        visibility=Visibility.PUBLIC,
        published_at=timezone.now() - timedelta(hours=1),
        author=author,
        created_by=author,
        last_edited_by=author,
        is_homepage_pinned=True,
        pin_priority=10,
        meta_title="Aktuell sichtbar",
        meta_description="Bereits auf der Startseite.",
    )
    upcoming = Post.objects.create(
        title="Spaeter sichtbar",
        slug="spaeter-sichtbar",
        teaser="Soll spaeter uebernehmen.",
        body_html="<p>Text</p>",
        layout_preset=post_layout,
        status=PublishableStatus.SCHEDULED,
        visibility=Visibility.PUBLIC,
        scheduled_for=timezone.now() + timedelta(days=1),
        author=author,
        created_by=author,
        last_edited_by=author,
        is_homepage_pinned=True,
        pin_priority=11,
        meta_title="Spaeter sichtbar",
        meta_description="Soll spaeter uebernehmen.",
    )

    current.full_clean()
    current.save()
    upcoming.is_homepage_pinned = False
    upcoming.pin_priority = 0
    upcoming.save(update_fields=["is_homepage_pinned", "pin_priority", "updated_at"])

    feature_post_on_homepage(post=upcoming, actor=author)
    current.refresh_from_db()
    upcoming.refresh_from_db()

    assert current.is_homepage_pinned is True
    assert upcoming.is_homepage_pinned is True
    assert upcoming.pin_priority > current.pin_priority
    assert Post.objects.filter(is_homepage_pinned=True).count() == 2


def test_simplified_body_html_accepts_safe_markup_and_rejects_unsafe_links(
    settings,
    tmp_path,
):
    settings.MEDIA_ROOT = tmp_path / "test-media"
    cleaned = sanitize_post_body_html(
        "<p><strong>Fett</strong></p>"
        "<p><a href=\"/mitglied-werden/\">Intern</a></p>"
        "<p><a href=\"mailto:test@example.invalid\">Mail</a></p>"
        "<p><a href=\"javascript:alert(1)\" onclick=\"alert(1)\">Boese</a></p>"
        "<script>alert(1)</script>"
        "<iframe src=\"https://example.invalid/embed\"></iframe>"
    )

    assert "<strong>Fett</strong>" in cleaned
    assert 'href="/mitglied-werden/"' in cleaned
    assert 'href="mailto:test@example.invalid"' in cleaned
    assert "javascript:" not in cleaned
    assert "onclick" not in cleaned
    assert "<script" not in cleaned
    assert "<iframe" not in cleaned


def test_post_revision_snapshots_include_blocks(
    settings, tmp_path, user_factory, image_upload_factory
):
    settings.MEDIA_ROOT = tmp_path / "test-media"
    user = user_factory()
    hero = create_media_asset(user=user, image_upload_factory=image_upload_factory, title="Hero")
    post_layout = LayoutPreset.objects.get(scope=LayoutPreset.Scope.POST, key="standard_article")
    block_layout = LayoutPreset.objects.get(scope=LayoutPreset.Scope.BLOCK, key="text_only")
    post = Post.objects.create(
        title="Rueckblick",
        slug="rueckblick",
        teaser="Kurzfassung",
        layout_preset=post_layout,
        hero_image=hero,
        status=PublishableStatus.PUBLISHED,
        published_at=timezone.now(),
        created_by=user,
    )
    PostBlock.objects.create(
        post=post,
        block_type=PostBlock.BlockType.TEXT,
        layout_preset=block_layout,
        body="Ein erster Absatz.",
    )

    revision = post.create_revision(actor=user, reason="Erste Fassung")

    assert revision.revision_number == 1
    assert revision.snapshot["title"] == "Rueckblick"
    assert revision.snapshot["body_html"] == ""
    assert revision.snapshot["blocks"][0]["body"] == "Ein erster Absatz."


def test_carousel_revision_snapshots_include_items(
    settings, tmp_path, user_factory, image_upload_factory
):
    settings.MEDIA_ROOT = tmp_path / "test-media"
    user = user_factory()
    image = create_media_asset(user=user, image_upload_factory=image_upload_factory, title="Slide")
    carousel = Carousel.objects.create(name="homepage-hero", title="Hero", created_by=user)
    CarouselItem.objects.create(
        carousel=carousel,
        image=image,
        heading="Slide 1",
        body="Kurztext",
        position=1,
    )

    revision = carousel.create_revision(actor=user, reason="Initial")

    assert revision.snapshot["name"] == "homepage-hero"
    assert revision.snapshot["items"][0]["heading"] == "Slide 1"


def test_bootstrap_roles_assigns_web_aktuar_cms_permissions():
    call_command("bootstrap_roles")

    event_verantwortlich = Group.objects.get(name="event_verantwortlich")
    web_aktuar = Group.objects.get(name="web_aktuar")
    member_admin = Group.objects.get(name="member_admin")

    assert web_aktuar.permissions.filter(
        content_type__app_label="content",
        codename="publish_post",
    ).exists()
    assert web_aktuar.permissions.filter(
        content_type__app_label="media_library",
        codename="publish_mediaasset",
    ).exists()
    assert web_aktuar.permissions.filter(
        content_type__app_label="events",
        codename="publish_event",
    ).exists()
    assert event_verantwortlich.permissions.filter(
        content_type__app_label="events",
        codename="schedule_event",
    ).exists()
    assert not member_admin.permissions.filter(
        content_type__app_label="content",
        codename="publish_post",
    ).exists()
