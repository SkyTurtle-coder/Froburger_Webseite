from django import forms
from django.contrib.auth import get_user_model

from .models import MemberProfile

User = get_user_model()


class MemberProfileSelfForm(forms.ModelForm):
    class Meta:
        model = MemberProfile
        fields = ("vulgar_name", "directory_visibility", "profile_photo", "phone_number")
        labels = {
            "vulgar_name": "Vulgo",
            "directory_visibility": "Sichtbarkeit",
            "profile_photo": "Profilfoto",
            "phone_number": "Telefonnummer",
        }


class MemberProfileAdminForm(forms.ModelForm):
    first_name = forms.CharField(label="Vorname", max_length=150, required=False)
    last_name = forms.CharField(label="Nachname", max_length=150, required=False)
    email = forms.EmailField(label="E-Mail-Adresse")
    is_active = forms.BooleanField(label="Konto aktiv", required=False)
    member_roles = forms.MultipleChoiceField(
        label="Rollen",
        choices=MemberProfile.MemberRole.choices,
        required=False,
        widget=forms.SelectMultiple(attrs={"size": 3}),
    )

    class Meta:
        model = MemberProfile
        fields = (
            "membership_status",
            "joined_on",
            "left_on",
            "current_charge",
            "member_roles",
            "association_type",
            "directory_visibility",
            "vulgar_name",
            "profile_photo",
            "phone_number",
        )
        labels = {
            "membership_status": "Mitgliederstatus",
            "joined_on": "Eintrittsdatum",
            "left_on": "Austrittsdatum",
            "current_charge": "Charge",
            "association_type": "Verein",
            "directory_visibility": "Sichtbarkeit",
            "vulgar_name": "Vulgo",
            "profile_photo": "Profilfoto",
            "phone_number": "Telefonnummer",
        }
        widgets = {
            "joined_on": forms.DateInput(attrs={"type": "date"}),
            "left_on": forms.DateInput(attrs={"type": "date"}),
        }

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        user = self.instance.user
        self.fields["first_name"].initial = user.first_name
        self.fields["last_name"].initial = user.last_name
        self.fields["email"].initial = user.email
        self.fields["is_active"].initial = user.is_active
        self.fields["member_roles"].initial = self.instance.member_roles

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
        profile.member_roles = self.cleaned_data["member_roles"]

        if commit:
            user.save()
            profile.save()
            self.save_m2m()

        return profile
