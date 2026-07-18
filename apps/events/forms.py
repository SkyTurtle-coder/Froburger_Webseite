from __future__ import annotations

from django import forms
from django.contrib.auth import get_user_model
from django.contrib.auth.models import Group

from apps.content.forms import (
    DATETIME_LOCAL_FORMAT,
    VersionedModelForm,
    datetime_local_widget,
    editable_media_queryset_for_user,
)

from .models import Event, EventCategory

User = get_user_model()


class EventForm(VersionedModelForm):
    class Meta:
        model = Event
        fields = (
            "title",
            "slug",
            "short_description",
            "description",
            "start_at",
            "end_at",
            "timezone_name",
            "location_name",
            "location_address",
            "map_url",
            "hero_image",
            "category",
            "visibility",
            "allowed_groups",
            "allowed_users",
            "status",
            "published_at",
            "scheduled_for",
            "signup_url",
            "signup_deadline",
            "max_participants",
        )
        widgets = {
            "short_description": forms.Textarea(attrs={"rows": 3}),
            "description": forms.Textarea(attrs={"rows": 8}),
            "start_at": datetime_local_widget(),
            "end_at": datetime_local_widget(),
            "published_at": datetime_local_widget(),
            "scheduled_for": datetime_local_widget(),
            "signup_deadline": datetime_local_widget(),
            "location_address": forms.Textarea(attrs={"rows": 3}),
            "allowed_groups": forms.CheckboxSelectMultiple(),
            "allowed_users": forms.SelectMultiple(attrs={"size": 8}),
        }
        labels = {
            "title": "Titel",
            "slug": "Slug",
            "short_description": "Kurzbeschreibung",
            "description": "Ausfuehrliche Beschreibung",
            "start_at": "Beginn",
            "end_at": "Ende",
            "timezone_name": "Zeitzone",
            "location_name": "Ort",
            "location_address": "Adresse",
            "map_url": "Karten- oder externe URL",
            "hero_image": "Titelbild",
            "category": "Kategorie",
            "visibility": "Sichtbarkeit",
            "allowed_groups": "Erlaubte Gruppen",
            "allowed_users": "Erlaubte Benutzer",
            "status": "Status",
            "published_at": "Veroeffentlicht am",
            "scheduled_for": "Geplant fuer",
            "signup_url": "Anmeldelink",
            "signup_deadline": "Anmeldefrist",
            "max_participants": "Maximale Teilnehmerzahl",
        }

    def __init__(self, *args, **kwargs):
        self.user = kwargs.pop("user", None)
        super().__init__(*args, **kwargs)
        media_queryset = editable_media_queryset_for_user(self.user)
        self.fields["hero_image"].queryset = media_queryset
        self.fields["category"].queryset = EventCategory.objects.filter(is_active=True).order_by(
            "name"
        )
        self.fields["allowed_groups"].queryset = Group.objects.order_by("name")
        self.fields["allowed_users"].queryset = User.objects.filter(is_active=True).order_by(
            "email"
        )
        self.fields["hero_image"].required = False
        self.fields["category"].required = False
        self.fields["map_url"].required = False
        self.fields["published_at"].required = False
        self.fields["scheduled_for"].required = False
        self.fields["signup_url"].required = False
        self.fields["signup_deadline"].required = False
        self.fields["max_participants"].required = False
        for field_name in (
            "start_at",
            "end_at",
            "published_at",
            "scheduled_for",
            "signup_deadline",
        ):
            self.fields[field_name].input_formats = [DATETIME_LOCAL_FORMAT]
        self.fields["timezone_name"].initial = self.instance.timezone_name or "Europe/Zurich"

    def clean_timezone_name(self):
        value = (self.cleaned_data.get("timezone_name") or "Europe/Zurich").strip()
        if value != "Europe/Zurich":
            raise forms.ValidationError("Derzeit wird fachlich nur Europe/Zurich unterstuetzt.")
        return value

    def clean(self):
        cleaned_data = super().clean()
        visibility = cleaned_data.get("visibility")
        allowed_groups = cleaned_data.get("allowed_groups")
        allowed_users = cleaned_data.get("allowed_users")
        if visibility == Event.Visibility.SELECTED_GROUPS and not allowed_groups:
            self.add_error("allowed_groups", "Bitte mindestens eine Gruppe auswaehlen.")
        if visibility == Event.Visibility.SELECTED_USERS and not allowed_users:
            self.add_error("allowed_users", "Bitte mindestens einen Benutzer auswaehlen.")
        return cleaned_data
