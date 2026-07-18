from django.db import migrations


LAYOUT_PRESETS = [
    {
        "scope": "page",
        "key": "homepage",
        "name": "Startseite",
        "description": "Startseite mit fixen Bereichen, Hero und angepinnten Beitragsflaechen.",
        "allowed_block_types": [
            "heading",
            "text",
            "image_text",
            "carousel",
            "cta",
            "post_list",
            "event_list",
            "gallery",
            "notice",
        ],
    },
    {
        "scope": "page",
        "key": "standard_page",
        "name": "Standardseite",
        "description": "Geschuetzte Grundstruktur fuer redaktionelle Seiten.",
        "allowed_block_types": [
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
        ],
    },
    {
        "scope": "page",
        "key": "legal_page",
        "name": "Rechtliche Seite",
        "description": "Strenge Seitenstruktur fuer Impressum und Datenschutz.",
        "allowed_block_types": ["heading", "text", "notice", "divider"],
    },
    {
        "scope": "post",
        "key": "standard_article",
        "name": "Standardartikel",
        "description": "Klassischer Artikel mit Titelbild und Fliesstext.",
        "allowed_block_types": ["heading", "text", "image", "quote", "cta", "divider", "notice"],
        "required_fields": ["title", "teaser", "hero_image"],
    },
    {
        "scope": "post",
        "key": "large_hero",
        "name": "Grosser Hero-Beitrag",
        "description": "Hero-Bild gross mit anschliessenden Inhaltsbloecken.",
        "allowed_block_types": [
            "heading",
            "text",
            "image",
            "image_text",
            "gallery",
            "carousel",
            "quote",
            "cta",
            "divider",
        ],
        "required_fields": ["title", "teaser", "hero_image"],
    },
    {
        "scope": "post",
        "key": "image_left",
        "name": "Bild links",
        "description": "Titel und Teaser neben einem links ausgerichteten Bild.",
        "allowed_block_types": ["heading", "text", "image_text", "quote", "cta", "divider"],
        "required_fields": ["title", "teaser", "hero_image"],
    },
    {
        "scope": "post",
        "key": "image_right",
        "name": "Bild rechts",
        "description": "Titel und Teaser neben einem rechts ausgerichteten Bild.",
        "allowed_block_types": ["heading", "text", "image_text", "quote", "cta", "divider"],
        "required_fields": ["title", "teaser", "hero_image"],
    },
    {
        "scope": "post",
        "key": "gallery_story",
        "name": "Galeriebeitrag",
        "description": "Beitrag mit Bildergalerie oder Karussell im Fokus.",
        "allowed_block_types": ["heading", "text", "gallery", "carousel", "cta", "notice"],
        "required_fields": ["title", "teaser"],
        "max_images": 12,
    },
    {
        "scope": "post",
        "key": "event_recap",
        "name": "Veranstaltungsrueckblick",
        "description": "Rueckblick mit Datum, Ort und Bilderstrecke.",
        "allowed_block_types": [
            "heading",
            "text",
            "gallery",
            "carousel",
            "quote",
            "cta",
            "notice",
        ],
        "required_fields": ["title", "teaser", "hero_image"],
        "max_images": 12,
    },
    {
        "scope": "post",
        "key": "announcement",
        "name": "Ankuendigung",
        "description": "Kurze, handlungsorientierte Mitteilung mit Call-to-Action.",
        "allowed_block_types": ["heading", "text", "cta", "notice", "divider"],
        "required_fields": ["title", "teaser"],
    },
    {
        "scope": "post",
        "key": "compact_news",
        "name": "Kompakte Neuigkeit",
        "description": "Kompakter Beitrag fuer kurze Aktualitaeten.",
        "allowed_block_types": ["heading", "text", "image", "cta", "notice"],
        "required_fields": ["title", "teaser"],
    },
    {
        "scope": "block",
        "key": "text_only",
        "name": "Nur Text",
        "description": "Reiner Text- oder Ueberschriftsblock.",
        "allowed_block_types": ["heading", "text", "quote", "divider", "notice"],
    },
    {
        "scope": "block",
        "key": "image_left",
        "name": "Bild links",
        "description": "Bild links, Text rechts.",
        "allowed_block_types": ["image_text"],
    },
    {
        "scope": "block",
        "key": "image_right",
        "name": "Bild rechts",
        "description": "Bild rechts, Text links.",
        "allowed_block_types": ["image_text"],
    },
    {
        "scope": "block",
        "key": "image_full_width",
        "name": "Bild volle Breite",
        "description": "Grosses Bild ueber die gesamte Inhaltsbreite.",
        "allowed_block_types": ["image", "hero_image"],
    },
    {
        "scope": "block",
        "key": "image_background",
        "name": "Bild im Hintergrund",
        "description": "Bildflaeche mit Textueberlagerung.",
        "allowed_block_types": ["image_text", "hero_image"],
    },
    {
        "scope": "block",
        "key": "gallery_grid",
        "name": "Galerieraster",
        "description": "Mehrere Bilder im Raster.",
        "allowed_block_types": ["gallery"],
        "max_images": 12,
    },
    {
        "scope": "block",
        "key": "carousel_slides",
        "name": "Karussell",
        "description": "Karussell mit mehreren Slides.",
        "allowed_block_types": ["carousel", "gallery"],
        "max_images": 12,
    },
    {
        "scope": "block",
        "key": "cta_primary",
        "name": "Call-to-Action",
        "description": "Hervorgehobener Handlungsblock.",
        "allowed_block_types": ["cta"],
    },
    {
        "scope": "block",
        "key": "list_compact",
        "name": "Kompakte Liste",
        "description": "Kompakte Listenansicht fuer Links, Dokumente, Beitraege oder Termine.",
        "allowed_block_types": ["link_list", "document_list", "event_list", "post_list"],
    },
    {
        "scope": "block",
        "key": "people_grid",
        "name": "Personenraster",
        "description": "Raster fuer Personen- oder Chargenuebersichten.",
        "allowed_block_types": ["people_list"],
    },
    {
        "scope": "block",
        "key": "timeline_vertical",
        "name": "Zeitstrahl",
        "description": "Vertikaler Zeitstrahl fuer Historie und Ablaufe.",
        "allowed_block_types": ["timeline"],
    },
    {
        "scope": "block",
        "key": "notice_highlight",
        "name": "Hervorgehobener Hinweis",
        "description": "Betonter Hinweisblock.",
        "allowed_block_types": ["notice", "quote"],
    },
]


def seed_layout_presets(apps, schema_editor):
    LayoutPreset = apps.get_model("content", "LayoutPreset")
    for preset in LAYOUT_PRESETS:
        LayoutPreset.objects.update_or_create(
            scope=preset["scope"],
            key=preset["key"],
            defaults={
                "name": preset["name"],
                "description": preset["description"],
                "allowed_block_types": preset.get("allowed_block_types", []),
                "required_fields": preset.get("required_fields", []),
                "max_images": preset.get("max_images", 1),
                "is_active": True,
                "preview_static_path": preset.get("preview_static_path", ""),
            },
        )


def unseed_layout_presets(apps, schema_editor):
    LayoutPreset = apps.get_model("content", "LayoutPreset")
    keys = [(preset["scope"], preset["key"]) for preset in LAYOUT_PRESETS]
    for scope, key in keys:
        LayoutPreset.objects.filter(scope=scope, key=key).delete()


class Migration(migrations.Migration):

    dependencies = [
        ("content", "0001_initial"),
    ]

    operations = [
        migrations.RunPython(seed_layout_presets, reverse_code=unseed_layout_presets),
    ]
