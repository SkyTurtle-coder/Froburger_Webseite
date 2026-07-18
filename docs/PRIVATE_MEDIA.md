# Private Media

Stand: 2026-07-18

## Umfang

Dieses Dokument beschreibt die geschuetzte Behandlung von privaten Medien im weiteren Sinn:

- Mitglieder-Profilbilder
- die Trennung zu oeffentlichen CMS-Medien

Private Dokumente sind separat beschrieben in `docs/PRIVATE_DOCUMENTS.md`.

## Warum Profilbilder privat sind

Mitglieder-Profilbilder gehoeren nicht in einen frei erratbaren oeffentlichen `/media/`-Pfad. Sie sind Teil des geschuetzten Mitgliederbereichs und muessen serverseitig authorisiert ausgeliefert werden.

## Speicherort

- oeffentliche CMS-Medien bleiben unter `PUBLIC_MEDIA_ROOT`
- Mitglieder-Profilbilder liegen ueber `PrivateMemberPhotoStorage` unter `PRIVATE_MEDIA_ROOT`
- `MemberProfile.profile_photo` verwendet keinen oeffentlichen `base_url`

## Auslieferung

- Django-Endpunkt: `/members/profile-images/<profile_id>/`
- anonyme Benutzer: kein Direktzugriff
- berechtigte Benutzer: Zugriff nur nach serverseitiger Pruefung
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

## Migration bestehender Dateien

```text
uv run python manage.py migrate_profile_photos_to_private_storage
```

Verfuegbare Optionen:

- `--dry-run`
- `--keep-source`

## Production-Hinweis

Ein oeffentlicher Webserver-Alias fuer `PRIVATE_MEDIA_ROOT` ist unzulaessig.
