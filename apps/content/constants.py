from __future__ import annotations

from dataclasses import dataclass

BLOCK_TYPE_KEYS = (
    "heading",
    "text",
    "image",
    "image_text",
    "hero_image",
    "gallery",
    "carousel",
    "quote",
    "cta",
    "link_list",
    "document_list",
    "event_list",
    "post_list",
    "people_list",
    "timeline",
    "divider",
    "notice",
)


@dataclass(frozen=True)
class LayoutPresetDefinition:
    scope: str
    key: str
    name: str
    description: str
    template_name: str
    allowed_block_types: tuple[str, ...] = ()
    required_fields: tuple[str, ...] = ()
    max_images: int = 1


PAGE_LAYOUT_PRESET_DEFINITIONS = (
    LayoutPresetDefinition(
        scope="page",
        key="homepage",
        name="Startseite",
        description="Startseite mit fixen Bereichen, Hero und angepinnten Beitragsflaechen.",
        template_name="content/pages/layouts/homepage.html",
        allowed_block_types=(
            "heading",
            "text",
            "image_text",
            "carousel",
            "cta",
            "post_list",
            "event_list",
            "gallery",
            "notice",
        ),
    ),
    LayoutPresetDefinition(
        scope="page",
        key="standard_page",
        name="Standardseite",
        description="Geschuetzte Grundstruktur fuer redaktionelle Seiten.",
        template_name="content/pages/layouts/standard_page.html",
        allowed_block_types=(
            "heading",
            "text",
            "image",
            "image_text",
            "gallery",
            "carousel",
            "quote",
            "cta",
            "link_list",
            "document_list",
            "notice",
            "divider",
        ),
    ),
    LayoutPresetDefinition(
        scope="page",
        key="legal_page",
        name="Rechtliche Seite",
        description="Strenge Seitenstruktur fuer Impressum und Datenschutz.",
        template_name="content/pages/layouts/legal_page.html",
        allowed_block_types=("heading", "text", "notice", "divider"),
    ),
)


POST_LAYOUT_PRESET_DEFINITIONS = (
    LayoutPresetDefinition(
        scope="post",
        key="standard_article",
        name="Standardartikel",
        description="Klassischer Artikel mit Titelbild und Fliesstext.",
        template_name="content/posts/layouts/standard_article.html",
        allowed_block_types=(
            "heading",
            "text",
            "image",
            "quote",
            "cta",
            "divider",
            "notice",
        ),
        required_fields=("title", "teaser", "hero_image"),
    ),
    LayoutPresetDefinition(
        scope="post",
        key="large_hero",
        name="Grosser Hero-Beitrag",
        description="Hero-Bild gross mit anschliessenden Inhaltsbloecken.",
        template_name="content/posts/layouts/large_hero.html",
        allowed_block_types=(
            "heading",
            "text",
            "image",
            "image_text",
            "gallery",
            "carousel",
            "quote",
            "cta",
            "divider",
        ),
        required_fields=("title", "teaser", "hero_image"),
    ),
    LayoutPresetDefinition(
        scope="post",
        key="image_left",
        name="Bild links",
        description="Titel und Teaser neben einem links ausgerichteten Bild.",
        template_name="content/posts/layouts/image_left.html",
        allowed_block_types=(
            "heading",
            "text",
            "image_text",
            "quote",
            "cta",
            "divider",
        ),
        required_fields=("title", "teaser", "hero_image"),
    ),
    LayoutPresetDefinition(
        scope="post",
        key="image_right",
        name="Bild rechts",
        description="Titel und Teaser neben einem rechts ausgerichteten Bild.",
        template_name="content/posts/layouts/image_right.html",
        allowed_block_types=(
            "heading",
            "text",
            "image_text",
            "quote",
            "cta",
            "divider",
        ),
        required_fields=("title", "teaser", "hero_image"),
    ),
    LayoutPresetDefinition(
        scope="post",
        key="gallery_story",
        name="Galeriebeitrag",
        description="Beitrag mit Bildergalerie oder Karussell im Fokus.",
        template_name="content/posts/layouts/gallery_story.html",
        allowed_block_types=("heading", "text", "gallery", "carousel", "cta", "notice"),
        required_fields=("title", "teaser"),
        max_images=12,
    ),
    LayoutPresetDefinition(
        scope="post",
        key="event_recap",
        name="Veranstaltungsrueckblick",
        description="Rueckblick mit Datum, Ort und Bilderstrecke.",
        template_name="content/posts/layouts/event_recap.html",
        allowed_block_types=(
            "heading",
            "text",
            "gallery",
            "carousel",
            "quote",
            "cta",
            "notice",
        ),
        required_fields=("title", "teaser", "hero_image"),
        max_images=12,
    ),
    LayoutPresetDefinition(
        scope="post",
        key="announcement",
        name="Ankuendigung",
        description="Kurze, handlungsorientierte Mitteilung mit Call-to-Action.",
        template_name="content/posts/layouts/announcement.html",
        allowed_block_types=("heading", "text", "cta", "notice", "divider"),
        required_fields=("title", "teaser"),
    ),
    LayoutPresetDefinition(
        scope="post",
        key="compact_news",
        name="Kompakte Neuigkeit",
        description="Kompakter Beitrag fuer kurze Aktualitaeten.",
        template_name="content/posts/layouts/compact_news.html",
        allowed_block_types=("heading", "text", "image", "cta", "notice"),
        required_fields=("title", "teaser"),
    ),
)


BLOCK_LAYOUT_PRESET_DEFINITIONS = (
    LayoutPresetDefinition(
        scope="block",
        key="text_only",
        name="Nur Text",
        description="Reiner Text- oder Ueberschriftsblock.",
        template_name="content/blocks/text_only.html",
        allowed_block_types=("heading", "text", "quote", "divider", "notice"),
    ),
    LayoutPresetDefinition(
        scope="block",
        key="image_left",
        name="Bild links",
        description="Bild links, Text rechts.",
        template_name="content/blocks/image_left.html",
        allowed_block_types=("image_text",),
    ),
    LayoutPresetDefinition(
        scope="block",
        key="image_right",
        name="Bild rechts",
        description="Bild rechts, Text links.",
        template_name="content/blocks/image_right.html",
        allowed_block_types=("image_text",),
    ),
    LayoutPresetDefinition(
        scope="block",
        key="image_full_width",
        name="Bild volle Breite",
        description="Grosses Bild ueber die gesamte Inhaltsbreite.",
        template_name="content/blocks/image_full_width.html",
        allowed_block_types=("image", "hero_image"),
        max_images=1,
    ),
    LayoutPresetDefinition(
        scope="block",
        key="image_background",
        name="Bild im Hintergrund",
        description="Bildflaeche mit Textueberlagerung.",
        template_name="content/blocks/image_background.html",
        allowed_block_types=("image_text", "hero_image"),
        max_images=1,
    ),
    LayoutPresetDefinition(
        scope="block",
        key="gallery_grid",
        name="Galerieraster",
        description="Mehrere Bilder im Raster.",
        template_name="content/blocks/gallery_grid.html",
        allowed_block_types=("gallery",),
        max_images=12,
    ),
    LayoutPresetDefinition(
        scope="block",
        key="carousel_slides",
        name="Karussell",
        description="Karussell mit mehreren Slides.",
        template_name="content/blocks/carousel_slides.html",
        allowed_block_types=("carousel", "gallery"),
        max_images=12,
    ),
    LayoutPresetDefinition(
        scope="block",
        key="cta_primary",
        name="Call-to-Action",
        description="Hervorgehobener Handlungsblock.",
        template_name="content/blocks/cta_primary.html",
        allowed_block_types=("cta",),
    ),
    LayoutPresetDefinition(
        scope="block",
        key="list_compact",
        name="Kompakte Liste",
        description="Kompakte Listenansicht fuer Links, Dokumente, Beitraege oder Termine.",
        template_name="content/blocks/list_compact.html",
        allowed_block_types=("link_list", "document_list", "event_list", "post_list"),
    ),
    LayoutPresetDefinition(
        scope="block",
        key="people_grid",
        name="Personenraster",
        description="Raster fuer Personen- oder Chargenuebersichten.",
        template_name="content/blocks/people_grid.html",
        allowed_block_types=("people_list",),
    ),
    LayoutPresetDefinition(
        scope="block",
        key="timeline_vertical",
        name="Zeitstrahl",
        description="Vertikaler Zeitstrahl fuer Historie und Ablaufe.",
        template_name="content/blocks/timeline_vertical.html",
        allowed_block_types=("timeline",),
    ),
    LayoutPresetDefinition(
        scope="block",
        key="notice_highlight",
        name="Hervorgehobener Hinweis",
        description="Betonter Hinweisblock.",
        template_name="content/blocks/notice_highlight.html",
        allowed_block_types=("notice", "quote"),
    ),
)


ALL_LAYOUT_PRESET_DEFINITIONS = (
    PAGE_LAYOUT_PRESET_DEFINITIONS
    + POST_LAYOUT_PRESET_DEFINITIONS
    + BLOCK_LAYOUT_PRESET_DEFINITIONS
)


BLOCK_OPTION_SCHEMA = {
    "heading": {"heading_level": ("h2", "h3")},
    "image": {"aspect_ratio": ("natural", "landscape", "portrait", "square")},
    "image_text": {
        "vertical_align": ("top", "center"),
        "image_ratio": ("natural", "landscape", "portrait", "square"),
    },
    "gallery": {"columns": (2, 3, 4), "show_captions": (True, False)},
    "carousel": {"autoplay": (True, False)},
    "quote": {"tone": ("default", "muted", "emphasis")},
    "cta": {"tone": ("primary", "secondary")},
    "link_list": {"columns": (1, 2)},
    "document_list": {"columns": (1, 2)},
    "event_list": {"limit": (3, 5, 10)},
    "post_list": {"limit": (3, 5, 10)},
    "people_list": {"columns": (2, 3, 4)},
    "timeline": {"layout": ("vertical",)},
    "notice": {"tone": ("default", "warning", "success")},
}


LAYOUT_PRESET_INDEX = {
    (definition.scope, definition.key): definition for definition in ALL_LAYOUT_PRESET_DEFINITIONS
}


def get_layout_preset_definition(scope: str, key: str) -> LayoutPresetDefinition | None:
    return LAYOUT_PRESET_INDEX.get((scope, key))
