# WEB-X CMS: Aktueller Stand

Stand: 2026-07-18

## Kurzfazit

Das Repository ist nicht mehr nur eine Django-Basis mit vorbereiteten CMS-Modellen, sondern ein funktionierender Web-X-CMS-Stand fuer die wichtigsten Vereinsablaeufe. Inhalte, Veranstaltungen, Dokumente und die oeffentliche Mitgliederseite sind strukturiert modelliert, serverseitig abgesichert und ueber Redaktionsoberflaechen nutzbar.

## Produktiver Stand im Branch

### Oeffentliche und interne Inhaltsbereiche

- Startseite, News, `about`, `join` und `members` rendern aus CMS-Daten
- Veranstaltungen haben oeffentliche und interne Ausspielpfade
- der interne Mitgliederbereich verlinkt direkt auf CMS-, Event- und Dokument-Workflows

### Redaktionsoberflaechen

- CMS-Dashboard mit Kennzahlen und Schnellzugriffen
- Editoren fuer Beitraege, Seiten, Startseite, Karussells, Veranstaltungen und Dokumente
- Preview-, Publish-, Withdraw-, Archive- und Restore-Workflows
- Revisionshistorie fuer strukturierte Inhalte

### Geschuetzte Datenfluesse

- Profilbilder liegen unter `PRIVATE_MEDIA_ROOT`
- Dokumentversionen liegen unter geschuetztem Storage
- Downloads und Bildauslieferungen laufen serverseitig authorisiert
- sensible Dokumente bleiben von normalen Mitgliederdokumenten getrennt

### Oeffentliche Mitgliederseite

- `Page.page_key = members` ist an eine strukturierte CMS-Seite gebunden
- `people_list`-Bloecke erlauben freigegebene Gruppenansichten
- oeffentliche Personendaten werden ueber `members.PublicMemberProfile` getrennt gepflegt

## Relevante Management-Commands

- `uv run python manage.py bootstrap_roles`
- `uv run python manage.py import_existing_public_pages`
- `uv run python manage.py import_existing_members_page`
- `uv run python manage.py migrate_profile_photos_to_private_storage`

## Relevante Qualitaetsabdeckung

- serverseitige Modell-, View- und Berechtigungstests
- gezielte Authorisierungstests fuer sensible Dokumente und CMS-Zugriffe
- Browser-E2E fuer Veranstaltungen, Dokumente und die oeffentliche Mitgliederseite

## Verbleibende Restpunkte

- verifizierte Rechts- und Hosting-Fakten fuer `impressum` und `datenschutz`
- produktive Reverse-Proxy-Integration fuer private Medien und Downloads
- CI-Pipeline fuer die lokalen Quality-Gates
