# Browser E2E Tests

Stand: 2026-07-18

## Ziel

Die Browser-E2E-Suite prueft die kritischsten Redaktions- und Sichtbarkeitsablaeufe im
tatsaechlichen UI.

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

- vereinfachter Post-Workflow mit Layoutwahl, Entwurf, Vorschau, Publish, Layoutwechsel und Startseitenmarkierung
- geplanter Post-Workflow mit zukuenftiger Veroeffentlichung und Berechtigungspruefung fuer normale Mitglieder
- Event-CMS mit Vorschau, Publish, oeffentlicher Sicht und ICS-Download
- Dokument-CMS mit gruppenbasierter Freigabe, erlaubtem Download und verweigertem Direktzugriff
- oeffentliche Mitgliederseite mit Import, CMS-Bearbeitung, Publish und Datenschutzpruefung

## Technische Schutznetze

- jeder Browserlauf scheitert bei `console.error`
- jeder Browserlauf scheitert bei `pageerror`
- erwartete Downloads werden explizit geprueft
- verbotene CMS-Zugriffe werden ueber die Request-API geprueft, damit die Konsolenpruefung sauber bleibt

## Dateien

- `tests/e2e/conftest.py`
- `tests/e2e/test_critical_workflows.py`
