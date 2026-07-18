from __future__ import annotations

from django import forms
from django.contrib.auth import get_user_model
from django.contrib.auth.models import Group

from apps.content.forms import DATETIME_LOCAL_FORMAT, datetime_local_widget

from .models import Document, DocumentCategory
from .services import user_can_manage_sensitive_documents, validate_document_upload

User = get_user_model()


class DocumentForm(forms.ModelForm):
    version_counter = forms.IntegerField(widget=forms.HiddenInput)
    change_note = forms.CharField(
        label="Aenderungsnotiz",
        max_length=255,
        required=False,
        widget=forms.TextInput(
            attrs={"placeholder": "Optional, z. B. neue Version oder Korrektur"}
        ),
    )
    file_upload = forms.FileField(label="Datei", required=False)

    class Meta:
        model = Document
        fields = (
            "title",
            "description",
            "category",
            "visibility",
            "allowed_groups",
            "allowed_users",
            "status",
            "published_at",
            "valid_until",
        )
        widgets = {
            "description": forms.Textarea(attrs={"rows": 5}),
            "allowed_groups": forms.CheckboxSelectMultiple(),
            "allowed_users": forms.SelectMultiple(attrs={"size": 8}),
            "published_at": datetime_local_widget(),
            "valid_until": datetime_local_widget(),
        }
        labels = {
            "title": "Titel",
            "description": "Beschreibung",
            "category": "Kategorie",
            "visibility": "Sichtbarkeit",
            "allowed_groups": "Erlaubte Gruppen",
            "allowed_users": "Erlaubte Benutzer",
            "status": "Status",
            "published_at": "Veroeffentlicht am",
            "valid_until": "Gueltig bis",
        }

    def __init__(self, *args, **kwargs):
        self.user = kwargs.pop("user", None)
        super().__init__(*args, **kwargs)
        self.fields["category"].queryset = DocumentCategory.objects.filter(is_active=True).order_by(
            "name"
        )
        self.fields["allowed_groups"].queryset = Group.objects.order_by("name")
        self.fields["allowed_users"].queryset = User.objects.filter(is_active=True).order_by(
            "email"
        )
        self.fields["category"].required = False
        self.fields["published_at"].required = False
        self.fields["valid_until"].required = False
        self.fields["published_at"].input_formats = [DATETIME_LOCAL_FORMAT]
        self.fields["valid_until"].input_formats = [DATETIME_LOCAL_FORMAT]
        self.fields["version_counter"].initial = (
            self.instance.version_counter if self.instance.pk else 0
        )
        if self.instance.pk and self.instance.current_version_id:
            self.fields["file_upload"].help_text = (
                f"Aktuelle Version: {self.instance.current_version.original_filename} "
                f"(v{self.instance.current_version.version_number})"
            )

    def clean_version_counter(self):
        submitted = self.cleaned_data["version_counter"]
        if self.instance.pk and submitted != self.instance.version_counter:
            raise forms.ValidationError(
                "Dieses Formular basiert auf einem veralteten Stand. Bitte neu laden."
            )
        return submitted

    def clean_file_upload(self):
        uploaded = self.cleaned_data.get("file_upload")
        if not uploaded:
            if not self.instance.pk:
                raise forms.ValidationError("Bitte eine Datei hochladen.")
            return None
        validate_document_upload(uploaded)
        uploaded.seek(0)
        return uploaded

    def clean(self):
        cleaned_data = super().clean()
        visibility = cleaned_data.get("visibility")
        allowed_groups = cleaned_data.get("allowed_groups")
        allowed_users = cleaned_data.get("allowed_users")
        if visibility == Document.Visibility.SELECTED_GROUPS and not allowed_groups:
            self.add_error("allowed_groups", "Bitte mindestens eine Gruppe auswaehlen.")
        if visibility == Document.Visibility.SELECTED_USERS and not allowed_users:
            self.add_error("allowed_users", "Bitte mindestens einen Benutzer auswaehlen.")
        if (
            visibility == Document.Visibility.HIGHLY_SENSITIVE
            and not user_can_manage_sensitive_documents(self.user)
        ):
            self.add_error(
                "visibility",
                "Besonders sensible Dokumente duerfen nur separat berechtigt verwaltet werden.",
            )
        return cleaned_data
