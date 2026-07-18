from __future__ import annotations

from dataclasses import dataclass

from django.core.management.base import BaseCommand
from django.utils import timezone

from apps.content.models import Page, PageSection, PublishableStatus, Visibility
from apps.content.services import get_block_layout, get_page_layout


@dataclass(frozen=True)
class SectionSeed:
    block_type: str
    layout_key: str
    position: int
    eyebrow: str
    heading: str
    body: str
    anchor_id: str
    link_label: str = ""
    link_url: str = ""


PAGE_SEEDS = {
    "about": {
        "title": "Ueber uns",
        "slug": "ueber-uns",
        "meta_title": "Ueber uns - AV Froburger",
        "meta_description": "Geschichte, Couleur und Hochschulumfeld der AV Froburger.",
        "sections": [
            SectionSeed(
                block_type=PageSection.BlockType.TEXT,
                layout_key="text_only",
                position=10,
                eyebrow="Allgemein FB!",
                heading="Eine Verbindung mit Geschichte und Zukunft",
                body=(
                    "Die AV Froburger ist eine akademische Verbindung im Schweizerischen "
                    "Studentenverein. Sie bringt Studierende und Ehemalige zusammen, "
                    "foerdert den persoenlichen Austausch und schafft ein Netzwerk, das "
                    "weit ueber die Studienzeit hinausreicht.\n\n"
                    "Als Teil des Schw. StV bekennen wir uns zu demokratischer Verantwortung, "
                    "gegenseitigem Respekt und einem offenen Dialog. Die Verbindung ist "
                    "parteipolitisch unabhaengig. Persoenliche Ueberzeugungen werden "
                    "respektiert und reflektiert diskutiert."
                ),
                anchor_id="about-intro",
            ),
            SectionSeed(
                block_type=PageSection.BlockType.CTA,
                layout_key="cta_primary",
                position=20,
                eyebrow="Mitglied werden",
                heading="Offen fuer Studierende in der Region Basel",
                body=(
                    "Mitglied werden koennen Schweizer Studierende der Universitaet Basel "
                    "sowie Studierende der FHNW an Standorten in Basel-Stadt und Basel-Landschaft. "
                    "Ebenso willkommen sind internationale Studierende aus der Region Basel, "
                    "die in der Schweiz wohnen.\n\n"
                    "Auch fuer internationale Studierende mit Wohnsitz im Ausland und im Rahmen "
                    "der Abkommen des Schweizerischen Studentenvereins bestehen "
                    "Aufnahmemoeglichkeiten, die gemeinsam mit dem "
                    "Zentralkomitee abgestimmt werden."
                ),
                anchor_id="about-membership",
                link_label="Interesse anmelden",
                link_url="/mitglied-werden/",
            ),
            SectionSeed(
                block_type=PageSection.BlockType.NOTICE,
                layout_key="notice_highlight",
                position=30,
                eyebrow="Geschichte",
                heading="Die ersten Froburger",
                body=(
                    "Studierende aus der Region Olten schaffen eine Gemeinschaft "
                    "auf der Grundlage von Freundschaft und Verantwortung."
                ),
                anchor_id="about-history-1",
            ),
            SectionSeed(
                block_type=PageSection.BlockType.NOTICE,
                layout_key="notice_highlight",
                position=40,
                eyebrow="20. Jahrhundert",
                heading="Generationen im Austausch",
                body=(
                    "Die Verbindung waechst und pflegt ihre Beziehungen innerhalb "
                    "des Schweizerischen Studentenvereins."
                ),
                anchor_id="about-history-2",
            ),
            SectionSeed(
                block_type=PageSection.BlockType.NOTICE,
                layout_key="notice_highlight",
                position=50,
                eyebrow="Heute",
                heading="Tradition zeitgemaess leben",
                body=(
                    "Aktivitas und Altherrenschaft gestalten gemeinsam ein vielseitiges "
                    "Semesterprogramm in Basel und Olten."
                ),
                anchor_id="about-history-3",
            ),
            SectionSeed(
                block_type=PageSection.BlockType.TEXT,
                layout_key="text_only",
                position=60,
                eyebrow="Unsere Farben",
                heading="Orange, Weiss und Gruen",
                body=(
                    "Die Couleur ist sichtbares Zeichen unserer Zugehoerigkeit. Ihre drei Farben "
                    "werden in dieser festen Reihenfolge getragen und praegen den visuellen "
                    "Auftritt der AV Froburger.\n\n"
                    "Orange - Freundschaft · Amicitia\n"
                    "Weiss - Wissenschaft · Scientia\n"
                    "Gruen - Tugend · Virtus\n\n"
                    "Voluntate forti viam rectam!"
                ),
                anchor_id="about-colours",
            ),
            SectionSeed(
                block_type=PageSection.BlockType.CTA,
                layout_key="cta_primary",
                position=70,
                eyebrow="Dachverband",
                heading="Der Schweizerische Studentenverein",
                body=(
                    "Der Schweizerische Studentenverein verbindet akademische Gemeinschaften "
                    "in der ganzen Schweiz. Er foerdert den Austausch ueber Sprach-, "
                    "Hochschul- und Generationengrenzen hinweg."
                ),
                anchor_id="about-stv",
                link_label="Zum Schw. StV",
                link_url="https://schw-stv.ch",
            ),
            SectionSeed(
                block_type=PageSection.BlockType.CTA,
                layout_key="cta_primary",
                position=80,
                eyebrow="Unser Netzwerk",
                heading="Paten- und Platzverbindungen",
                body=(
                    "Freundschaft endet nicht an den Grenzen der eigenen Verbindung. "
                    "Wir pflegen den Austausch mit unserer Patenverbindung und mit "
                    "befreundeten Verbindungen am Hochschulplatz Basel."
                ),
                anchor_id="about-network",
                link_label="Kontakt aufnehmen",
                link_url="mailto:info@avfroburger.ch?subject=Frage%20zu%20den%20Platzverbindungen",
            ),
            SectionSeed(
                block_type=PageSection.BlockType.CTA,
                layout_key="cta_primary",
                position=90,
                eyebrow="Hochschule",
                heading="Universitaet Basel",
                body=(
                    "Die 1460 gegruendete Universitaet Basel ist die aelteste Universitaet "
                    "der Schweiz. Die Vielfalt der Studienrichtungen bereichert den Austausch "
                    "innerhalb der Verbindung."
                ),
                anchor_id="about-unibas",
                link_label="Zur Universitaet Basel",
                link_url="https://www.unibas.ch",
            ),
            SectionSeed(
                block_type=PageSection.BlockType.CTA,
                layout_key="cta_primary",
                position=100,
                eyebrow="Fachhochschule",
                heading="Fachhochschule Nordwestschweiz",
                body=(
                    "Die FHNW verbindet Lehre, Forschung, Weiterbildung und "
                    "Dienstleistungen mit konsequentem Praxisbezug. In den beiden Basel "
                    "ist sie unter anderem am Campus Muttenz praesent."
                ),
                anchor_id="about-fhnw",
                link_label="Zur FHNW",
                link_url="https://www.fhnw.ch/de/die-fhnw/standorte",
            ),
        ],
    },
    "join": {
        "title": "Mitglied werden",
        "slug": "mitglied-werden",
        "meta_title": "Mitglied werden - AV Froburger",
        "meta_description": (
            "Mitglied werden bei der AV Froburger und unkompliziert "
            "Kontakt aufnehmen."
        ),
        "sections": [
            SectionSeed(
                block_type=PageSection.BlockType.NOTICE,
                layout_key="notice_highlight",
                position=10,
                eyebrow="Dein Einstieg",
                heading="Schoen, dass wir dein Interesse geweckt haben!",
                body=(
                    "Ob erste Frage, spontaner Besuch oder direkter Draht zur Aktivitas: "
                    "Melde dich so, wie es fuer dich am einfachsten ist."
                ),
                anchor_id="join-intro",
            ),
            SectionSeed(
                block_type=PageSection.BlockType.CTA,
                layout_key="cta_primary",
                position=20,
                eyebrow="Klassisch und direkt",
                heading="Per Mail",
                body=(
                    "Schreib uns kurz, wer du bist und was dich an der AV Froburger interessiert. "
                    "Wir antworten dir persoenlich.\n\ninfo@avfroburger.ch"
                ),
                anchor_id="join-mail",
                link_label="Mail senden",
                link_url="mailto:info@avfroburger.ch?subject=Mitglied%20werden",
            ),
            SectionSeed(
                block_type=PageSection.BlockType.CTA,
                layout_key="cta_primary",
                position=30,
                eyebrow="Schnell im Alltag",
                heading="DM bei Instagram",
                body=(
                    "Wenn du lieber locker einsteigen willst, schreib uns direkt eine Nachricht "
                    "auf Instagram.\n\n@av_froburger"
                ),
                anchor_id="join-instagram",
                link_label="Instagram oeffnen",
                link_url="https://www.instagram.com/av_froburger/",
            ),
            SectionSeed(
                block_type=PageSection.BlockType.CTA,
                layout_key="cta_primary",
                position=40,
                eyebrow="Direkt zur Aktivitas",
                heading="WhatsApp-Kontakt anfragen",
                body=(
                    "Wenn du direkt mit dem Senior schreiben moechtest, schicken wir dir den "
                    "WhatsApp-Kontakt von Newton persoenlich zu."
                ),
                anchor_id="join-whatsapp",
                link_label="Kontakt anfragen",
                link_url="mailto:info@avfroburger.ch?subject=WhatsApp%20Kontakt%20zum%20Senior",
            ),
            SectionSeed(
                block_type=PageSection.BlockType.NOTICE,
                layout_key="notice_highlight",
                position=50,
                eyebrow="01",
                heading="Kontakt aufnehmen",
                body="Schreib uns per Mail, DM oder frage den WhatsApp-Kontakt beim Senior an.",
                anchor_id="join-step-1",
            ),
            SectionSeed(
                block_type=PageSection.BlockType.NOTICE,
                layout_key="notice_highlight",
                position=60,
                eyebrow="02",
                heading="Anlass besuchen",
                body=(
                    "Lerne uns an einem Anlass oder im persoenlichen Austausch "
                    "unverbindlich kennen."
                ),
                anchor_id="join-step-2",
            ),
            SectionSeed(
                block_type=PageSection.BlockType.NOTICE,
                layout_key="notice_highlight",
                position=70,
                eyebrow="03",
                heading="In Ruhe entscheiden",
                body=(
                    "Wenn es fuer beide Seiten passt, besprechen wir gemeinsam "
                    "die naechsten Schritte."
                ),
                anchor_id="join-step-3",
            ),
            SectionSeed(
                block_type=PageSection.BlockType.TEXT,
                layout_key="text_only",
                position=80,
                eyebrow="Gut zu wissen",
                heading="Offen fuer Studierende in der Region Basel",
                body=(
                    "Mitglied werden koennen Schweizer Studierende der Universitaet Basel "
                    "sowie Studierende der FHNW an Standorten in Basel-Stadt und Basel-Landschaft. "
                    "Ebenso willkommen sind internationale Studierende aus der Region Basel, "
                    "die in der Schweiz wohnen.\n\n"
                    "Universitaet Basel\n"
                    "FHNW in Basel-Stadt und Basel-Landschaft\n"
                    "Internationale Studierende in der Region Basel"
                ),
                anchor_id="join-eligibility",
            ),
            SectionSeed(
                block_type=PageSection.BlockType.CTA,
                layout_key="cta_primary",
                position=90,
                eyebrow="Erstes Kennenlernen",
                heading="Unverbindlich und persoenlich.",
                body=(
                    "Du musst dich nicht sofort entscheiden. Ein erster Besuch oder ein "
                    "kurzes Gespraech reichen voellig, um ein Gefuehl fuer die AV Froburger "
                    "zu bekommen."
                ),
                anchor_id="join-note",
                link_label="Aktivitas ansehen",
                link_url="/mitglieder/",
            ),
        ],
    },
}


class Command(BaseCommand):
    help = (
        "Importiert die bestehenden Inhalte fuer 'Ueber uns' und 'Mitglied werden' "
        "in die CMS-Modelle, ohne spaetere manuelle Aenderungen zu ueberschreiben."
    )

    def add_arguments(self, parser):
        parser.add_argument(
            "--dry-run",
            action="store_true",
            help="Zeigt nur an, welche Seiten angelegt wuerden.",
        )

    def handle(self, *args, **options):
        dry_run = options["dry_run"]
        for page_key, seed in PAGE_SEEDS.items():
            existing = Page.objects.filter(page_key=page_key).first()
            if existing and existing.sections.exists():
                self.stdout.write(
                    f"Uebersprungen: {page_key} existiert bereits mit CMS-Inhalten."
                )
                continue

            if dry_run:
                action = "anlegen" if existing is None else "vervollstaendigen"
                self.stdout.write(f"[dry-run] Wuerde Seite {page_key} {action}.")
                continue

            page, created = Page.objects.get_or_create(
                page_key=page_key,
                defaults={
                    "title": seed["title"],
                    "slug": seed["slug"],
                    "page_type": Page.PageType.EDITORIAL,
                    "layout_preset": get_page_layout("standard_page"),
                    "status": PublishableStatus.PUBLISHED,
                    "visibility": Visibility.PUBLIC,
                    "published_at": timezone.now(),
                    "meta_title": seed["meta_title"],
                    "meta_description": seed["meta_description"],
                    "version_number": 1,
                },
            )

            if not created:
                page.title = page.title or seed["title"]
                page.slug = page.slug or seed["slug"]
                page.layout_preset = page.layout_preset or get_page_layout("standard_page")
                page.meta_title = page.meta_title or seed["meta_title"]
                page.meta_description = page.meta_description or seed["meta_description"]
                page.status = page.status or PublishableStatus.PUBLISHED
                page.visibility = page.visibility or Visibility.PUBLIC
                page.published_at = page.published_at or timezone.now()
                page.save()

            if page.sections.exists():
                self.stdout.write(
                    "Uebersprungen: "
                    f"{page_key} hat bereits Seitenbloecke und wird nicht "
                    "ueberschrieben."
                )
                continue

            PageSection.objects.bulk_create(
                [
                    PageSection(
                        page=page,
                        block_type=section.block_type,
                        layout_preset=get_block_layout(section.layout_key),
                        position=section.position,
                        eyebrow=section.eyebrow,
                        heading=section.heading,
                        body=section.body,
                        anchor_id=section.anchor_id,
                        link_label=section.link_label,
                        link_url=section.link_url,
                        is_active=True,
                        options={},
                    )
                    for section in seed["sections"]
                ]
            )
            page.create_revision(
                actor=None,
                reason="Initialer Import bestehender oeffentlicher Seite",
            )
            self.stdout.write(self.style.SUCCESS(f"Importiert: {page_key}"))
