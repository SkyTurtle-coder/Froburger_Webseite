from django import forms
from django.contrib.auth import get_user_model

from .models import MemberProfile

User = get_user_model()


class MemberProfileSelfForm(forms.ModelForm):
    class Meta:
        model = MemberProfile
        fields = ("vulgar_name", "directory_visibility", "short_bio")
        labels = {
            "vulgar_name": "Vulgo",
            "directory_visibility": "Sichtbarkeit",
            "short_bio": "Kurzbeschreibung",
        }
        widgets = {
            "short_bio": forms.Textarea(attrs={"rows": 4}),
        }


class MemberProfileAdminForm(forms.ModelForm):
    first_name = forms.CharField(label="Vorname", max_length=150, required=False)
    last_name = forms.CharField(label="Nachname", max_length=150, required=False)
    email = forms.EmailField(label="E-Mail-Adresse")
    is_active = forms.BooleanField(label="Konto aktiv", required=False)

    class Meta:
        model = MemberProfile
        fields = (
            "membership_number",
            "membership_status",
            "joined_on",
            "left_on",
            "current_charge",
            "directory_visibility",
            "vulgar_name",
            "short_bio",
        )
        labels = {
            "membership_number": "Mitgliedsnummer",
            "membership_status": "Mitgliederstatus",
            "joined_on": "Eintrittsdatum",
            "left_on": "Austrittsdatum",
            "current_charge": "Aktuelle Charge",
            "directory_visibility": "Sichtbarkeit",
            "vulgar_name": "Vulgo",
            "short_bio": "Kurzbeschreibung",
        }
        widgets = {
            "joined_on": forms.DateInput(attrs={"type": "date"}),
            "left_on": forms.DateInput(attrs={"type": "date"}),
            "short_bio": forms.Textarea(attrs={"rows": 4}),
        }

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        user = self.instance.user
        self.fields["first_name"].initial = user.first_name
        self.fields["last_name"].initial = user.last_name
        self.fields["email"].initial = user.email
        self.fields["is_active"].initial = user.is_active

    def clean_email(self):
        email = self.cleaned_data["email"].strip().lower()
        existing = User.objects.filter(email__iexact=email).exclude(pk=self.instance.user_id)
        if existing.exists():
            raise forms.ValidationError("Diese E-Mail-Adresse ist bereits vergeben.")
        return email

    def save(self, commit=True):
        profile = super().save(commit=False)
        user = profile.user
        user.first_name = self.cleaned_data["first_name"].strip()
        user.last_name = self.cleaned_data["last_name"].strip()
        user.email = self.cleaned_data["email"]
        user.is_active = self.cleaned_data["is_active"]

        if commit:
            user.save()
            profile.save()
            self.save_m2m()

        return profile
