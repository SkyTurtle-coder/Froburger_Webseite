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
- Modelltests fuer die neue CMS-Grundlage

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
- Startseitenbereiche fachlich ueber `PageSection` modellierbar

## 8. Medien- und Karussellverwaltung

- `MediaAsset` mit Sichtbarkeit, Status, Alt-Text-Regel, MIME-Pruefung, Bilddimensionen und Dateigroesse
- `Carousel` und `CarouselItem` mit Reihenfolge, Aktivstatus und optionalen CTA-Daten

## 9. Seitenbearbeitung

Die fachliche Grundlage ist vorhanden, aber die benutzerfreundliche Web-X-Oberflaeche fuer die Bearbeitung bestehender Seiten ist noch nicht umgesetzt.

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

Seiten, Beitraege und Karussells besitzen Snapshot-basierte Revisionsmodelle. Wiederherstellungs-Views und Restore-Workflows sind noch offen.

## 13. Audit-Protokoll

Fuer CMS-Aktionen noch nicht implementiert. Die bestehende Audit-Basis loggt weiterhin nur Einladungsereignisse.

## 14. Testresultate

- `python manage.py check`: erfolgreich
- `python manage.py makemigrations --check`: erfolgreich
- `ruff check .`: erfolgreich
- `pytest`: `44 passed`

## 15. Coverage

- `coverage run -m pytest`
- `coverage report`: `93%` Gesamtdeckung

## 16. Ergebnisse der Multiagent-Reviews

- Architektur: CMS war im Code nicht vorhanden, nur in ADRs und Planungsdokumenten; Empfehlung fuer strukturierte Modelle wurde umgesetzt.
- Frontend: oeffentliche Templates sind weiterhin weitgehend statisch; Risiken durch enge Kopplung an `static/script.js` bleiben bestehen.
- Security: Profilfotos im Mitgliederbereich sind weiterhin public media; CMS-Audit, Preview und private Dateifluessse bleiben offen.
- QA: bestehende Suite war gruen, aber CMS-seitig leer; neue Modelltests schliessen diese erste Luecke.

## 17. Offene mittlere und niedrige Punkte

- Editor-Views fuer Web-X fehlen noch
- Startseite und `Aktuelles` rendern noch nicht aus CMS-Daten
- Restore-UI fehlt
- Event- und Dokumentenmodule bleiben offen
- Accessibility- und Lighthouse-Toollaeufe fehlen weiterhin

## 18. Notwendige manuelle Konfiguration

- bei lokaler SQLite-Nutzung `DATABASE_URL=sqlite:///db.sqlite3`
- fuer PostgreSQL lokales `docker compose up -d db`
- rechtlich verifizierte Inhalte fuer `impressum` und `datenschutz`

## 19. Migration bestehender Inhalte

Noch nicht umgesetzt. Die bestehenden Inhalte liegen weiterhin in den oeffentlichen Templates und muessen in einem naechsten Schritt kontrolliert in `Page`, `Post` und spaeter `Event` migriert werden.

## 20. Liste der Commits

In diesem Rollout wurden keine neuen Git-Commits erzeugt.

## 21. Verwendeter Branch

`feature/web-x-block-cms`

## 22. Push-Status

Nicht gepusht.

## 23. Pull-Request-Status

Kein Pull Request erstellt.
