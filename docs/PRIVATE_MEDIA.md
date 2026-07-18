# Private Media

Stand: 2026-07-18

## Warum Profilbilder privat sind

Mitglieder-Profilbilder gehoeren nicht in einen frei erratbaren oeffentlichen `/media/`-Pfad. Sie sind Teil des geschuetzten Mitgliederbereichs und muessen serverseitig authorisiert ausgeliefert werden.

## Speicherort

- oeffentliche CMS-Medien bleiben unter `PUBLIC_MEDIA_ROOT`
- Mitglieder-Profilbilder liegen ueber `PrivateMemberPhotoStorage` unter `PRIVATE_MEDIA_ROOT`
- `MemberProfile.profile_photo` verwendet kein oeffentliches `base_url`

## Auslieferung

- Django-Endpunkt: `/members/profile-images/<profile_id>/`
- anonyme Benutzer: kein Direktzugriff
- aktive berechtigte Benutzer: Zugriff nur nach serverseitiger Pruefung
- Development: `FileResponse` nach erfolgreicher Berechtigungspruefung
- Production-Vorbereitung: optional `X-Accel-Redirect` ueber
  - `DJANGO_PRIVATE_MEDIA_USE_X_ACCEL_REDIRECT`
  - `DJANGO_PRIVATE_MEDIA_ACCEL_REDIRECT_PREFIX`

## Berechtigungen

Ein Profilbild wird nur ausgeliefert, wenn alle folgenden Bedingungen erfuellt sind:

1. Benutzer ist angemeldet.
2. Benutzerkonto ist aktiv.
3. Profil existiert.
4. Bilddatei existiert im privaten Storage.
5. Benutzer ist entweder:
   - der Profilinhaber selbst
   - Superuser
   - Benutzer mit `members.manage_member_profiles`
   - oder ein aktives Mitglied mit passender `directory_visibility`

Direkte Profil-IDs umgehen diese Pruefung nicht. Nicht berechtigte Zugriffe laufen fuer geschuetzte Profile nicht auf eine Rohdatei-URL durch.

## Templates

Direkte Verwendungen von `profile_photo.url` wurden fuer Members-/Accounts-Ansichten ersetzt durch:

- `{% url "members:profile_photo" profile.pk %}`

Es gibt keinen oeffentlichen Rohdatei-Fallback mehr.

## Migration bestehender Dateien

Command:

```text
uv run python manage.py migrate_profile_photos_to_private_storage
```

Verfuegbare Optionen:

- `--dry-run`
- `--keep-source`

Verhalten:

- prueft bestehende Dateireferenzen
- kopiert nur, wenn das Ziel noch nicht existiert
- validiert die Zieldatei nach dem Kopieren
- loescht die Quelle erst nach erfolgreicher Validierung, ausser `--keep-source` ist gesetzt
- ist idempotent fuer bereits privat migrierte Dateien

## Development vs. Production

- Development:
  - `DEBUG` kann weiterhin oeffentliche CMS-Medien ueber `/media/` ausliefern
  - Profilbilder laufen trotzdem ueber den geschuetzten Members-Endpunkt
- Production:
  - kein oeffentlicher Alias auf `PRIVATE_MEDIA_ROOT`
  - geschuetzte Profilbilder entweder ueber Django oder ueber einen internen Nginx-Endpunkt ausliefern

## Backups und Restore

- vor einer produktiven Migration Backup von `PUBLIC_MEDIA_ROOT`, `PRIVATE_MEDIA_ROOT` und Datenbank erstellen
- Restore muss Dateisystem und Datenbank gemeinsam betrachten
- `--dry-run` vor einer echten Verschiebung zuerst ausfuehren
