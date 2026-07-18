from __future__ import annotations

from pathlib import Path

from django.conf import settings
from django.core.files import File
from django.core.management.base import BaseCommand

from apps.members.models import MemberProfile


class Command(BaseCommand):
    help = (
        "Migriert bestehende Mitglieder-Profilbilder aus dem oeffentlichen "
        "Media-Root ins private Profilfoto-Storage."
    )

    def add_arguments(self, parser):
        parser.add_argument(
            "--dry-run",
            action="store_true",
            help="Zeigt nur an, welche Dateien migriert wuerden.",
        )
        parser.add_argument(
            "--keep-source",
            action="store_true",
            help="Behaelt die Quelldatei nach erfolgreicher Migration als Backup.",
        )

    def handle(self, *args, **options):
        dry_run = options["dry_run"]
        keep_source = options["keep_source"]
        storage = MemberProfile._meta.get_field("profile_photo").storage
        public_root = Path(settings.PUBLIC_MEDIA_ROOT)

        processed = 0
        migrated = 0
        skipped = 0
        conflicts = 0
        missing = 0

        self.stdout.write(
            self.style.WARNING(
                "Vor der Migration sollte ein Backup von public media und "
                "private media vorhanden sein."
            )
        )

        for profile in MemberProfile.objects.exclude(profile_photo="").select_related("user"):
            processed += 1
            name = profile.profile_photo.name
            source_path = public_root / name
            destination_exists = storage.exists(name)

            if destination_exists and not source_path.exists():
                skipped += 1
                self.stdout.write(
                    "Bereits privat vorhanden: "
                    f"Profil {profile.pk} ({profile.display_name}) -> {name}"
                )
                continue

            if not source_path.exists() and not destination_exists:
                missing += 1
                self.stdout.write(
                    self.style.ERROR(
                        "Quelle fehlt fuer Profil "
                        f"{profile.pk} ({profile.display_name}): {source_path}"
                    )
                )
                continue

            if destination_exists and source_path.exists():
                conflicts += 1
                self.stdout.write(
                    self.style.ERROR(
                        "Zieldatei existiert bereits, Quelle wird nicht ueberschrieben: "
                        f"Profil {profile.pk} ({profile.display_name}) -> {name}"
                    )
                )
                continue

            if dry_run:
                migrated += 1
                self.stdout.write(
                    "[dry-run] Wuerde migrieren: "
                    f"Profil {profile.pk} ({profile.display_name}) -> {name}"
                )
                continue

            with source_path.open("rb") as source_handle:
                storage.save(name, File(source_handle))

            if not storage.exists(name):
                raise RuntimeError(
                    "Zieldatei konnte nicht validiert werden: "
                    f"Profil {profile.pk} ({profile.display_name})"
                )

            profile.save(update_fields=["updated_at"])
            migrated += 1
            self.stdout.write(
                self.style.SUCCESS(
                    f"Migriert: Profil {profile.pk} ({profile.display_name}) -> {name}"
                )
            )

            if not keep_source:
                source_path.unlink()

        self.stdout.write("")
        self.stdout.write(
            "Zusammenfassung: "
            f"geprueft={processed}, migriert={migrated}, "
            f"bereits-privat={skipped}, konflikte={conflicts}, fehlend={missing}"
        )

        if dry_run:
            self.stdout.write("Dry-Run abgeschlossen. Es wurden keine Dateien verschoben.")
