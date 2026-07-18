# WEB-X CMS Guide

Stand: 2026-07-18

## Uebersicht

Das Web-X CMS deckt aktuell folgende Bereiche ab:

- Dashboard
- Beitraege
- Seiten
- Startseite
- Medien
- Karussells
- Veranstaltungen
- Dokumente

## Rollen

- `web_aktuar`: operative CMS-Bearbeitung fuer Inhalte, Veranstaltungen und Publikationsdokumente
- `event_verantwortlich`: Event-Fokus ohne allgemeine CMS-Basis
- `document_verantwortlich`: Dokument-Fokus ohne allgemeine CMS-Basis
- `president` und `system_admin`: enthalten die CMS-Rechte ebenfalls
- normale Mitglieder: kein CMS-Zugriff

## Dashboard

`/cms/` zeigt die Redaktion in einem Arbeitsbereich:

- aktuelle Kennzahlen fuer Posts, Events, Dokumente und Medien
- direkte Einstiege in Editor-Listen
- Ruecklink in den internen Mitgliederbereich

## Seiten

Workflow:

1. `/cms/seiten/` oeffnen
2. bestehende CMS-Seite waehlen
3. Titel, Meta-Angaben und Inhaltsbloecke bearbeiten
4. speichern
5. gespeicherte Vorschau pruefen
6. veroeffentlichen, zurueckziehen oder archivieren
7. Revisionen bei Bedarf wiederherstellen

Aktuell angebundene oeffentliche Seiten:

- `about`
- `join`
- `members`

## Veranstaltungen

Der Event-Editor ist unter `/cms/veranstaltungen/` erreichbar.

- Status: Entwurf, Review, geplant, veroeffentlicht, abgesagt, abgeschlossen, archiviert
- Sichtbarkeit: oeffentlich, Mitglieder, ausgewaehlte Gruppen, ausgewaehlte Benutzer
- Vorschau und Revisionen sind intern verfuegbar
- oeffentliche Anlaesse erscheinen unter `/anlaesse/`
- interne und gruppenbezogene Anlaesse erscheinen unter `/members/events/`
- ICS-Einzeldownloads und Feed-Endpunkte bleiben serverseitig sichtbarkeitsgefiltert

Mehr Details: `docs/EVENTS_CMS.md`

## Dokumente

Der Dokument-Editor ist unter `/cms/dokumente/` erreichbar.

- private Dateien bleiben unter geschuetztem Storage
- neue Uploads erzeugen Versionen
- `publish` setzt das Dokument fuer den freigegebenen Empfaengerkreis sichtbar
- `archive` nimmt das Dokument aus der aktiven Sicht
- der Mitgliederbereich zeigt nur serverseitig freigegebene Dokumente

Mehr Details: `docs/PRIVATE_DOCUMENTS.md`

## Oeffentliche Mitgliederseite

Die bestehende Mitgliederseite wurde auf eine strukturierte CMS-Seite migriert:

- `Page.page_key = members`
- `people_list`-Bloecke fuer Aktivitas, Salon, Fuxenstall und Altherrenschaft
- oeffentliche Personendaten liegen in `members.PublicMemberProfile`
- private Benutzerfelder aus dem Mitgliederbereich werden dort nicht ausgespielt

Mehr Details: `docs/MEMBERS_PUBLIC_PAGE.md`

## Import-Commands

Vorhandene statische Inhalte werden mit idempotenten Commands in die CMS-Modelle uebernommen:

```text
uv run python manage.py import_existing_public_pages
uv run python manage.py import_existing_members_page
```

Option:

- `--dry-run`

## Link-Regeln

CMS-Links duerfen folgende Formen haben:

- absolute `http://`- und `https://`-URLs
- site-relative Pfade wie `/mitglied-werden/`
- `mailto:`-Links
- `tel:`-Links

## Tests

- serverseitige View- und Berechtigungstests
- Browser-E2E fuer Events, Dokumente und die Mitgliederseite

Mehr Details: `docs/BROWSER_E2E_TESTS.md`
