# Private Documents

Stand: 2026-07-18

## Umfang

Das Dokumentmodul verwaltet geschuetzte Dateien fuer den Mitgliederbereich inklusive Versionierung und serverseitiger Download-Autorisierung.

## Speicher und Delivery

- Dateiablage ueber privates Storage
- keine direkte oeffentliche Rohdatei-URL
- Download-Endpunkt:
  - `/members/documents/<pk>/download/`

## CMS-Routen

- `/cms/dokumente/`
- `/cms/dokumente/neu/`
- `/cms/dokumente/<pk>/bearbeiten/`
- `/cms/dokumente/<pk>/versionen/`

## Sichtbarkeiten

- `all_members`
- `selected_groups`
- `selected_users`
- `functionaries`
- `highly_sensitive`

## Berechtigungsmodell

- `web_aktuar` darf Publikationsdokumente verwalten
- `document_verantwortlich` darf Publikationsdokumente verwalten
- `president` und `system_admin` duerfen auch hochsensible Dokumente verwalten
- der sensible Mitgliederbereich ist nur mit entsprechender Berechtigung oder Bursch-Rolle sichtbar

## Mitgliederbereich

- allgemeine Dokumente: `/members/documents/`
- sensible Dokumente: `/members/documents/sensitive/`
- Listen und Downloads werden serverseitig pro Benutzer gefiltert

## Sicherheitsdetails

- Uploadvalidierung blockiert gefaehrliche Dateiendungen
- Download-Responses setzen `X-Content-Type-Options: nosniff`
- Archivieren nimmt Dateien aus der aktiven Sicht, loescht sie aber nicht implizit

## Tests

- `tests/test_documents_views.py`
- `tests/e2e/test_critical_workflows.py`
