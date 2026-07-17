import pytest
from django.core import mail
from django.urls import reverse

from apps.accounts.models import AccountInvitation, User
from apps.audit.models import AuditLogEntry
from apps.members.models import MemberProfile


@pytest.mark.django_db
def test_login_and_logout_flow(client):
    user = User.objects.create_user(email="member@example.invalid", password="Secret1234!")

    response = client.post(
        reverse("accounts:login"),
        {"username": "member@example.invalid", "password": "Secret1234!"},
    )

    assert response.status_code == 302
    assert response.url == "/accounts/"
    assert str(user.pk) == client.session["_auth_user_id"]

    response = client.post(reverse("accounts:logout"))
    assert response.status_code == 302
    assert response.url == reverse("accounts:login")
    assert "_auth_user_id" not in client.session


@pytest.mark.django_db
def test_inactive_user_cannot_log_in(client):
    User.objects.create_user(
        email="inactive@example.invalid",
        password="Secret1234!",
        is_active=False,
    )

    response = client.post(
        reverse("accounts:login"),
        {"username": "inactive@example.invalid", "password": "Secret1234!"},
    )

    assert response.status_code == 200
    assert "Anmeldung fehlgeschlagen" in response.content.decode()


@pytest.mark.django_db
def test_password_reset_uses_generic_response_for_unknown_email(client):
    User.objects.create_user(email="member@example.invalid", password="Secret1234!")

    known = client.post(reverse("accounts:password_reset"), {"email": "member@example.invalid"})
    unknown = client.post(reverse("accounts:password_reset"), {"email": "nobody@example.invalid"})

    assert known.status_code == 302
    assert unknown.status_code == 302
    assert known.url == reverse("accounts:password_reset_done")
    assert unknown.url == reverse("accounts:password_reset_done")
    assert len(mail.outbox) == 1


@pytest.mark.django_db
def test_password_change_updates_credentials(client):
    user = User.objects.create_user(email="member@example.invalid", password="Secret1234!")
    client.force_login(user)

    response = client.post(
        reverse("accounts:password_change"),
        {
            "old_password": "Secret1234!",
            "new_password1": "NewSecret1234!",
            "new_password2": "NewSecret1234!",
        },
    )

    user.refresh_from_db()

    assert response.status_code == 302
    assert response.url == reverse("accounts:password_change_done")
    assert user.check_password("NewSecret1234!")


@pytest.mark.django_db
def test_invitation_creation_and_acceptance_activate_account(client):
    inviter = User.objects.create_superuser(
        email="admin@example.invalid",
        password="Secret1234!",
    )
    client.force_login(inviter)

    create_response = client.post(
        reverse("accounts:invitation_create"),
        {
            "email": "invitee@example.invalid",
            "first_name": "Test",
            "last_name": "Mitglied",
            "expires_in_days": 7,
        },
    )

    invitation = AccountInvitation.objects.select_related("invited_user").get()
    invited_user = invitation.invited_user

    assert create_response.status_code == 302
    assert create_response.url == reverse("accounts:invitation_create")
    assert invited_user.is_active is False
    assert len(mail.outbox) == 1
    assert AuditLogEntry.objects.filter(action="accounts.invitation.created").exists()

    token = mail.outbox[0].body.split("/")[-2]
    accept_response = client.post(
        reverse(
            "accounts:invitation_accept",
            kwargs={"invitation_id": invitation.pk, "token": token},
        ),
        {
            "new_password1": "InviteSecret1234!",
            "new_password2": "InviteSecret1234!",
        },
    )

    invited_user.refresh_from_db()
    invitation.refresh_from_db()

    assert accept_response.status_code == 302
    assert accept_response.url == reverse("accounts:login")
    assert invited_user.is_active is True
    assert invited_user.check_password("InviteSecret1234!")
    assert invited_user.member_profile.membership_status == MemberProfile.MembershipStatus.ACTIVE
    assert invitation.used_at is not None
    assert AuditLogEntry.objects.filter(action="accounts.invitation.accepted").exists()
