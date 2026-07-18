# WEB-X CMS Acceptance Test

Stand: 2026-07-18

## Testumgebung

- Windows PowerShell
- `DATABASE_URL=sqlite:///db.sqlite3`
- Django 5.2
- Playwright Chromium fuer Browser-E2E
- Branch: `feature/simplify-web-x-cms`

## Verifizierte Pflichtlaeufe

- `git diff --check`
- `uv run ruff check .`
- `uv run python manage.py check`
- `uv run python manage.py makemigrations --check`
- `uv run python manage.py migrate`
- `uv run pytest -q` -> `110 passed`
- `uv run coverage run -m pytest`
- `uv run coverage report` -> `69%`

## Browser-E2E

Verifiziert am 2026-07-18:

- `uv run pytest tests/e2e -q` -> `5 passed`

Abgedeckte Szenarien:

1. vereinfachter Beitragsworkflow
   - Layout waehlen
   - vier Felder erfassen
   - Entwurf speichern
   - Vorschau pruefen
   - veroeffentlichen
   - Layout auf `Magazin` wechseln
   - auf der Startseite hervorheben
2. geplanter Beitragsworkflow
   - `Fokus` waehlen
   - zukuenftige Veroeffentlichung planen
   - oeffentliche Nicht-Sichtbarkeit vor Termin pruefen
3. CMS-Berechtigungen im Browser
   - normales Mitglied sieht keine CMS-Kachel
   - direkter `/cms/`-Zugriff bleibt verboten
4. Event-CMS
5. Dokument-CMS
6. oeffentliche Mitgliederseite

## Fachlich abgedeckte Bereiche

- vereinfachte Layoutauswahl mit genau drei Post-Layouts
- automatisches Slug-, SEO- und Autoren-Handling
- serverseitige Rich-Text-Sanitization
- vereinfachte Publish- und Schedule-Aktionen ohne Review-Schritt
- Startseitenmarkierung mit genau einem aktuellen Beitrag
- Legacy-Kompatibilitaet fuer bestehende Block-Beitraege
- CMS-Zugriffsschutz und Vorschau-Schutz

## Deploy-Check

Ausgefuehrt:

- `uv run python manage.py check --deploy --settings=config.settings.production`

Verbleibende Warnungen im lokalen Stand:

- `security.W005`
- `security.W009`
- `security.W021`

`security.W009` stammt hier aus dem lokalen Test-Setup mit einem nicht produktiven Secret-Key.
Fuer Production ist weiterhin ein eigener starker Secret-Key noetig.
