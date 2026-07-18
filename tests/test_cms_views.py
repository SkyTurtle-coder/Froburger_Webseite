from datetime import timedelta

import pytest
from django.contrib.auth.models import Group, Permission
from django.core.management import call_command
from django.urls import reverse
from django.utils import timezone

from apps.audit.models import AuditLogEntry
from apps.content.models import (
    LayoutPreset,
    Page,
    PageSection,
    Post,
    PostBlock,
    PublishableStatus,
    Visibility,
)
from apps.media_library.models import MediaAsset

pytestmark = pytest.mark.django_db


def create_media_asset(*, user, image_upload_factory, title="Bild", alt_text="Bildbeschreibung"):
    asset = MediaAsset(
        title=title,
        file=image_upload_factory(),
        alt_text=alt_text,
        uploaded_by=user,
        status=MediaAsset.PublicationStatus.PUBLISHED,
        visibility=MediaAsset.Visibility.PUBLIC,
    )
    asset.save()
    return asset


@pytest.fixture
def role_user_factory(user_factory):
    call_command("bootstrap_roles")

    def factory(role_name=None):
        user = user_factory()
        if role_name:
            user.groups.add(Group.objects.get(name=role_name))
        return user

    return factory


@pytest.fixture
def cms_post(settings, tmp_path, role_user_factory, image_upload_factory):
    settings.MEDIA_ROOT = tmp_path / "test-media"
    editor = role_user_factory("web_aktuar")
    hero = create_media_asset(user=editor, image_upload_factory=image_upload_factory, title="Hero")
    post_layout = LayoutPreset.objects.get(scope=LayoutPreset.Scope.POST, key="standard_article")
    block_layout = LayoutPreset.objects.get(scope=LayoutPreset.Scope.BLOCK, key="text_only")
    post = Post.objects.create(
        title="CMS Testbeitrag",
        slug="cms-testbeitrag",
        teaser="Ein Teaser aus dem CMS.",
        layout_preset=post_layout,
        hero_image=hero,
        status=PublishableStatus.DRAFT,
        visibility=Visibility.PUBLIC,
        created_by=editor,
        last_edited_by=editor,
    )
    PostBlock.objects.create(
        post=post,
        block_type=PostBlock.BlockType.TEXT,
        layout_preset=block_layout,
        body="Ein erster Textblock.",
        position=10,
    )
    post.create_revision(actor=editor, reason="Initial")
    return post


@pytest.fixture
def cms_page(settings, tmp_path, role_user_factory):
    settings.MEDIA_ROOT = tmp_path / "test-media"
    editor = role_user_factory("web_aktuar")
    page_layout = LayoutPreset.objects.get(scope=LayoutPreset.Scope.PAGE, key="standard_page")
    block_layout = LayoutPreset.objects.get(scope=LayoutPreset.Scope.BLOCK, key="text_only")
    page = Page.objects.create(
        title="CMS Testseite",
        slug="cms-testseite",
        page_key="cms-testseite",
        page_type=Page.PageType.EDITORIAL,
        layout_preset=page_layout,
        status=PublishableStatus.DRAFT,
        visibility=Visibility.PUBLIC,
        created_by=editor,
        last_edited_by=editor,
    )
    PageSection.objects.create(
        page=page,
        block_type=PageSection.BlockType.TEXT,
        layout_preset=block_layout,
        body="Ein Seitenabschnitt aus dem CMS.",
        position=10,
    )
    page.create_revision(actor=editor, reason="Initial")
    return page


@pytest.mark.parametrize(
    ("route_name", "kwargs"),
    [
        ("cms:dashboard", {}),
        ("cms:post_list", {}),
        ("cms:page_list", {}),
        ("cms:post_create", {}),
        ("cms:media_list", {}),
        ("cms:carousel_list", {}),
        ("cms:homepage_edit", {}),
    ],
)


def test_cms_routes_require_expected_permissions(
    client,
    role_user_factory,
    route_name,
    kwargs,
    settings,
    tmp_path,
):
    settings.MEDIA_ROOT = tmp_path / "test-media"
    url = reverse(route_name, kwargs=kwargs)

    anonymous_response = client.get(url)

    assert anonymous_response.status_code == 302

    member = role_user_factory("member")
    client.force_login(member)
    assert client.get(url).status_code == 403

    member_admin = role_user_factory("member_admin")
    client.force_login(member_admin)
    assert client.get(url).status_code == 403

    web_aktuar = role_user_factory("web_aktuar")
    client.force_login(web_aktuar)
    assert client.get(url).status_code == 200

    system_admin = role_user_factory("system_admin")
    client.force_login(system_admin)
    assert client.get(url).status_code == 200


def test_cms_base_access_uses_central_view_post_permission(client, role_user_factory):
    url = reverse("cms:page_list")
    user = role_user_factory()
    user.user_permissions.add(
        Permission.objects.get(content_type__app_label="content", codename="view_page")
    )
    client.force_login(user)

    assert client.get(url).status_code == 403

    user.user_permissions.add(
        Permission.objects.get(content_type__app_label="content", codename="view_post")
    )
    refreshed_user = type(user).objects.get(pk=user.pk)
    client.force_login(refreshed_user)

    assert client.get(url).status_code == 200


def test_cms_navigation_links_back_to_internal_area(client, role_user_factory):
    user = role_user_factory("web_aktuar")
    client.force_login(user)

    response = client.get(reverse("cms:dashboard"))
    content = response.content.decode()

    assert response.status_code == 200
    assert "Zurueck zum internen Bereich" in content
    assert f'href="{reverse("accounts:home")}"' in content


def test_post_preview_requires_preview_permission(client, role_user_factory, cms_post):
    url = reverse("cms:post_preview", args=[cms_post.pk])

    assert client.get(url).status_code == 302

    member = role_user_factory("member")
    client.force_login(member)
    assert client.get(url).status_code == 403

    web_aktuar = role_user_factory("web_aktuar")
    client.force_login(web_aktuar)
    response = client.get(url)

    assert response.status_code == 200
    assert "Diese Vorschau ist nur intern sichtbar." in response.content.decode()


def test_media_upload_rejects_private_visibility_for_web_aktuar(
    client,
    settings,
    tmp_path,
    role_user_factory,
    image_upload_factory,
):
    settings.MEDIA_ROOT = tmp_path / "test-media"
    editor = role_user_factory("web_aktuar")
    client.force_login(editor)

    response = client.post(
        reverse("cms:media_create"),
        {
            "file": image_upload_factory(),
            "title": "Privates Medium",
            "alt_text": "Alt-Text",
            "is_decorative": "",
            "caption": "",
            "credit": "",
            "captured_on": "",
            "visibility": MediaAsset.Visibility.PRIVATE,
            "status": MediaAsset.PublicationStatus.PUBLISHED,
        },
    )

    assert response.status_code == 200
    assert "Private Medien duerfen nur von Systemadministratoren verwaltet werden." in (
        response.content.decode()
    )
    assert not MediaAsset.objects.filter(title="Privates Medium").exists()


def test_media_upload_creates_audit_entry_for_web_aktuar(
    client,
    settings,
    tmp_path,
    role_user_factory,
    image_upload_factory,
):
    settings.MEDIA_ROOT = tmp_path / "test-media"
    editor = role_user_factory("web_aktuar")
    client.force_login(editor)

    response = client.post(
        reverse("cms:media_create"),
        {
            "file": image_upload_factory(filename="upload.png"),
            "title": "Neues Medium",
            "alt_text": "Ein gueltiger Alt-Text",
            "is_decorative": "",
            "caption": "",
            "credit": "",
            "captured_on": "",
            "visibility": MediaAsset.Visibility.PUBLIC,
            "status": MediaAsset.PublicationStatus.PUBLISHED,
        },
    )

    asset = MediaAsset.objects.get(title="Neues Medium")

    assert response.status_code == 302
    assert response.url == reverse("cms:media_edit", args=[asset.pk])
    assert AuditLogEntry.objects.filter(
        action="media.asset.created",
        object_type="MediaAsset",
        object_id=str(asset.pk),
    ).exists()


def test_post_revision_restore_creates_new_revision_and_restores_content(
    client,
    settings,
    tmp_path,
    role_user_factory,
    image_upload_factory,
):
    settings.MEDIA_ROOT = tmp_path / "test-media"
    editor = role_user_factory("web_aktuar")
    client.force_login(editor)
    hero = create_media_asset(user=editor, image_upload_factory=image_upload_factory, title="Hero")
    post_layout = LayoutPreset.objects.get(scope=LayoutPreset.Scope.POST, key="standard_article")
    block_layout = LayoutPreset.objects.get(scope=LayoutPreset.Scope.BLOCK, key="text_only")

    post = Post.objects.create(
        title="Erste Fassung",
        slug="erste-fassung",
        teaser="Teaser",
        layout_preset=post_layout,
        hero_image=hero,
        status=PublishableStatus.PUBLISHED,
        visibility=Visibility.PUBLIC,
        published_at=timezone.now() - timedelta(days=1),
        created_by=editor,
        last_edited_by=editor,
    )
    PostBlock.objects.create(
        post=post,
        block_type=PostBlock.BlockType.TEXT,
        layout_preset=block_layout,
        body="Version 1",
        position=10,
    )
    first_revision = post.create_revision(actor=editor, reason="Version 1")

    post.title = "Zweite Fassung"
    post.version_number += 1
    post.save(update_fields=["title", "version_number", "updated_at"])
    post.blocks.all().delete()
    PostBlock.objects.create(
        post=post,
        block_type=PostBlock.BlockType.TEXT,
        layout_preset=block_layout,
        body="Version 2",
        position=10,
    )
    post.create_revision(actor=editor, reason="Version 2")

    response = client.post(reverse("cms:post_revision_restore", args=[post.pk, first_revision.pk]))

    post.refresh_from_db()

    assert response.status_code == 302
    assert post.title == "Erste Fassung"
    assert post.blocks.get().body == "Version 1"
    assert post.revisions.count() == 4


def test_homepage_preview_requires_permission(client, role_user_factory, settings, tmp_path):
    settings.MEDIA_ROOT = tmp_path / "test-media"
    url = reverse("cms:homepage_preview")

    assert client.get(url).status_code == 302

    member = role_user_factory("member")
    client.force_login(member)
    assert client.get(url).status_code == 403

    editor = role_user_factory("web_aktuar")
    client.force_login(editor)
    response = client.get(url)

    assert response.status_code == 200
    assert "Vorschau Startseite" in response.content.decode()


def test_page_preview_requires_preview_permission(client, role_user_factory, cms_page):
    url = reverse("cms:page_preview", args=[cms_page.pk])

    assert client.get(url).status_code == 302

    member = role_user_factory("member")
    client.force_login(member)
    assert client.get(url).status_code == 403

    web_aktuar = role_user_factory("web_aktuar")
    client.force_login(web_aktuar)
    response = client.get(url)

    assert response.status_code == 200
    assert "Ein Seitenabschnitt aus dem CMS." in response.content.decode()


def test_page_workflow_views_publish_and_withdraw_page(
    client,
    role_user_factory,
    cms_page,
):
    editor = role_user_factory("web_aktuar")
    client.force_login(editor)

    publish_response = client.post(reverse("cms:page_publish", args=[cms_page.pk]))
    cms_page.refresh_from_db()
    assert publish_response.status_code == 302
    assert cms_page.status == PublishableStatus.PUBLISHED

    withdraw_response = client.post(reverse("cms:page_withdraw", args=[cms_page.pk]))
    cms_page.refresh_from_db()
    assert withdraw_response.status_code == 302
    assert cms_page.status == PublishableStatus.DRAFT
    assert cms_page.revisions.count() == 3


def test_page_revision_restore_creates_new_revision_and_restores_content(
    client,
    role_user_factory,
    cms_page,
):
    editor = role_user_factory("web_aktuar")
    client.force_login(editor)
    first_revision = cms_page.revisions.get(revision_number=1)

    cms_page.title = "CMS Testseite Aktualisiert"
    cms_page.version_number += 1
    cms_page.save(update_fields=["title", "version_number", "updated_at"])
    cms_page.sections.all().delete()
    block_layout = LayoutPreset.objects.get(scope=LayoutPreset.Scope.BLOCK, key="text_only")
    PageSection.objects.create(
        page=cms_page,
        block_type=PageSection.BlockType.TEXT,
        layout_preset=block_layout,
        body="Neue Seitenfassung.",
        position=10,
    )
    cms_page.create_revision(actor=editor, reason="Version 2")

    response = client.post(
        reverse("cms:page_revision_restore", args=[cms_page.pk, first_revision.pk])
    )

    cms_page.refresh_from_db()

    assert response.status_code == 302
    assert cms_page.title == "CMS Testseite"
    assert cms_page.sections.get().body == "Ein Seitenabschnitt aus dem CMS."
    assert cms_page.revisions.count() == 4
