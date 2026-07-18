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

Die Homepage besitzt jetzt eine eigene Web-X-Bearbeitungsmaske mit festen `PageSection`-Zeilen, Versionierung, Preview und Restore. Eine generische Bearbeitung weiterer redaktioneller Seiten ausserhalb der Startseite ist weiterhin nicht umgesetzt.

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

## 12. Versionierung

Seiten, Beitraege und Karussells besitzen Snapshot-basierte Revisionsmodelle. Revisionen koennen intern eingesehen, vorschauartig gerendert und wiederhergestellt werden; jede Wiederherstellung erzeugt eine neue Revision.

## 13. Audit-Protokoll

CMS-Aktionen fuer Beitragsspeicherung, Homepage-Speicherung, Pinning, Restore und Medien-Uploads erzeugen jetzt Audit-Eintraege. Die Audit-Abdeckung ist fuer spaetere private Dateiauslieferung und weitere Module noch auszubauen.

## 14. Testresultate

- `python manage.py check`: erfolgreich
- `python manage.py makemigrations --check`: erfolgreich
- `ruff check .`: erfolgreich
- `pytest`: `59 passed`

## 15. Coverage

- `coverage run -m pytest`
- `coverage report`: `93%` Gesamtdeckung

## 16. Ergebnisse der Multiagent-Reviews

- Architektur: Strukturierte Modelle und klares `/cms/`-Routing wurden umgesetzt; weitere generische Seiteneditoren bleiben bewusst ausserhalb dieses ersten Schnitts.
- Frontend: Die Redaktionsoberflaeche nutzt eine eigene CMS-Schale, waehrend Home und News minimal-invasiv an die neuen Datenmodelle gebunden wurden.
- Security: Profilfotos im Mitgliederbereich sind weiterhin public media; private Dateifluessse und der Mitgliederbereich selbst wurden bewusst nicht als Vorlage fuer CMS-Medien uebernommen.
- QA: Die Suite deckt jetzt CMS-Zugriffsmatrix, Preview-Schutz, Media-Upload, Restore, Startseiten-Fallback und Sitemap fuer News-Details mit ab.

## 17. Offene mittlere und niedrige Punkte

- Generische Editor-Views fuer weitere redaktionelle Seiten ausserhalb der Homepage fehlen noch
- Die oeffentlichen Seiten `anlaesse`, `mitglieder`, `mitglied-werden` und `ueber-uns` rendern weiterhin statisch
- Private Medienauslieferung und geschuetzte Dateifluesse sind noch nicht umgesetzt
- Event- und Dokumentenmodule bleiben offen
- Accessibility- und Lighthouse-Toollaeufe fehlen weiterhin

## 18. Notwendige manuelle Konfiguration

- bei lokaler SQLite-Nutzung `DATABASE_URL=sqlite:///db.sqlite3`
- fuer PostgreSQL lokales `docker compose up -d db`
- rechtlich verifizierte Inhalte fuer `impressum` und `datenschutz`

## 19. Migration bestehender Inhalte

Teilweise umgesetzt. Startseitenbereiche werden beim ersten CMS-Zugriff initialisiert, und News-Inhalte koennen jetzt direkt aus `Post` oeffentlich ausgespielt werden. Die restlichen statischen Inhaltsseiten muessen in einem naechsten Schritt kontrolliert in `Page`, `Post` und spaeter `Event` migriert werden.

## 20. Liste der Commits

In diesem Rollout wurde der bereits vorhandene CMS-Grundstand separat gesichert:

- `7dd8fe3` `feat: add Web-X CMS domain models and permissions`

Die in diesem Durchlauf implementierte Web-X-CMS-Oberflaeche liegt aktuell noch uncommittet im Arbeitsverzeichnis.

## 21. Verwendeter Branch

`feature/web-x-block-cms`

## 22. Push-Status

Nicht gepusht.

## 23. Pull-Request-Status

Kein Pull Request erstellt.
