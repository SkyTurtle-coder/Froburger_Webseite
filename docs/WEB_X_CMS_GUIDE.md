# WEB-X CMS Guide

Stand: 2026-07-18

## Uebersicht

Das Web-X CMS deckt aktuell folgende Bereiche ab:

- Dashboard
- Beitraege
- generische Seiten
- Startseite
- Medien
- Karussells

## Rollen

- `web_aktuar`: operative CMS-Bearbeitung
- `president` und `system_admin`: enthalten die CMS-Rechte ebenfalls
- normale Mitglieder: kein CMS-Zugriff

## Beitraege

Workflow:

1. `/cms/beitraege/` oeffnen
2. Beitrag anlegen oder bearbeiten
3. Layout, Titel, Teaser und Bloecke pflegen
4. speichern
5. Vorschau pruefen
6. veroeffentlichen, planen oder zurueckziehen
7. Revisionen bei Bedarf wiederherstellen

## Seiten

Workflow:

1. `/cms/seiten/` oeffnen
2. bestehende CMS-Seite waehlen
3. Seitentitel, Meta-Angaben und Inhaltsbloecke bearbeiten
4. speichern
5. Vorschau pruefen
6. veroeffentlichen oder zurueckziehen
7. Revisionen bei Bedarf wiederherstellen

Aktuell sind besonders vorbereitet:

- `about`
- `join`
- `homepage`

## Startseite

Die Startseite bleibt eine eigene Systemseite mit festem Mapping fuer:

- Copy-Bereich
- Rueckblicksbereich
- optionales Karussell
- angepinnte publizierte Beitraege

## Medien

- nur freigegebene Bildtypen
- Alt-Text fuer nicht dekorative Bilder erforderlich
- private Medienverwaltung nur fuer entsprechend berechtigte Benutzer

## Import bestehender Seiten

Command:

```text
uv run python manage.py import_existing_public_pages
```

Option:

- `--dry-run`

Der Command legt `about` und `join` nur an, wenn sie noch nicht mit CMS-Inhalten gepflegt wurden.
