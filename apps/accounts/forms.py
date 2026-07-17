from datetime import timedelta

from django import forms
from django.contrib.auth import get_user_model
from django.contrib.auth.forms import AuthenticationForm, SetPasswordForm
from django.utils import timezone

from .models import AccountInvitation

User = get_user_model()


class EmailAuthenticationForm(AuthenticationForm):
    username = forms.EmailField(
        label="E-Mail-Adresse",
        widget=forms.EmailInput(attrs={"autofocus": True, "autocomplete": "email"}),
    )

    error_messages = {
        "invalid_login": "Anmeldung fehlgeschlagen. Bitte Zugangsdaten pruefen.",
        "inactive": "Anmeldung fehlgeschlagen. Bitte Zugangsdaten pruefen.",
    }

    def confirm_login_allowed(self, user):
        if not user.is_active:
            raise forms.ValidationError(
                self.error_messages["invalid_login"],
                code="invalid_login",
            )


class InvitationCreateForm(forms.Form):
    email = forms.EmailField(label="E-Mail-Adresse")
    first_name = forms.CharField(label="Vorname", max_length=150, required=False)
    last_name = forms.CharField(label="Nachname", max_length=150, required=False)
    expires_in_days = forms.IntegerField(
        label="Gueltig fuer Tage",
        min_value=1,
        max_value=30,
        initial=7,
    )

    def clean_email(self):
        email = self.cleaned_data["email"].strip().lower()
        user = User.objects.filter(email__iexact=email).first()

        if user and user.is_active:
            raise forms.ValidationError(
                "Fuer diese E-Mail-Adresse existiert bereits ein aktives Konto."
            )

        if user and hasattr(user, "account_invitation") and user.account_invitation.is_usable:
            raise forms.ValidationError(
                "Fuer diese E-Mail-Adresse besteht bereits eine gueltige Einladung."
            )

        return email

    def save(self, actor):
        email = self.cleaned_data["email"]
        first_name = self.cleaned_data.get("first_name", "").strip()
        last_name = self.cleaned_data.get("last_name", "").strip()
        expires_in_days = self.cleaned_data["expires_in_days"]

        user, _ = User.objects.get_or_create(
            email=email,
            defaults={
                "first_name": first_name,
                "last_name": last_name,
                "is_active": False,
            },
        )

        user.first_name = first_name
        user.last_name = last_name
        user.is_active = False
        user.set_unusable_password()
        user.save()

        invitation, _ = AccountInvitation.objects.get_or_create(
            invited_user=user,
            defaults={
                "invited_by": actor,
                "expires_at": timezone.now() + timedelta(days=expires_in_days),
                "token_hash": "",
            },
        )
        invitation.invited_by = actor
        invitation.expires_at = timezone.now() + timedelta(days=expires_in_days)
        raw_token = invitation.issue_token()
        invitation.used_at = None
        invitation.save()

        return invitation, raw_token


class InvitationAcceptForm(SetPasswordForm):
    new_password1 = forms.CharField(
        label="Passwort",
        strip=False,
        widget=forms.PasswordInput(attrs={"autocomplete": "new-password"}),
    )
    new_password2 = forms.CharField(
        label="Passwort wiederholen",
        strip=False,
        widget=forms.PasswordInput(attrs={"autocomplete": "new-password"}),
    )
