# FINAL WEB-X CMS REPORT

Stand: 2026-07-18

## 1. Analysierter Ausgangszustand

Das Repository war bereits auf Django migriert, aber die CMS-nahen Apps `content`, `media_library`, `events` und `documents` waren fachlich leer. Die oeffentlichen Seiten wurden weiterhin aus statischen Templates gerendert. Ein Mitgliederbereich, Rollen-Bootstrap und eine Audit-Basis fuer Einladungen waren bereits vorhanden.

## 2. Bereits vorhandene Funktionen

- Django-Foundation mit getrennten Settings
- oeffentliche Seiten ueber Django-Templates
- Authentifizierung, Passwort-Reset und Einladungssystem
- Mitgliederprofile, Verzeichnis und Rollen-Bootstrap
- Audit-Basis fuer Einladungen

## 3. Neu implementierte Funktionen

- strukturierte CMS-Modelle fuer Seiten, Beitraege, Bloecke, Karussells und Revisionen
- Medienmodell mit Uploadvalidierung und Metadaten
- seedbare Layout-Presets fuer Seiten, Beitraege und Blocks
- Homepage-Pinning als Modelllogik
- zentrale Publikations- und Sichtbarkeitsquerysets
- Web-X-CMS-Permissions im Rollen-Bootstrap
- Web-X-CMS-Oberflaeche unter `/cms/` mit Dashboard, Beitrags-, Medien-, Karussell- und Startseiteneditor
- gespeicherte Preview-, Publish-, Schedule-, Withdraw-, Archive- und Restore-Workflows fuer Beitraege und Startseite
- Revisionslisten und Restore-Views fuer Beitraege, Startseite und Karussells
- Audit-Events fuer Content-Saves, Pinning, Restore und Medien-Uploads
- oeffentliche Integration fuer Startseite, News-Liste, News-Detail und Sitemap
- private Profilbild-Auslieferung ueber geschuetzte Members-Route statt direkter Rohdatei-URL
- Management-Command zur Migration bestehender Profilbilder ins private Storage
- generischer CMS-Seiteneditor fuer `Page` und `PageSection` mit Preview, Publish, Withdraw und Revisionen
- Import-Command fuer `about` und `join`
- CMS-View- und Public-Integrationstests zusaetzlich zu den Modelltests

## 4. Verwendete Datenmodelle

- `media_library.MediaAsset`
- `content.LayoutPreset`
- `content.Page`
- `content.PageSection`
- `content.Post`
- `content.PostBlock`
- `content.Carousel`
- `content.CarouselItem`
- `content.PageRevision`
- `content.PostRevision`
- `content.CarouselRevision`

## 5. Beitragslayouts

- `standard_article`
- `large_hero`
- `image_left`
- `image_right`
- `gallery_story`
- `event_recap`
- `announcement`
- `compact_news`

## 6. Inhaltsblocktypen

- `heading`
- `text`
- `image`
- `image_text`
- `hero_image`
- `gallery`
- `carousel`
- `quote`
- `cta`
- `link_list`
- `document_list`
- `event_list`
- `post_list`
- `people_list`
- `timeline`
- `divider`
- `notice`

## 7. Startseitenfunktionen

- `Page.page_key = homepage` als eindeutiger Seitenschluessel
- Homepage-Pinning mit `is_homepage_pinned` und `pin_priority`
- Startseitenbereiche ueber `PageSection` editierbar
- optionales Startseitenkarussell mit internem Preview-Banner und oeffentlichem Fallback-Hero
- angepinnte publizierte Beitraege werden auf der Startseite dynamisch ausgespielt

## 8. Medien- und Karussellverwaltung

- `MediaAsset` mit Sichtbarkeit, Status, Alt-Text-Regel, MIME-Pruefung, Bilddimensionen und Dateigroesse
- `Carousel` und `CarouselItem` mit Reihenfolge, Aktivstatus und optionalen CTA-Daten

## 9. Seitenbearbeitung

- Homepage mit eigener Systemseiten-Bearbeitung
- generischer Seiteneditor fuer weitere `Page`-Eintraege unter `/cms/seiten/`
- `about` und `join` als erste zusaetzliche strukturierte CMS-Seiten vorbereitet

## 10. Rollen und Berechtigungen

- `web_aktuar` traegt jetzt Content- und Medienrechte fuer die CMS-Grundlage
- `member_admin` bleibt ohne diese CMS-Rechte
- `president` und `system_admin` enthalten die CMS-Grundrechte zusaetzlich

## 11. Sicherheitsmassnahmen

- Layout- und Template-Whitelist ueber `LayoutPreset`
- keine freie HTML-, CSS- oder JavaScript-Eingabe im Modell
- Alt-Text-Pflicht fuer nicht dekorative CMS-Bilder
- SVG-Uploads gesperrt
- Sichtbarkeits- und Publikationslogik zentral im Modell
- private Mitglieder-Profilbilder unter `PRIVATE_MEDIA_ROOT`
- serverseitige Profilbild-Pruefung ueber `/members/profile-images/<profile_id>/`
- optional vorbereitete `X-Accel-Redirect`-Konfiguration fuer Production

## 12. Versionierung

Seiten, Beitraege und Karussells besitzen Snapshot-basierte Revisionsmodelle. Revisionen koennen intern eingesehen, vorschauartig gerendert und wiederhergestellt werden; jede Wiederherstellung erzeugt eine neue Revision.

## 13. Audit-Protokoll

CMS-Aktionen fuer Beitragsspeicherung, Homepage-Speicherung, Pinning, Restore und Medien-Uploads erzeugen jetzt Audit-Eintraege. Die Audit-Abdeckung ist fuer spaetere private Dateiauslieferung und weitere Module noch auszubauen.

## 14. Testresultate

- `uv run python manage.py check`: erfolgreich
- `uv run python manage.py makemigrations --check`: erfolgreich
- `uv run python manage.py migrate`: erfolgreich
- `uv run ruff check .`: erfolgreich
- `uv run pytest -q`: `69 passed`

## 15. Coverage

- `uv run coverage run -m pytest`
- `uv run coverage report`: `81%` Gesamtdeckung

## 16. Ergebnisse der Multiagent-Reviews

- Architektur: strukturierte Modelle, CMS-Oberflaeche und generischer Seiteneditor sind jetzt verbunden.
- Frontend: CMS nutzt eine eigene Redaktion-Schale; `about` und `join` koennen aus strukturierten `PageSection`-Bloecken gerendert werden.
- Security: private Profilbild-Auslieferung ist umgesetzt; Reverse-Proxy-Hardening fuer Production bleibt bewusst konfigurationsgetrieben.
- QA: Die Suite deckt jetzt CMS-Zugriffsmatrix, Preview-Schutz, Media-Upload, Restore, Page-Workflow, Public-News und Public-Page-Import mit ab.

## 17. Offene mittlere und niedrige Punkte

- Die oeffentlichen Seiten `anlaesse` und `mitglieder` rendern weiterhin statisch
- Event- und Dokumentenmodule bleiben offen
- Browser-Automation fuer Console-, Keyboard- und Viewport-Regressionen fehlt weiterhin

## 18. Notwendige manuelle Konfiguration

- bei lokaler SQLite-Nutzung `DATABASE_URL=sqlite:///db.sqlite3`
- fuer PostgreSQL lokales `docker compose up -d db`
- rechtlich verifizierte Inhalte fuer `impressum` und `datenschutz`
- produktive Reverse-Proxy-Regeln fuer private Medien und HTTPS

## 19. Migration bestehender Inhalte

Teilweise umgesetzt. Startseitenbereiche werden beim ersten CMS-Zugriff initialisiert, News-Inhalte kommen aus `Post`, und `about` sowie `join` koennen ueber `import_existing_public_pages` in `Page` und `PageSection` uebernommen werden. `anlaesse` und `mitglieder` bleiben als naechste Migrationskandidaten offen.

## 20. Liste der Commits

- `7dd8fe3` `feat: add Web-X CMS domain models and permissions`
- `1d797c8` `feat: implement Web-X CMS interface and public content integration`
- `7deef15` `security: protect member profile images behind authorization`
- `f84c206` `feat: add reusable CMS editor for public pages`
- `e691b7f` `feat: connect about and join pages to structured CMS content`
- `01877f5` `chore: prepare hardened production settings`
- `2940679` `test: document and verify Web-X CMS acceptance workflow`
- `2bf96ed` `docs: update CMS security deployment and migration guides`

## 21. Verwendeter Branch

`feature/web-x-block-cms`

## 22. Push-Status

Nach `origin/feature/web-x-block-cms` gepusht.

## 23. Pull-Request-Status

Kein Pull Request automatisch erstellt. Ein lokaler Draft liegt in `docs/PR_WEB_X_CMS_DRAFT.md`; fuer eine direkte PR-Erstellung fehlen lokale `gh`-CLI oder ein installiertes GitHub-Plugin.
