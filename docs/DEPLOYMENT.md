# Deployment-Anleitung

Stand: 2026-07-18

## Zielbild

Die Website wird als Django-Anwendung mit getrennten Settings und strukturiertem CMS betrieben. Oeffentliche CMS-Medien und private Mitglieder-Profilbilder bleiben getrennt.

## Produktionsrelevante Umgebungsvariablen

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

1. `git status` pruefen
2. `uv run ruff check .`
3. `uv run python manage.py check`
4. `uv run python manage.py makemigrations --check`
5. `uv run pytest -q`
6. `uv run python manage.py check --deploy --settings=config.settings.production`

## Technische Deployment-Schritte

1. Abhaengigkeiten installieren.
2. Produktions-Env-Variablen setzen.
3. `uv run python manage.py migrate`
4. optional bestehende Profilbilder sicher migrieren:
   - `uv run python manage.py migrate_profile_photos_to_private_storage --dry-run`
   - danach ohne `--dry-run`
5. fuer `about` und `join` initiale CMS-Inhalte anlegen:
   - `uv run python manage.py import_existing_public_pages`
6. `uv run python manage.py collectstatic --noinput`
7. Anwendung hinter dem produktiven WSGI-/ASGI-Setup starten.

## Reverse Proxy und HTTPS

- `SECURE_PROXY_SSL_HEADER` nur setzen, wenn der Proxy `X-Forwarded-Proto` kontrolliert und keine manipulierten Fremdwerte ungeprueft durchreicht.
- `PRIVATE_MEDIA_ROOT` darf keinen oeffentlichen Alias erhalten.
- fuer private Profilbilder entweder Django-Streaming oder internen Proxy-Mechanismus wie `X-Accel-Redirect` verwenden.

## HSTS-Einfuehrung

Schrittweise aktivieren:

1. HTTPS-Ende pruefen
2. `DJANGO_SECURE_SSL_REDIRECT=True`
3. kurze HSTS-Zeit, z. B. `DJANGO_SECURE_HSTS_SECONDS=3600`
4. HSTS schrittweise erhoehen
5. erst spaeter `DJANGO_SECURE_HSTS_INCLUDE_SUBDOMAINS=True`
6. zuletzt optional `DJANGO_SECURE_HSTS_PRELOAD=True`

## Aktueller Stand von `check --deploy`

Mit kurzer HSTS-Zeit und bewusst noch deaktivierten spaeten HSTS-Stufen bleiben erwartete Warnungen moeglich fuer:

- `SECURE_HSTS_INCLUDE_SUBDOMAINS`
- `SECURE_HSTS_PRELOAD`

Diese Warnungen sollen erst dann geschlossen werden, wenn alle Subdomains und das reale HTTPS-Setup verifiziert sind.
