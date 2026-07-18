from __future__ import annotations

from dataclasses import dataclass

from django.core.management.base import BaseCommand
from django.utils import timezone

from apps.content.models import Page, PageSection, PublishableStatus, Visibility
from apps.content.services import get_block_layout, get_page_layout
from apps.members.models import PublicMemberProfile


@dataclass(frozen=True)
class PersonSeed:
    external_key: str
    group_key: str
    sort_order: int
    display_name: str
    vulgar_name: str = ""
    function_title: str = ""
    short_description: str = ""


@dataclass(frozen=True)
class SectionSeed:
    block_type: str
    layout_key: str
    position: int
    eyebrow: str
    heading: str
    body: str
    anchor_id: str
    options: dict
    link_label: str = ""
    link_url: str = ""


GROUP = PublicMemberProfile.GroupKey


PERSON_SEEDS = (
    PersonSeed(
        "aktivitas-newton",
        GROUP.AKTIVITAS_COMMITTEE,
        10,
        "Philipp Thuerlemann",
        "Newton",
        "Senior",
    ),
    PersonSeed(
        "aktivitas-eirene",
        GROUP.AKTIVITAS_COMMITTEE,
        20,
        "Magdalena Friedl",
        "Eirene",
        "Consenior",
    ),
    PersonSeed(
        "aktivitas-jade",
        GROUP.AKTIVITAS_COMMITTEE,
        30,
        "Celine Stier",
        "Jade",
        "Fuxmajor",
    ),
    PersonSeed(
        "aktivitas-duro",
        GROUP.AKTIVITAS_COMMITTEE,
        40,
        "Sandro Wicki",
        "Duro",
        "Aktuar",
    ),
    PersonSeed(
        "aktivitas-caruso",
        GROUP.AKTIVITAS_COMMITTEE,
        50,
        "Andre Spiess",
        "Caruso",
        "Quaestor",
    ),
    PersonSeed("salon-newton", GROUP.SALON, 10, "Philipp Thuerlemann", "Newton"),
    PersonSeed("salon-eirene", GROUP.SALON, 20, "Magdalena Friedl", "Eirene"),
    PersonSeed("salon-jade", GROUP.SALON, 30, "Celine Stier", "Jade"),
    PersonSeed("salon-duro", GROUP.SALON, 40, "Sandro Wicki", "Duro"),
    PersonSeed("salon-caruso", GROUP.SALON, 50, "Andre Spiess", "Caruso"),
    PersonSeed(
        "salon-ragusa",
        GROUP.SALON,
        60,
        "Caroline Schiltknecht, MSc",
        "Ragusa",
    ),
    PersonSeed("salon-grace", GROUP.SALON, 70, "Belana Steinauer, MSc", "Grace"),
    PersonSeed("salon-linse", GROUP.SALON, 80, "Bessy Purayampillil, MA", "Linse"),
    PersonSeed("salon-gavel", GROUP.SALON, 90, "Alina Neuburger, MSc", "Gavel"),
    PersonSeed("salon-neutral", GROUP.SALON, 100, "Clemens Renaux, MSc", "Neutral"),
    PersonSeed(
        "salon-gschwaetzig",
        GROUP.SALON,
        110,
        "Christine Leuthard, MSc",
        "Gschwaetzig",
    ),
    PersonSeed("salon-parzival", GROUP.SALON, 120, "Reto Fluetsch, BSc", "Parzival"),
    PersonSeed("salon-anubis", GROUP.SALON, 130, "Shana Schnider, BA", "Anubis"),
    PersonSeed(
        "fux-inana",
        GROUP.FUXENSTALL,
        10,
        "Melanie Natum, stud. phil. I",
        "Inana",
    ),
    PersonSeed(
        "fux-toffee",
        GROUP.FUXENSTALL,
        20,
        "Mara Kalbermatten, stud. phil. I",
        "Toffee",
    ),
    PersonSeed(
        "fux-helia",
        GROUP.FUXENSTALL,
        30,
        "Elisheba Schmid, stud. phil. I",
        "Helia",
    ),
    PersonSeed("fux-malva", GROUP.FUXENSTALL, 40, "Elizaveta Kotova, BSc", "Malva"),
    PersonSeed(
        "af-bacchus",
        GROUP.ALTHERRN_COMMITTEE,
        10,
        "Sven Cattelan",
        "Bacchus",
        "Praesident",
    ),
    PersonSeed(
        "af-buehrle",
        GROUP.ALTHERRN_COMMITTEE,
        20,
        "Benno Notter",
        "Buehrle",
        "Aktuar",
    ),
    PersonSeed(
        "af-cabernet",
        GROUP.ALTHERRN_COMMITTEE,
        30,
        "Dominik Frei",
        "Cabernet",
        "Kassier",
    ),
)


PAGE_SEED = {
    "title": "Mitglieder",
    "slug": "mitglieder",
    "meta_title": "Mitglieder - AV Froburger",
    "meta_description": "Komitee der Aktivitas, Salon, Fuxenstall und Komitee der Alt-Froburger.",
    "sections": (
        SectionSeed(
            block_type=PageSection.BlockType.PEOPLE_LIST,
            layout_key="people_grid",
            position=10,
            eyebrow="Chargen",
            heading="Komitee der Aktivitas",
            body="",
            anchor_id="committee-aktivitas",
            options={"group_key": PublicMemberProfile.GroupKey.AKTIVITAS_COMMITTEE, "columns": 3},
        ),
        SectionSeed(
            block_type=PageSection.BlockType.PEOPLE_LIST,
            layout_key="people_grid",
            position=20,
            eyebrow="Die Burschen",
            heading="Salon",
            body="",
            anchor_id="salon",
            options={"group_key": PublicMemberProfile.GroupKey.SALON, "columns": 4},
        ),
        SectionSeed(
            block_type=PageSection.BlockType.PEOPLE_LIST,
            layout_key="people_grid",
            position=30,
            eyebrow="Die Fuxen",
            heading="Fuxenstall",
            body="",
            anchor_id="fuxenstall",
            options={"group_key": PublicMemberProfile.GroupKey.FUXENSTALL, "columns": 4},
        ),
        SectionSeed(
            block_type=PageSection.BlockType.PEOPLE_LIST,
            layout_key="people_grid",
            position=40,
            eyebrow="Altherrenschaft",
            heading="Komitee der Alt-Froburger",
            body="",
            anchor_id="altherren",
            options={"group_key": PublicMemberProfile.GroupKey.ALTHERRN_COMMITTEE, "columns": 3},
        ),
        SectionSeed(
            block_type=PageSection.BlockType.CTA,
            layout_key="cta_primary",
            position=50,
            eyebrow="Interesse?",
            heading="Werde Teil unserer Gemeinschaft.",
            body=(
                "Besuche einen Anlass, lerne unsere Mitglieder kennen und finde heraus, "
                "ob die AV Froburger zu dir passt."
            ),
            anchor_id="members-cta",
            options={},
            link_label="Kontakt aufnehmen",
            link_url="/mitglied-werden/",
        ),
    ),
}


class Command(BaseCommand):
    help = (
        "Importiert die bestehende oeffentliche Mitgliederseite in die CMS-Modelle "
        "und legt freigegebene Personenprojektionen idempotent an."
    )

    def add_arguments(self, parser):
        parser.add_argument(
            "--dry-run",
            action="store_true",
            help="Zeigt nur an, welche Personen und Seiten angelegt wuerden.",
        )

    def handle(self, *args, **options):
        dry_run = options["dry_run"]
        created_people = 0

        for person in PERSON_SEEDS:
            existing = PublicMemberProfile.objects.filter(external_key=person.external_key).first()
            if dry_run:
                if existing is None:
                    self.stdout.write(f"[dry-run] Wuerde Person {person.external_key} anlegen.")
                continue
            if existing is not None:
                continue
            PublicMemberProfile.objects.create(
                external_key=person.external_key,
                display_name=person.display_name,
                vulgar_name=person.vulgar_name,
                function_title=person.function_title,
                short_description=person.short_description,
                group_key=person.group_key,
                sort_order=person.sort_order,
                is_active=True,
                is_publicly_approved=True,
            )
            created_people += 1

        existing_page = Page.objects.filter(page_key="members").first()
        if existing_page and existing_page.sections.exists():
            self.stdout.write("Uebersprungen: members existiert bereits mit CMS-Inhalten.")
        elif dry_run:
            action = "anlegen" if existing_page is None else "vervollstaendigen"
            self.stdout.write(f"[dry-run] Wuerde Seite members {action}.")
        else:
            page, created = Page.objects.get_or_create(
                page_key="members",
                defaults={
                    "title": PAGE_SEED["title"],
                    "slug": PAGE_SEED["slug"],
                    "page_type": Page.PageType.EDITORIAL,
                    "layout_preset": get_page_layout("standard_page"),
                    "status": PublishableStatus.PUBLISHED,
                    "visibility": Visibility.PUBLIC,
                    "published_at": timezone.now(),
                    "meta_title": PAGE_SEED["meta_title"],
                    "meta_description": PAGE_SEED["meta_description"],
                    "version_number": 1,
                },
            )
            if not created:
                page.title = page.title or PAGE_SEED["title"]
                page.slug = page.slug or PAGE_SEED["slug"]
                page.layout_preset = page.layout_preset or get_page_layout("standard_page")
                page.meta_title = page.meta_title or PAGE_SEED["meta_title"]
                page.meta_description = page.meta_description or PAGE_SEED["meta_description"]
                page.status = page.status or PublishableStatus.PUBLISHED
                page.visibility = page.visibility or Visibility.PUBLIC
                page.published_at = page.published_at or timezone.now()
                page.save()

            if not page.sections.exists():
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
                            options=section.options,
                        )
                        for section in PAGE_SEED["sections"]
                    ]
                )
                page.create_revision(
                    actor=None,
                    reason="Initialer Import bestehender oeffentlicher Mitgliederseite",
                )
                self.stdout.write(self.style.SUCCESS("Importiert: members"))

        if dry_run:
            return
            self.stdout.write(
                self.style.SUCCESS(f"Oeffentliche Personen neu angelegt: {created_people}")
            )
