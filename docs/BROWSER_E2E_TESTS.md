# Browser E2E Tests

Stand: 2026-07-18

## Ziel

Die Browser-E2E-Suite prueft kritische Redaktions- und Sichtbarkeitsfluesse mit Playwright Chromium.

## Setup

```text
uv pip install playwright
uv run playwright install chromium
```

## Ausfuehrung

```text
$env:DATABASE_URL='sqlite:///db.sqlite3'
uv run pytest tests\e2e -q
```

## Abgedeckte Viewports

- `desktop`
- `tablet`
- `mobile`

## Abgedeckte Szenarien

- Event-CMS mit Vorschau, Publish, oeffentlicher Sicht und ICS-Download
- Dokument-CMS mit gruppenbasierter Freigabe, erlaubtem Download und verweigertem Direktzugriff
- oeffentliche Mitgliederseite mit Import, CMS-Bearbeitung, Publish und Datenschutzpruefung

## Technische Schutznetze

- jeder Browserlauf scheitert bei `console.error`
- jeder Browserlauf scheitert bei `pageerror`
- Downloads werden explizit erwartet und Dateinamen geprueft

## Dateien

- `tests/e2e/conftest.py`
- `tests/e2e/test_critical_workflows.py`
