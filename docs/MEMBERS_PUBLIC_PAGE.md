# Oeffentliche Mitgliederseite

Stand: 2026-07-18

## Ziel

Die oeffentliche Mitgliederseite wird nicht aus internen Mitgliederprofilen gerendert, sondern aus einer expliziten oeffentlichen Projektion.

## Datenmodell

- CMS-Seite: `content.Page` mit `page_key = members`
- Inhaltsbloecke: `people_list` und `cta`
- oeffentliche Personen: `members.PublicMemberProfile`

## Gruppierungen

- `aktivitas_committee`
- `salon`
- `fuxenstall`
- `altherren_committee`

## Import

```text
uv run python manage.py import_existing_members_page
```

Option:

- `--dry-run`

Der Import ist idempotent:

- bestehende oeffentliche Personen werden nicht dupliziert
- eine bereits gepflegte CMS-Seite `members` wird nicht still ueberschrieben

## Datenschutzgrenze

- interne `MemberProfile`-Daten bleiben intern
- private E-Mail-Adressen, Telefonnummern oder andere interne Felder werden nicht auf die oeffentliche Mitgliederseite gespiegelt
- nur explizit freigegebene `PublicMemberProfile`-Eintraege werden gerendert

## CMS-Bearbeitung

Die Seite ist danach normal ueber `/cms/seiten/` bearbeitbar:

- Titel
- Reihenfolge der Gruppen
- CTA-Inhalt
- Vorschau, Publish und Revisionen

## Tests

- `tests/test_public_pages.py`
- `tests/e2e/test_critical_workflows.py`
