# WEB-X CMS Acceptance Test

Stand: 2026-07-18

## Testumgebung

- lokale Windows-Entwicklung
- PowerShell
- Django mit `DATABASE_URL=sqlite:///db.sqlite3`
- Playwright Chromium fuer Browser-E2E

## Verifizierte Pflichtlaeufe

- `git diff --check`
- `uv run ruff check .`
- `uv run python manage.py check`
- `uv run python manage.py makemigrations --check`
- `uv run python manage.py migrate`
- `uv run pytest -q` -> `97 passed`
- `uv run coverage run -m pytest`
- `uv run coverage report` -> `82%`

## Browser-E2E

Automatisierte Browserfluesse:

1. Event-CMS
   - Event erstellen
   - Vorschau pruefen
   - veroeffentlichen
   - oeffentliche Sichtbarkeit und ICS-Download pruefen
   - interne Sichtbarkeit fuer Mitglieder pruefen
2. Dokumente
   - Dokument im CMS erstellen
   - Sichtbarkeit auf Gruppe begrenzen
   - erlaubten Download pruefen
   - verweigerten Direktzugriff pruefen
3. Oeffentliche Mitgliederseite
   - importierte Mitgliederseite im CMS bearbeiten
   - Vorschau und Publish pruefen
   - oeffentliche Ausspielung pruefen
   - interne Kontaktdaten auf Nicht-Sichtbarkeit pruefen

## Fachlich abgedeckte Bereiche

- CMS-Zugriffsmatrix
- Preview- und Revisionsschutz
- Event- und Dokumenten-Workflows
- serverseitige Sichtbarkeitsfilter fuer Mitglieder- und Downloadbereiche
- strukturierte Mitgliederseite mit oeffentlicher Personenprojektion

## Erwartete Restwarnungen

Bei `check --deploy --settings=config.settings.production` bleiben bewusst offen:

- `security.W005`
- `security.W021`

Diese beiden Punkte haengen an der spaeteren, echten HSTS-Freigabe fuer die Produktionsinfrastruktur.
