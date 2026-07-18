# Deployment-Anleitung

Stand: 2026-07-18

## Zielbild

Die Website wird als Django-Anwendung mit getrennten Settings und strukturierten CMS-Workflows betrieben. Oeffentliche CMS-Medien und private Dateien bleiben strikt getrennt.

## Wichtige Umgebungsvariablen

- `DJANGO_SETTINGS_MODULE=config.settings.production`
- `DJANGO_SECRET_KEY`
- `DJANGO_ALLOWED_HOSTS`
- `CSRF_TRUSTED_ORIGINS`
- `DATABASE_URL`
- `PUBLIC_MEDIA_ROOT`
- `PRIVATE_MEDIA_ROOT`
- `DJANGO_SECURE_SSL_REDIRECT`
- `DJANGO_SECURE_HSTS_SECONDS`
- `DJANGO_SECURE_HSTS_INCLUDE_SUBDOMAINS`
- `DJANGO_SECURE_HSTS_PRELOAD`
- `DJANGO_SESSION_COOKIE_SECURE`
- `DJANGO_CSRF_COOKIE_SECURE`
- `DJANGO_USE_X_FORWARDED_PROTO`
- `DJANGO_PRIVATE_MEDIA_USE_X_ACCEL_REDIRECT`
- `DJANGO_PRIVATE_MEDIA_ACCEL_REDIRECT_PREFIX`

## Verbindliche Vorpruefung

1. `git diff --check`
2. `uv run ruff check .`
3. `uv run python manage.py check`
4. `uv run python manage.py makemigrations --check`
5. `uv run pytest -q`
6. `uv run python manage.py check --deploy --settings=config.settings.production`

## Technische Deployment-Schritte

1. Abhaengigkeiten installieren.
2. Produktions-Env-Variablen setzen.
3. `uv run python manage.py migrate`
4. Rollen initialisieren oder nachziehen:
   - `uv run python manage.py bootstrap_roles`
5. statische Inhalte einmalig importieren:
   - `uv run python manage.py import_existing_public_pages`
   - `uv run python manage.py import_existing_members_page`
6. optional bestehende Profilbilder sicher migrieren:
   - `uv run python manage.py migrate_profile_photos_to_private_storage --dry-run`
   - danach ohne `--dry-run`
7. `uv run python manage.py collectstatic --noinput`
8. Anwendung hinter dem produktiven WSGI-/ASGI-Setup starten.

## Private Dateien

- `PRIVATE_MEDIA_ROOT` darf keinen oeffentlichen Alias erhalten.
- Dokumentversionen und Profilbilder duerfen nie direkt ueber eine oeffentliche Rohdatei-URL erreichbar sein.
- fuer Production ist entweder Django-Streaming oder ein interner Reverse-Proxy-Mechanismus wie `X-Accel-Redirect` vorgesehen.

## Erwartete `check --deploy`-Warnungen

Mit kurzer HSTS-Zeit und bewusst noch deaktivierten Spaetschritten bleiben erwartete Warnungen moeglich fuer:

- `SECURE_HSTS_INCLUDE_SUBDOMAINS`
- `SECURE_HSTS_PRELOAD`

Diese Punkte sollen erst nach verifizierter HTTPS-Infrastruktur fuer alle relevanten Subdomains geschlossen werden.
