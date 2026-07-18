from __future__ import annotations

from django import forms
from django.forms import inlineformset_factory

from apps.media_library.models import MediaAsset

from .models import Carousel, CarouselItem, LayoutPreset, Page, PageSection, Post, PostBlock

DATETIME_LOCAL_FORMAT = "%Y-%m-%dT%H:%M"


def datetime_local_widget():
    return forms.DateTimeInput(format=DATETIME_LOCAL_FORMAT, attrs={"type": "datetime-local"})


def editable_media_queryset_for_user(user):
    queryset = MediaAsset.objects.exclude(status=MediaAsset.PublicationStatus.ARCHIVED).order_by(
        "title"
    )
    if user and (
        user.is_superuser or user.has_perm("media_library.manage_private_mediaasset")
    ):
        return queryset
    return queryset.exclude(visibility=MediaAsset.Visibility.PRIVATE)


class VersionedModelForm(forms.ModelForm):
    version_number = forms.IntegerField(widget=forms.HiddenInput)
    change_reason = forms.CharField(
        label="Aenderungsgrund",
        max_length=255,
        required=False,
        widget=forms.TextInput(
            attrs={"placeholder": "Optional, z. B. Inhalt aktualisiert oder Fehler korrigiert"}
        ),
    )

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        if self.instance.pk:
            self.fields["version_number"].initial = self.instance.version_number
        else:
            self.fields["version_number"].initial = 1

    def clean_version_number(self):
        submitted = self.cleaned_data["version_number"]
        if self.instance.pk and submitted != self.instance.version_number:
            raise forms.ValidationError(
                "Dieses Formular basiert auf einem veralteten Stand. "
                "Bitte neu laden und erneut pruefen."
            )
        return submitted


class PostForm(VersionedModelForm):
    class Meta:
        model = Post
        fields = (
            "title",
            "slug",
            "teaser",
            "layout_preset",
            "hero_image",
            "og_image",
            "status",
            "visibility",
            "published_at",
            "scheduled_for",
            "is_homepage_pinned",
            "pin_priority",
            "meta_title",
            "meta_description",
            "event_date",
            "event_location",
            "cta_label",
            "cta_url",
            "categories",
        )
        widgets = {
            "published_at": datetime_local_widget(),
            "scheduled_for": datetime_local_widget(),
            "event_date": forms.DateInput(attrs={"type": "date"}),
            "teaser": forms.Textarea(attrs={"rows": 3}),
            "meta_description": forms.Textarea(attrs={"rows": 3}),
            "categories": forms.TextInput(
                attrs={"placeholder": "Kommagetrennte Kategorien, z. B. Rueckblick, Aktivitas"}
            ),
        }
        labels = {
            "title": "Titel",
            "slug": "Slug",
            "teaser": "Teaser",
            "layout_preset": "Layout",
            "hero_image": "Titelbild",
            "og_image": "Open-Graph-Bild",
            "status": "Status",
            "visibility": "Sichtbarkeit",
            "published_at": "Veroeffentlicht am",
            "scheduled_for": "Geplant fuer",
            "is_homepage_pinned": "Auf Startseite anpinnen",
            "pin_priority": "Pin-Prioritaet",
            "meta_title": "SEO-Titel",
            "meta_description": "SEO-Beschreibung",
            "event_date": "Anlassdatum",
            "event_location": "Ort",
            "cta_label": "Call-to-Action Label",
            "cta_url": "Call-to-Action URL",
            "categories": "Kategorien",
        }

    def __init__(self, *args, **kwargs):
        self.user = kwargs.pop("user", None)
        super().__init__(*args, **kwargs)
        self.fields["layout_preset"].queryset = LayoutPreset.objects.filter(
            scope=LayoutPreset.Scope.POST,
            is_active=True,
        ).order_by("name")
        media_queryset = editable_media_queryset_for_user(self.user)
        self.fields["hero_image"].queryset = media_queryset
        self.fields["og_image"].queryset = media_queryset
        self.fields["published_at"].input_formats = [DATETIME_LOCAL_FORMAT]
        self.fields["scheduled_for"].input_formats = [DATETIME_LOCAL_FORMAT]
        self.fields["categories"].required = False
        self.fields["meta_title"].required = False
        self.fields["meta_description"].required = False
        self.fields["event_date"].required = False
        self.fields["event_location"].required = False
        self.fields["cta_label"].required = False
        self.fields["cta_url"].required = False
        self.fields["published_at"].required = False
        self.fields["scheduled_for"].required = False
        self.fields["og_image"].required = False

    def clean_categories(self):
        raw_value = self.cleaned_data["categories"]
        if isinstance(raw_value, list):
            return raw_value
        if not raw_value:
            return []
        return [item.strip() for item in str(raw_value).split(",") if item.strip()]


class PostBlockForm(forms.ModelForm):
    class Meta:
        model = PostBlock
        fields = (
            "block_type",
            "layout_preset",
            "position",
            "is_active",
            "anchor_id",
            "eyebrow",
            "heading",
            "body",
            "image",
            "carousel",
            "link_label",
            "link_url",
            "options",
        )
        widgets = {
            "body": forms.Textarea(attrs={"rows": 4}),
            "options": forms.Textarea(
                attrs={"rows": 2, "placeholder": '{"tone": "default"}'}
            ),
        }
        labels = {
            "block_type": "Blocktyp",
            "layout_preset": "Layoutvariante",
            "position": "Position",
            "is_active": "Aktiv",
            "anchor_id": "Anker-ID",
            "eyebrow": "Eyebrow",
            "heading": "Ueberschrift",
            "body": "Textinhalt",
            "image": "Bild",
            "carousel": "Karussell oder Galerie",
            "link_label": "Link-Label",
            "link_url": "Link-URL",
            "options": "Optionen",
        }

    def __init__(self, *args, **kwargs):
        self.user = kwargs.pop("user", None)
        super().__init__(*args, **kwargs)
        self.fields["layout_preset"].queryset = LayoutPreset.objects.filter(
            scope=LayoutPreset.Scope.BLOCK,
            is_active=True,
        ).order_by("name")
        self.fields["image"].queryset = editable_media_queryset_for_user(self.user)
        self.fields["carousel"].queryset = Carousel.objects.order_by("name")
        self.fields["options"].required = False
        self.fields["anchor_id"].required = False
        self.fields["eyebrow"].required = False
        self.fields["heading"].required = False
        self.fields["body"].required = False
        self.fields["image"].required = False
        self.fields["carousel"].required = False
        self.fields["link_label"].required = False
        self.fields["link_url"].required = False
        self.fields["position"].widget.attrs.setdefault("min", 0)

    def clean_options(self):
        raw_value = self.cleaned_data.get("options")
        if not raw_value:
            return {}
        if isinstance(raw_value, dict):
            return raw_value
        import json

        try:
            parsed = json.loads(raw_value)
        except json.JSONDecodeError as exc:
            raise forms.ValidationError("Optionen muessen gueltiges JSON sein.") from exc
        if not isinstance(parsed, dict):
            raise forms.ValidationError("Optionen muessen ein JSON-Objekt sein.")
        return parsed


PostBlockFormSet = inlineformset_factory(
    Post,
    PostBlock,
    form=PostBlockForm,
    extra=2,
    can_delete=True,
)


class MediaAssetForm(forms.ModelForm):
    class Meta:
        model = MediaAsset
        fields = (
            "file",
            "title",
            "alt_text",
            "is_decorative",
            "caption",
            "credit",
            "captured_on",
            "visibility",
            "status",
        )
        widgets = {
            "captured_on": forms.DateInput(attrs={"type": "date"}),
            "caption": forms.TextInput(attrs={"placeholder": "Optional"}),
            "credit": forms.TextInput(attrs={"placeholder": "Optional"}),
        }
        labels = {
            "file": "Bilddatei",
            "title": "Titel",
            "alt_text": "Alt-Text",
            "is_decorative": "Dekoratives Bild",
            "caption": "Bildlegende",
            "credit": "Urheber oder Quelle",
            "captured_on": "Aufnahmedatum",
            "visibility": "Sichtbarkeit",
            "status": "Status",
        }

    def __init__(self, *args, **kwargs):
        self.user = kwargs.pop("user", None)
        super().__init__(*args, **kwargs)

    def clean_visibility(self):
        visibility = self.cleaned_data["visibility"]
        if (
            visibility == MediaAsset.Visibility.PRIVATE
            and not (
                self.user
                and (
                    self.user.is_superuser
                    or self.user.has_perm(
                        "media_library.manage_private_mediaasset"
                    )
                )
            )
        ):
            raise forms.ValidationError(
                "Private Medien duerfen nur von Systemadministratoren verwaltet werden."
            )
        return visibility


class CarouselForm(VersionedModelForm):
    version_number = forms.IntegerField(widget=forms.HiddenInput, initial=1)

    class Meta:
        model = Carousel
        fields = ("name", "title", "description", "usage_context", "visibility", "is_active")
        labels = {
            "name": "Interner Name",
            "title": "Titel",
            "description": "Beschreibung",
            "usage_context": "Verwendungszweck",
            "visibility": "Sichtbarkeit",
            "is_active": "Aktiv",
        }
        widgets = {
            "description": forms.Textarea(attrs={"rows": 3}),
        }

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        if self.instance.pk:
            self.fields["version_number"].initial = (
                self.instance.revisions.order_by("-revision_number")
                .values_list("revision_number", flat=True)
                .first()
                or 1
            )

    def clean_version_number(self):
        submitted = self.cleaned_data["version_number"]
        if self.instance.pk:
            current = (
                self.instance.revisions.order_by("-revision_number")
                .values_list("revision_number", flat=True)
                .first()
                or 1
            )
            if submitted != current:
                raise forms.ValidationError(
                    "Dieses Formular basiert auf einem veralteten Karussellstand."
                )
        return submitted


class CarouselItemForm(forms.ModelForm):
    class Meta:
        model = CarouselItem
        fields = (
            "image",
            "position",
            "is_active",
            "heading",
            "body",
            "link_label",
            "link_url",
            "starts_at",
            "ends_at",
        )
        widgets = {
            "body": forms.Textarea(attrs={"rows": 3}),
            "starts_at": datetime_local_widget(),
            "ends_at": datetime_local_widget(),
        }
        labels = {
            "image": "Bild",
            "position": "Position",
            "is_active": "Aktiv",
            "heading": "Ueberschrift",
            "body": "Text",
            "link_label": "Link-Label",
            "link_url": "Link-URL",
            "starts_at": "Start",
            "ends_at": "Ende",
        }

    def __init__(self, *args, **kwargs):
        self.user = kwargs.pop("user", None)
        super().__init__(*args, **kwargs)
        self.fields["image"].queryset = editable_media_queryset_for_user(self.user)
        self.fields["position"].widget.attrs.setdefault("min", 0)
        self.fields["link_label"].required = False
        self.fields["link_url"].required = False
        self.fields["heading"].required = False
        self.fields["body"].required = False
        self.fields["starts_at"].required = False
        self.fields["ends_at"].required = False
        self.fields["starts_at"].input_formats = [DATETIME_LOCAL_FORMAT]
        self.fields["ends_at"].input_formats = [DATETIME_LOCAL_FORMAT]


CarouselItemFormSet = inlineformset_factory(
    Carousel,
    CarouselItem,
    form=CarouselItemForm,
    extra=3,
    can_delete=True,
)


class HomepagePageForm(VersionedModelForm):
    class Meta:
        model = Page
        fields = (
            "title",
            "meta_title",
            "meta_description",
            "og_image",
            "status",
            "visibility",
            "published_at",
            "scheduled_for",
        )
        widgets = {
            "meta_description": forms.Textarea(attrs={"rows": 3}),
            "published_at": datetime_local_widget(),
            "scheduled_for": datetime_local_widget(),
        }
        labels = {
            "title": "Seitentitel",
            "meta_title": "SEO-Titel",
            "meta_description": "SEO-Beschreibung",
            "og_image": "Open-Graph-Bild",
            "status": "Status",
            "visibility": "Sichtbarkeit",
            "published_at": "Veroeffentlicht am",
            "scheduled_for": "Geplant fuer",
        }

    def __init__(self, *args, **kwargs):
        self.user = kwargs.pop("user", None)
        super().__init__(*args, **kwargs)
        self.fields["og_image"].queryset = editable_media_queryset_for_user(self.user)
        self.fields["published_at"].input_formats = [DATETIME_LOCAL_FORMAT]
        self.fields["scheduled_for"].input_formats = [DATETIME_LOCAL_FORMAT]
        self.fields["og_image"].required = False


class HomepageSectionForm(forms.ModelForm):
    class Meta:
        model = PageSection
        fields = (
            "block_type",
            "layout_preset",
            "position",
            "is_active",
            "anchor_id",
            "eyebrow",
            "heading",
            "body",
            "image",
            "carousel",
            "link_label",
            "link_url",
            "options",
        )
        widgets = {
            "body": forms.Textarea(attrs={"rows": 4}),
            "options": forms.Textarea(attrs={"rows": 2, "placeholder": '{"tone": "default"}'}),
        }
        labels = {
            "block_type": "Blocktyp",
            "layout_preset": "Layoutvariante",
            "position": "Position",
            "is_active": "Aktiv",
            "anchor_id": "Anker-ID",
            "eyebrow": "Eyebrow",
            "heading": "Ueberschrift",
            "body": "Textinhalt",
            "image": "Bild",
            "carousel": "Karussell",
            "link_label": "Link-Label",
            "link_url": "Link-URL",
            "options": "Optionen",
        }

    def __init__(self, *args, **kwargs):
        self.user = kwargs.pop("user", None)
        super().__init__(*args, **kwargs)
        self.fields["layout_preset"].queryset = LayoutPreset.objects.filter(
            scope=LayoutPreset.Scope.BLOCK,
            is_active=True,
        ).order_by("name")
        self.fields["image"].queryset = editable_media_queryset_for_user(self.user)
        self.fields["carousel"].queryset = Carousel.objects.order_by("name")
        self.fields["options"].required = False

    def clean_options(self):
        raw_value = self.cleaned_data.get("options")
        if not raw_value:
            return {}
        if isinstance(raw_value, dict):
            return raw_value
        import json

        try:
            parsed = json.loads(raw_value)
        except json.JSONDecodeError as exc:
            raise forms.ValidationError("Optionen muessen gueltiges JSON sein.") from exc
        if not isinstance(parsed, dict):
            raise forms.ValidationError("Optionen muessen ein JSON-Objekt sein.")
        return parsed


HomepageSectionFormSet = inlineformset_factory(
    Page,
    PageSection,
    form=HomepageSectionForm,
    extra=0,
    can_delete=False,
)
