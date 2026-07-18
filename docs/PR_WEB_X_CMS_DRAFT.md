# PR Draft: Web-X CMS fuer den Web-X vereinfachen und absichern

## Titel

`feature/simplify-web-x-cms` -> `main`

## Zusammenfassung

Dieser Branch reduziert den Web-X-Post-Workflow auf einen verstaendlichen Zwei-Schritt-Ablauf,
ohne die bestehende CMS-Architektur, Berechtigungen, Revisionen oder Preview-Routen zu verlieren.

Wesentliche Ziele:

1. genau drei visuelle Post-Layouts
2. nur vier sichtbare Inhaltsfelder fuer neue Beitraege
3. visueller Rich-Text-Editor statt Block-Formsets im normalen Ablauf
4. einfache Publish- und Schedule-Aktionen ohne Review-Schritt
5. direkte Startseitenmarkierung aus der Beitragsliste

## Wesentliche Aenderungen

- `Post.body_html` als neue vereinfachte Rich-Text-Struktur
- drei aktive Post-Layouts: `Klassisch`, `Fokus`, `Magazin`
- visuelle Layoutkarten fuer die Auswahl und fuer spaetere Layoutwechsel
- lokal gehosteter Trix-Editor
- serverseitige Sanitization mit `nh3`
- automatische Slug-, SEO- und Autoren-Verwaltung
- vereinfachtes CMS-Dashboard und vereinfachte Beitragsliste
- singulaere Startseitenmarkierung mit zusaetzlicher Option fuer genau einen geplanten Nachfolger
- Migration bestehender `review`-Posts nach `draft`
- Migration bestehender `PostBlock`-Inhalte nach `body_html`
- neue Browser-E2E fuer Post-Workflow, Planung und CMS-Berechtigungen

## Neue Migrationen

- `apps/content/migrations/0005_post_body_html.py`

## Testergebnisse

- `git diff --check`: erfolgreich
- `uv run ruff check .`: erfolgreich
- `uv run python manage.py check`: erfolgreich
- `uv run python manage.py makemigrations --check`: erfolgreich
- `uv run python manage.py migrate`: erfolgreich
- `uv run pytest -q`: `110 passed`
- `uv run coverage run -m pytest`: erfolgreich
- `uv run coverage report`: `69%`
- `uv run pytest tests/e2e -q`: `5 passed`

## `check --deploy`

Ausgefuehrt mit `config.settings.production`.

Verbleibende Warnungen im lokalen Stand:

- `security.W005`
- `security.W009`
- `security.W021`

`security.W009` ist lokal erwartbar, solange kein produktiver Secret-Key gesetzt ist.

## Bekannte Einschraenkungen

- keine direkte Medienbibliotheks-Einbettung innerhalb des neuen Post-Rich-Text-Editors
- produktiver Secret-Key, finale HSTS-Freigabe und echte Proxy-Pfade bleiben infra-abhaengig
- `impressum` und `datenschutz` enthalten weiter sichtbare `TODO`-Platzhalter fuer nicht verifizierte Fakten

## Selektiv nicht uebernommene lokale Artefakte

- `--check`
- `server_start`
- `uv.lock`

## PR-Status

Technisch reviewbereit. Push und eigentliche PR-Erstellung sind in diesem Lauf noch nicht ausgefuehrt worden.
