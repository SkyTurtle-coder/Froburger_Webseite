# Froburger Webseite

Django-Repository fuer die AV-Froburger-Webseite mit oeffentlicher Site, Mitgliederbereich und einer im Aufbau befindlichen Web-X-CMS-Schicht.

## Projektstatus

- Ziel-Domain: `https://avfroburger.ch`
- Architektur: Django modular monolith
- Datenbank: PostgreSQL als Ziel, SQLite lokal/testweise moeglich
- Oeffentliche Seiten: ueber Django-Templates ausgeliefert
- Mitgliederbereich: Auth, Einladungen, Profile und Verzeichnis vorhanden
- Web-X-CMS: strukturierte Modell- und Rechtebasis vorhanden, Redaktionsoberflaeche noch offen
- Oeffentlicher Release bleibt fachlich blockiert, bis `impressum` und `datenschutz` verbindlich finalisiert sind

## Wichtige Verzeichnisse

```text
.
|-- apps/
|   |-- accounts/
|   |-- audit/
|   |-- content/
|   |-- core/
|   |-- documents/
|   |-- events/
|   |-- media_library/
|   `-- members/
|-- config/
|-- docs/
|-- static/
|-- templates/
|-- media/
|-- private_media/
|-- tests/
|-- compose.yaml
|-- manage.py
`-- pyproject.toml
```

## Lokal starten

### Mit SQLite

```powershell
Set-Location "C:\Users\phili\OneDrive - FHNW\PRIVAT\Froburger\Webseite"
$env:DATABASE_URL='sqlite:///db.sqlite3'
.\.venv\Scripts\python.exe manage.py migrate
.\.venv\Scripts\python.exe manage.py runserver 127.0.0.1:8000
```

### Mit PostgreSQL via Docker

```powershell
docker compose up -d db
.\.venv\Scripts\python.exe manage.py migrate
.\.venv\Scripts\python.exe manage.py runserver 127.0.0.1:8000
```

## Qualitaetschecks

```text
uv run python manage.py check
uv run python manage.py check --deploy
uv run python manage.py makemigrations --check
uv run pytest
uv run ruff check .
git diff --check
```

## Aktueller Funktionsstand

### Bereits umgesetzt

- Custom User und Einladungssystem
- Django-Templates fuer die oeffentliche Site
- Mitgliederbereich mit Profilpflege und Verzeichnis
- Rollen-Bootstrap fuer `member`, `web_aktuar`, `member_admin`, `president`, `system_admin`
- Audit-Basis fuer Einladungen
- strukturierte CMS-Grundmodelle fuer Seiten, Beitraege, Medien, Karussells und Revisionen
- kontrollierte Layout-Presets fuer Seiten, Beitraege und Blocks
- Medienvalidierung fuer JPEG, PNG und WebP

### Noch offen

- benutzerfreundliche Web-X-Editor-Oberflaeche
- Preview-/Publish-/Restore-Flows
- dynamisches Rendering der oeffentlichen Seiten aus den neuen CMS-Modellen
- Event- und Dokumentenmodule
- private Medienauslieferung fuer Mitgliederinhalte

## Wichtige Dokumentation

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- [docs/EXECUTION_PLAN.md](docs/EXECUTION_PLAN.md)
- [docs/MIGRATION_STATUS.md](docs/MIGRATION_STATUS.md)
- [docs/SECURITY_MODEL.md](docs/SECURITY_MODEL.md)
- [docs/WEB_X_CMS_CURRENT_STATE.md](docs/WEB_X_CMS_CURRENT_STATE.md)
- [docs/WEB_X_CMS_ARCHITECTURE.md](docs/WEB_X_CMS_ARCHITECTURE.md)
- [docs/WEB_X_EDITOR_WORKFLOWS.md](docs/WEB_X_EDITOR_WORKFLOWS.md)
- [docs/WEB_X_CMS_SECURITY_REVIEW.md](docs/WEB_X_CMS_SECURITY_REVIEW.md)
- [docs/WEB_X_CMS_GUIDE.md](docs/WEB_X_CMS_GUIDE.md)
- [docs/TESTING.md](docs/TESTING.md)

## Sicherheitsregeln

- keine Secrets, `.env`, privaten Schluessel oder echten Mitgliederdaten committen
- oeffentliche und private Dateien strikt trennen
- Berechtigungen serverseitig erzwingen
- keine freie HTML-, CSS- oder JavaScript-Eingabe fuer Web-X
- rechtliche Platzhalter sichtbar lassen, bis Fakten verifiziert sind
