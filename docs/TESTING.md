# Testing

Stand: 2026-07-18

## Ziel

Die Tests decken heute vier Bereiche ab:

- bestehende oeffentliche Django-Seiten
- Authentifizierung und Einladungen
- Mitgliederbereich
- neue CMS-Grundmodelle fuer Content und Medien

## Lokale Befehle

```text
uv run python manage.py check
uv run python manage.py check --deploy
uv run python manage.py makemigrations --check
uv run pytest
uv run ruff check .
git diff --check
```

## Neue CMS-Tests

- `tests/test_cms_models.py`
  - Layout-Preset-Seed
  - Medienvalidierung und Metadaten
  - SVG-Sperre
  - Publikations- und Sichtbarkeitsquerysets
  - Homepage-Pin-Limit
  - Blocklayout-Validierung
  - Revisions-Snapshots
  - Rollen-Bootstrap fuer `web_aktuar`

## Noch fehlende Testbereiche

- Web-X-Editor-Views
- Preview- und Publish-Workflows
- Restore-Workflows
- Audit-Events fuer CMS-Aktionen
- private Dokumenten- und Medienauslieferung
- Event- und Seitenmigrationen aus bestehenden Templates
