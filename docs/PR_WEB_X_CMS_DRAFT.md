# PR Draft: Web-X CMS finalisieren, absichern und fuer Production vorbereiten

## Titel

`feature/web-x-block-cms` -> `main`

## Zusammenfassung

Dieser Branch schliesst die vorgesehenen Web-X-CMS-Ausbauschritte fuer den Django-Stand weitgehend ab:

1. private Mitglieder-Profilbilder mit serverseitiger Authorisierung
2. wiederverwendbarer CMS-Seiteneditor fuer `Page` und `PageSection`
3. strukturierte CMS-Anbindung fuer `about`, `join` und `members`
4. CMS-Workflows fuer Veranstaltungen inklusive Sichtbarkeiten und ICS
5. private Dokumente mit geschuetztem Storage, Versionen und Rollenpruefung
6. Browser-E2E fuer die kritischsten Redaktionsablaeufe

Zusaetzlich wurden Link-Validierung, Berechtigungsmatrix, Deployment-Dokumentation und Abschlussberichte aktualisiert.

## Wesentliche Aenderungen

- geschuetzte Profilbild-Auslieferung ueber `/members/profile-images/<profile_id>/`
- private Dokumente unter `/cms/dokumente/` mit Versionen, Publikation, Archivierung und serverseitigem Download-Schutz
- CMS-Veranstaltungen unter `/cms/veranstaltungen/` mit Sichtbarkeit fuer oeffentlich, Mitglieder, Gruppen und einzelne Benutzer
- Mitglieder-Dokumentenbereich unter `/members/documents/` mit Trennung zwischen allgemeinen und sensiblen Inhalten
- strukturierte Mitgliederseite auf Basis von `Page.page_key = members` und `people_list`-Bloecken
- oeffentliche Personendaten in `members.PublicMemberProfile` statt direkter Wiederverwendung interner Profildaten
- Browser-E2E fuer Veranstaltungen, Dokumente und die oeffentliche Mitgliederseite
- serverseitige URL-Validierung fuer relative Pfade, `mailto:` und `tel:`
- Regression-Fix fuer leere Inline-Formulare im CMS-Seiteneditor

## Neue Migrationen

- `apps/content/migrations/0003_allow_people_list_on_standard_page.py`
- `apps/content/migrations/0004_alter_carouselitem_link_url_and_more.py`
- `apps/documents/migrations/0001_initial.py`
- `apps/events/migrations/0001_initial.py`
- `apps/members/migrations/0006_publicmemberprofile.py`

## Neue Management-Commands

- `uv run python manage.py migrate_profile_photos_to_private_storage`
- `uv run python manage.py import_existing_public_pages`
- `uv run python manage.py import_existing_members_page`
- `uv run python manage.py bootstrap_roles`

## Testergebnisse

- `git diff --check`: erfolgreich
- `uv run ruff check .`: erfolgreich
- `uv run python manage.py check`: erfolgreich
- `uv run python manage.py makemigrations --check`: erfolgreich
- `uv run python manage.py migrate`: erfolgreich
- `uv run pytest -q`: `97 passed`
- `uv run coverage run -m pytest`: erfolgreich
- `uv run coverage report`: `82%`
- `uv run pytest tests/e2e -q`: `3 passed`

## `check --deploy`

Ausgefuehrt mit `config.settings.production` und sicheren Testwerten.

Verbleibende erwartete Warnungen:

- `security.W005` fuer `SECURE_HSTS_INCLUDE_SUBDOMAINS`
- `security.W021` fuer `SECURE_HSTS_PRELOAD`

Diese beiden Punkte bleiben bewusst fuer die spaetere echte HTTPS-Produktivfreigabe getrennt steuerbar.

## Bekannte Einschraenkungen

- `impressum` und `datenschutz` enthalten weiterhin sichtbare `TODO`-Platzhalter fuer noch nicht verifizierte Rechts- und Hosting-Fakten
- der Reverse Proxy fuer private Medien und Downloads ist dokumentiert, aber nicht an eine konkrete Produktionsinfrastruktur gebunden
- eine CI-Pipeline fuer die lokalen Quality-Gates ist noch nicht eingerichtet

## Manuelle Deployment-Schritte

1. Produktions-Env-Variablen setzen
2. `uv run python manage.py migrate`
3. `uv run python manage.py bootstrap_roles`
4. `uv run python manage.py import_existing_public_pages`
5. `uv run python manage.py import_existing_members_page`
6. `uv run python manage.py migrate_profile_photos_to_private_storage --dry-run`
7. danach produktive Profilfoto-Migration ohne `--dry-run`
8. `uv run python manage.py collectstatic --noinput`
9. Reverse Proxy fuer HTTPS und private Medien konfigurieren

## Selektiv nicht uebernommene lokale Artefakte

Diese lokalen Artefakte bleiben bewusst ausserhalb der Commits:

- `--check`
- `server_start`
- `uv.lock`

## PR-Status

Der Branch ist fuer Push und anschliessende externe Review vorbereitet. Die eigentliche PR-Erstellung konnte lokal weiterhin nicht automatisiert werden, weil weder `gh` noch ein installiertes GitHub-Plugin verfuegbar waren.
