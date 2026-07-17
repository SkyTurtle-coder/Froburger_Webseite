import pytest
from django.contrib.auth.models import Group, Permission
from django.core.management import call_command
from django.urls import reverse

from apps.accounts.models import User
from apps.members.models import MemberProfile


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
def test_member_can_view_and_edit_own_profile(client):
    user = User.objects.create_user(
        email="member@example.invalid",
        password="Secret1234!",
        first_name="Philipp",
        last_name="Thuerlemann",
    )
    client.force_login(user)

    detail_response = client.get(reverse("members:me"))
    edit_response = client.post(
        reverse("members:me_edit"),
        {
            "vulgar_name": "Newton",
            "directory_visibility": MemberProfile.DirectoryVisibility.PUBLIC,
            "short_bio": "Aktives Mitglied der AV Froburger.",
        },
    )

    user.member_profile.refresh_from_db()

    assert detail_response.status_code == 200
    assert edit_response.status_code == 302
    assert edit_response.url == reverse("members:me")
    assert user.member_profile.vulgar_name == "Newton"
    assert user.member_profile.directory_visibility == MemberProfile.DirectoryVisibility.PUBLIC


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
            "membership_number": "FB-42",
            "membership_status": MemberProfile.MembershipStatus.ACTIVE,
            "joined_on": "2026-01-01",
            "left_on": "",
            "current_charge": "Aktuar",
            "directory_visibility": MemberProfile.DirectoryVisibility.MEMBERS,
            "vulgar_name": "Parzival",
            "short_bio": "Aktiv und engagiert.",
        },
    )

    target_user.refresh_from_db()
    target_user.member_profile.refresh_from_db()

    assert response.status_code == 302
    assert response.url == reverse("members:admin_list")
    assert target_user.first_name == "Reto"
    assert target_user.email == "target-updated@example.invalid"
    assert target_user.is_active is True
    assert target_user.member_profile.membership_number == "FB-42"
    assert target_user.member_profile.current_charge == "Aktuar"


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
    assert system_admin.permissions.filter(
        content_type__app_label="members",
        codename="manage_member_profiles",
    ).exists()
