from io import StringIO

import pytest
from django.contrib.auth.models import Group, Permission
from django.core.files.uploadedfile import SimpleUploadedFile
from django.core.management import call_command
from django.urls import reverse

from apps.accounts.models import User
from apps.members.models import MemberProfile

GIF_BYTES = (
    b"GIF89a\x01\x00\x01\x00\x80\x00\x00"
    b"\x00\x00\x00\xff\xff\xff!\xf9\x04"
    b"\x01\x00\x00\x00\x00,\x00\x00\x00"
    b"\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;"
)


def configure_profile_media(settings, tmp_path):
    settings.PUBLIC_MEDIA_ROOT = tmp_path / "public-media"
    settings.PRIVATE_MEDIA_ROOT = tmp_path / "private-media"
    settings.MEDIA_ROOT = settings.PUBLIC_MEDIA_ROOT


def gif_upload(name="avatar.gif"):
    return SimpleUploadedFile(name, GIF_BYTES, content_type="image/gif")


@pytest.mark.django_db
def test_member_profile_is_created_for_new_users():
    active_user = User.objects.create_user(
        email="member@example.invalid",
        password="Secret1234!",
        first_name="Philipp",
        last_name="Thuerlemann",
    )
    invited_user = User.objects.create_user(
        email="invitee@example.invalid",
        is_active=False,
    )

    assert active_user.member_profile.membership_status == MemberProfile.MembershipStatus.ACTIVE
    assert invited_user.member_profile.membership_status == MemberProfile.MembershipStatus.INVITED


@pytest.mark.django_db
def test_member_can_view_and_edit_own_profile(client, settings, tmp_path):
    configure_profile_media(settings, tmp_path)
    user = User.objects.create_user(
        email="member@example.invalid",
        password="Secret1234!",
        first_name="Philipp",
        last_name="Thuerlemann",
    )
    client.force_login(user)
    photo = gif_upload()

    detail_response = client.get(reverse("members:me"))
    edit_response = client.post(
        reverse("members:me_edit"),
        {
            "vulgar_name": "Newton",
            "directory_visibility": MemberProfile.DirectoryVisibility.PUBLIC,
            "phone_number": "+41 79 555 12 34",
            "profile_photo": photo,
        },
    )

    user.member_profile.refresh_from_db()

    assert detail_response.status_code == 200
    assert edit_response.status_code == 302
    assert edit_response.url == reverse("members:me")
    assert user.member_profile.vulgar_name == "Newton"
    assert user.member_profile.directory_visibility == MemberProfile.DirectoryVisibility.PUBLIC
    assert user.member_profile.phone_number == "+41 79 555 12 34"
    assert user.member_profile.profile_photo.name.startswith("member_photos/avatar")


@pytest.mark.django_db
def test_profile_photo_is_served_only_via_protected_endpoint(client, settings, tmp_path):
    configure_profile_media(settings, tmp_path)
    user = User.objects.create_user(
        email="member@example.invalid",
        password="Secret1234!",
        first_name="Philipp",
        last_name="Thuerlemann",
    )
    user.member_profile.profile_photo.save("avatar.gif", gif_upload(), save=True)
    client.force_login(user)

    response = client.get(reverse("members:profile_photo", args=[user.member_profile.pk]))

    assert response.status_code == 200
    assert "no-store" in response["Cache-Control"]
    assert b"".join(response.streaming_content).startswith(b"GIF89a")
    with pytest.raises(ValueError):
        _ = user.member_profile.profile_photo.url


@pytest.mark.django_db
def test_profile_photo_endpoint_respects_visibility_and_role_access(client, settings, tmp_path):
    configure_profile_media(settings, tmp_path)
    owner = User.objects.create_user(
        email="owner@example.invalid",
        password="Secret1234!",
        first_name="Owner",
        last_name="Member",
    )
    owner.member_profile.directory_visibility = MemberProfile.DirectoryVisibility.PRIVATE
    owner.member_profile.profile_photo.save("private.gif", gif_upload("private.gif"), save=True)
    owner.member_profile.save(update_fields=["directory_visibility", "updated_at"])

    other_user = User.objects.create_user(
        email="other@example.invalid",
        password="Secret1234!",
        first_name="Other",
        last_name="Member",
    )
    admin_user = User.objects.create_user(
        email="admin@example.invalid",
        password="Secret1234!",
    )
    admin_user.user_permissions.add(
        Permission.objects.get(content_type__app_label="members", codename="manage_member_profiles")
    )

    client.force_login(other_user)
    denied = client.get(reverse("members:profile_photo", args=[owner.member_profile.pk]))
    assert denied.status_code == 404

    client.force_login(owner)
    own = client.get(reverse("members:profile_photo", args=[owner.member_profile.pk]))
    assert own.status_code == 200

    client.force_login(admin_user)
    privileged = client.get(reverse("members:profile_photo", args=[owner.member_profile.pk]))
    assert privileged.status_code == 200


@pytest.mark.django_db
def test_profile_photo_endpoint_denies_inactive_users(client, settings, tmp_path):
    configure_profile_media(settings, tmp_path)
    owner = User.objects.create_user(
        email="owner@example.invalid",
        password="Secret1234!",
        first_name="Owner",
        last_name="Member",
    )
    owner.member_profile.directory_visibility = MemberProfile.DirectoryVisibility.PUBLIC
    owner.member_profile.profile_photo.save("public.gif", gif_upload("public.gif"), save=True)
    owner.member_profile.save(update_fields=["directory_visibility", "updated_at"])

    inactive_user = User.objects.create_user(
        email="inactive@example.invalid",
        password="Secret1234!",
        is_active=False,
    )
    client.force_login(inactive_user)

    response = client.get(reverse("members:profile_photo", args=[owner.member_profile.pk]))

    assert response.status_code == 302
    assert response.headers["Location"].startswith("/accounts/login/")


@pytest.mark.django_db
def test_profile_photo_migration_command_moves_legacy_public_files(settings, tmp_path):
    configure_profile_media(settings, tmp_path)
    user = User.objects.create_user(
        email="member@example.invalid",
        password="Secret1234!",
        first_name="Legacy",
        last_name="Member",
    )
    profile = user.member_profile
    legacy_name = "member_photos/legacy.gif"
    legacy_path = settings.PUBLIC_MEDIA_ROOT / legacy_name
    legacy_path.parent.mkdir(parents=True, exist_ok=True)
    legacy_path.write_bytes(GIF_BYTES)
    profile.profile_photo = legacy_name
    profile.save(update_fields=["profile_photo", "updated_at"])

    dry_run_output = StringIO()
    call_command(
        "migrate_profile_photos_to_private_storage",
        "--dry-run",
        stdout=dry_run_output,
    )
    assert "[dry-run] Wuerde migrieren" in dry_run_output.getvalue()
    assert legacy_path.exists()

    call_command("migrate_profile_photos_to_private_storage")

    assert (settings.PRIVATE_MEDIA_ROOT / legacy_name).exists()
    assert not legacy_path.exists()

    rerun_output = StringIO()
    call_command(
        "migrate_profile_photos_to_private_storage",
        "--dry-run",
        stdout=rerun_output,
    )
    assert "Bereits privat vorhanden" in rerun_output.getvalue()


@pytest.mark.django_db
def test_dashboard_shows_requested_internal_tiles(client):
    user = User.objects.create_user(
        email="member@example.invalid",
        password="Secret1234!",
        first_name="Philipp",
        last_name="Thuerlemann",
    )
    client.force_login(user)

    response = client.get(reverse("accounts:home"))
    content = response.content.decode()

    assert response.status_code == 200
    assert "Profil" in content
    assert "Mitgliederverzeichnis" in content
    assert "Medien" in content
    assert "Allgemeine Dokumente" in content
    assert "Sensible Dokumente" in content
    assert "Web-X CMS" not in content
    assert "Rolle Bursch" in content


@pytest.mark.django_db
def test_dashboard_shows_cms_tile_for_users_with_cms_access(client):
    call_command("bootstrap_roles")
    user = User.objects.create_user(
        email="webaktuar@example.invalid",
        password="Secret1234!",
        first_name="Web",
        last_name="Aktuar",
    )
    user.groups.add(Group.objects.get(name="web_aktuar"))
    client.force_login(user)

    response = client.get(reverse("accounts:home"))
    content = response.content.decode()

    assert response.status_code == 200
    assert "Web-X CMS" in content
    assert "CMS oeffnen" in content
    assert f'href="{reverse("cms:dashboard")}"' in content


@pytest.mark.django_db
def test_member_directory_is_available_to_authenticated_users(client):
    user = User.objects.create_user(
        email="member@example.invalid",
        password="Secret1234!",
        first_name="Philipp",
        last_name="Thuerlemann",
    )
    visible = User.objects.create_user(
        email="visible@example.invalid",
        password="Secret1234!",
        first_name="Reto",
        last_name="Fluetsch",
    )
    visible.member_profile.vulgar_name = "Parzival"
    visible.member_profile.directory_visibility = MemberProfile.DirectoryVisibility.MEMBERS
    visible.member_profile.save()
    hidden = User.objects.create_user(
        email="hidden@example.invalid",
        password="Secret1234!",
        first_name="Verdeckt",
        last_name="Mitglied",
    )
    hidden.member_profile.directory_visibility = MemberProfile.DirectoryVisibility.PRIVATE
    hidden.member_profile.save()
    client.force_login(user)

    response = client.get(reverse("members:directory"))
    content = response.content.decode()

    assert response.status_code == 200
    assert "Parzival" in content
    assert "visible@example.invalid" in content
    assert "hidden@example.invalid" not in content


@pytest.mark.django_db
def test_member_admin_views_require_permission(client):
    user = User.objects.create_user(
        email="member@example.invalid",
        password="Secret1234!",
    )
    client.force_login(user)

    response = client.get(reverse("members:admin_list"))

    assert response.status_code == 403


@pytest.mark.django_db
def test_sensitive_documents_require_bursch_role(client):
    user = User.objects.create_user(
        email="member@example.invalid",
        password="Secret1234!",
    )
    client.force_login(user)

    response = client.get(reverse("members:documents_sensitive"))

    assert response.status_code == 403


@pytest.mark.django_db
def test_sensitive_documents_are_available_with_bursch_role(client):
    privileged_user = User.objects.create_user(
        email="member-admin@example.invalid",
        password="Secret1234!",
    )
    privileged_user.member_profile.member_roles = [MemberProfile.MemberRole.BURSCH]
    privileged_user.member_profile.save(update_fields=["member_roles", "updated_at"])
    client.force_login(privileged_user)

    response = client.get(reverse("members:documents_sensitive"))

    assert response.status_code == 200
    assert "Besonders geschuetzte Unterlagen" in response.content.decode()


@pytest.mark.django_db
def test_member_admin_can_update_profile_and_account(client):
    admin_user = User.objects.create_user(
        email="member-admin@example.invalid",
        password="Secret1234!",
    )
    admin_user.user_permissions.add(
        Permission.objects.get(content_type__app_label="members", codename="view_memberprofile"),
        Permission.objects.get(content_type__app_label="members", codename="change_memberprofile"),
    )
    target_user = User.objects.create_user(
        email="target@example.invalid",
        password="Secret1234!",
        is_active=False,
    )
    client.force_login(admin_user)

    response = client.post(
        reverse("members:admin_edit", args=[target_user.member_profile.pk]),
        {
            "first_name": "Reto",
            "last_name": "Fluetsch",
            "email": "target-updated@example.invalid",
            "is_active": "on",
            "membership_status": MemberProfile.MembershipStatus.ACTIVE,
            "joined_on": "2026-01-01",
            "left_on": "",
            "current_charge": MemberProfile.ChargeChoices.AKTUAR,
            "member_roles": [MemberProfile.MemberRole.BURSCH, MemberProfile.MemberRole.WEB_X],
            "association_type": MemberProfile.AssociationType.AF,
            "directory_visibility": MemberProfile.DirectoryVisibility.MEMBERS,
            "vulgar_name": "Parzival",
            "phone_number": "+41 61 555 00 11",
        },
    )

    target_user.refresh_from_db()
    target_user.member_profile.refresh_from_db()

    assert response.status_code == 302
    assert response.url == reverse("members:admin_list")
    assert target_user.first_name == "Reto"
    assert target_user.email == "target-updated@example.invalid"
    assert target_user.is_active is True
    assert target_user.member_profile.current_charge == MemberProfile.ChargeChoices.AKTUAR
    assert target_user.member_profile.member_roles == [
        MemberProfile.MemberRole.BURSCH,
        MemberProfile.MemberRole.WEB_X,
    ]
    assert target_user.member_profile.association_type == MemberProfile.AssociationType.AF
    assert target_user.member_profile.phone_number == "+41 61 555 00 11"


@pytest.mark.django_db
def test_bootstrap_roles_creates_expected_groups():
    call_command("bootstrap_roles")

    member_admin = Group.objects.get(name="member_admin")
    president = Group.objects.get(name="president")
    system_admin = Group.objects.get(name="system_admin")

    assert member_admin.permissions.filter(
        content_type__app_label="accounts",
        codename="add_accountinvitation",
    ).exists()
    assert president.permissions.filter(
        content_type__app_label="audit",
        codename="view_auditlogentry",
    ).exists()
    assert member_admin.permissions.filter(
        content_type__app_label="members",
        codename="view_sensitive_documents",
    ).exists()
    assert system_admin.permissions.filter(
        content_type__app_label="members",
        codename="manage_member_profiles",
    ).exists()
