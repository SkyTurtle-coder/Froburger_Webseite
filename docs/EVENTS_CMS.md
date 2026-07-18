# Events CMS

Stand: 2026-07-18

## Umfang

Das Event-Modul deckt oeffentliche, interne und zielgruppenspezifische Veranstaltungen ab.

## CMS-Routen

- `/cms/veranstaltungen/`
- `/cms/veranstaltungen/neu/`
- `/cms/veranstaltungen/<pk>/bearbeiten/`
- `/cms/veranstaltungen/<pk>/vorschau/`
- `/cms/veranstaltungen/<pk>/versionen/`

## Status

- `draft`
- `review`
- `scheduled`
- `published`
- `cancelled`
- `completed`
- `archived`

## Sichtbarkeit

- `public`
- `members`
- `selected_groups`
- `selected_users`

## Oeffentliche und interne Ausspielung

- oeffentliche Liste: `/anlaesse/`
- oeffentliche Detailseite: `/anlaesse/<slug>/`
- oeffentlicher Kalenderfeed: `/kalender.ics`
- interne Liste: `/members/events/`
- interner Kalenderfeed: `/members/calendar.ics`

## Wichtige Regeln

- nur freigegebene Empfaenger sehen interne oder gruppenspezifische Anlaesse
- ICS-Downloads folgen derselben Sichtbarkeitslogik wie Listen und Detailseiten
- Vorschauen und Revisionen sind nur intern verfuegbar

## Tests

- serverseitige View- und Workflowtests in `tests/test_events_views.py`
- Browser-E2E in `tests/e2e/test_critical_workflows.py`
