# Changelog

## 2026-07-18

### Web-X CMS Simplification
- neuen Branch `feature/simplify-web-x-cms` vom bisherigen CMS-Stand abgeleitet
- Post-Workflow auf zwei Schritte reduziert: Layoutwahl und vier Inhaltsfelder
- neue Post-Layouts `Klassisch`, `Fokus` und `Magazin` fuer den normalen Web-X-Ablauf freigeschaltet
- visuellen Rich-Text-Editor mit lokal gehostetem Trix integriert
- serverseitige HTML-Bereinigung mit `nh3` fuer Beitraege eingefuehrt
- automatisches Slug-, SEO- und Autoren-Handling fuer vereinfachte Posts umgesetzt
- Startseitenmarkierung auf genau einen aktuellen und optional einen geplanten Beitrag vereinfacht
- `review` aus der normalen Beitragsoberflaeche entfernt und bestehende Daten nach `draft` migriert
- Legacy-`PostBlock`-Inhalte nach `body_html` migriert und beim Bearbeiten kompatibel gehalten
- CMS-Dashboard und Beitragsliste fuer den Web-X visuell vereinfacht
- Browser-E2E fuer vereinfachten Post-Workflow, geplante Veroeffentlichung und CMS-Berechtigungen hinzugefuegt
- Web-X-Guides, Acceptance-Doku, PR-Draft und Abschlussbericht auf den vereinfachten Stand aktualisiert

### Web-X CMS Foundation
- neuen Arbeitsbranch `feature/web-x-block-cms` fuer das strukturierte Web-X-CMS angelegt
- `media_library.MediaAsset` mit Uploadvalidierung, Alt-Text-Regeln, Sichtbarkeit, Publikationsstatus und Bildmetadaten eingefuehrt
- `content.LayoutPreset`, `Page`, `PageSection`, `Post`, `PostBlock`, `Carousel`, `CarouselItem` sowie Revisionsmodelle fuer Seiten, Beitraege und Karussells eingefuehrt
- Seed-Migration fuer kontrollierte Seiten-, Beitrags- und Blocklayouts hinzugefuegt
- Queryset-Helfer fuer publizierte und sichtbare Inhalte sowie Homepage-Pinning modelliert
- `bootstrap_roles` um echte CMS- und Medienrechte fuer `web_aktuar`, `president` und `system_admin` erweitert
- Admin-Registrierungen fuer die neue Content- und Media-Basis hinzugefuegt
- neue Tests fuer CMS-Modelle, Layout-Seeding, Medienvalidierung, Publikationslogik, Pin-Limit, Revisions-Snapshots und Rollen-Bootstrap ergaenzt

### Web-X CMS Interface
- redaktionelle CMS-Oberflaeche unter `/cms/` mit Dashboard, Listen und Editoren fuer Beitraege, Medien, Karussells und die Homepage umgesetzt
- interne Preview-, Publish-, Schedule-, Withdraw-, Archive- und Restore-Workflows fuer Beitraege und die Homepage hinzugefuegt
- Revisionslisten und Restore-Ansichten fuer Beitraege, Startseite und Karussells umgesetzt
- Home und `Aktuelles` minimal-invasiv an die neuen CMS-Datenmodelle angebunden, inklusive Detailroute `/aktuelles/<slug>/` und Sitemap-Eintraegen fuer publizierte Posts
- Startseitenkarussell, angepinnte Beitraege und Homepage-Fallbacks in die oeffentlichen Templates integriert
- Zugriffstests fuer CMS-Routen, Preview-Schutz, Media-Upload-Regeln, Restore-Flow und Public-News-Integration hinzugefuegt

### Members Security and Reusable Pages
- Mitglieder-Profilbilder auf privates Storage unter `PRIVATE_MEDIA_ROOT` umgestellt und ueber geschuetzte Members-URLs ausgeliefert
- `migrate_profile_photos_to_private_storage` als idempotenten Migrations-Command mit `--dry-run` und optionalem Quellbehalt hinzugefuegt
- generischen CMS-Seiteneditor unter `/cms/seiten/` mit Preview, Publish, Withdraw und Revisionen eingefuehrt
- `about` und `join` ueber `import_existing_public_pages` an strukturierte `Page`-/`PageSection`-Inhalte angebunden
- `config.settings.production` auf explizite Security-Env-Variablen und schrittweise HSTS-Aktivierung vorbereitet
- Akzeptanzdokumentation, Private-Media-Doku und aktualisierte Deployment-/Security-Guides ergaenzt

### Documentation
- `README.md` auf den Django- und CMS-Stand umgestellt
- neue Web-X-CMS-Dokumente fuer Ist-Stand, Architektur, Workflows, Security, Guide und Testing hinzugefuegt
- `docs/EXECUTION_PLAN.md`, `docs/MIGRATION_STATUS.md` und `docs/ROLE_PERMISSION_MATRIX.md` auf den neuen CMS-Grundstand aktualisiert
- Abschlussdokumentation fuer Events, private Dokumente, oeffentliche Mitgliederseite, Browser-E2E, Deployment und Security auf den finalen Branch-Stand gebracht

### Events, Documents and Members Page
- `events` als strukturierte CMS-Domain mit Status-, Sichtbarkeits-, Preview-, Publish-, Archiv- und ICS-Workflows produktiv angebunden
- `documents` mit privatem Storage, Versionen, serverseitigem Download-Schutz und Mitgliederansicht umgesetzt
- Dashboard und interne Navigation um direkte Einstiege fuer Veranstaltungen und Dokumente erweitert
- `members.PublicMemberProfile` fuer freigegebene oeffentliche Personendaten eingefuehrt
- oeffentliche Mitgliederseite auf `Page.page_key = members` und `people_list`-Bloecke migriert

### Browser E2E and Validation Fixes
- Playwright in die Entwicklungsabhaengigkeiten aufgenommen und Chromium fuer lokale E2E-Checks eingerichtet
- Browser-E2E fuer Veranstaltungen, Dokumente und die oeffentliche Mitgliederseite hinzugefuegt
- Regression im CMS-Seiteneditor behoben, damit leere Zusatz-Inline-Formulare das Speichern nicht blockieren
- Link-Validierung auf site-relative Pfade sowie `mailto:`- und `tel:`-URLs erweitert
- neue fokussierte Tests fuer die Mitgliederseiten-Schemavalidierung, relative CMS-Links und den leeren Section-Form-Flow ergaenzt

## 2026-07-17

### Documentation
- `README.md` fuer GitHub deutlich ausgebaut und auf den aktuellen Repo-Betrieb ausgerichtet
- Dokumentationsbereich `docs/` mit Projektueberblick, Inhaltspflege und Deployment angelegt
- `DEPLOYMENT.md` auf die versionierte GitHub-Realitaet umgestellt: Root als Source of truth, kein veralteter `output/`-Pfad mehr als verbindlicher Repo-Stand
- Phase-0-Dokumentation fuer die Django-Migration angelegt: `AGENTS.md`, Ausfuehrungsplan, Architektur-, Security-, Rollen-, Datenklassifikations- und Migrationsdokumente sowie ADRs

### Backend Foundation
- Django-Projektgeruest mit `config/settings/{base,development,test,production}.py` angelegt
- Python- und Paketbasis ueber `uv`, `requirements/` und `pyproject.toml` vorbereitet
- `.env.example` und `compose.yaml` fuer lokale PostgreSQL-Entwicklung ergaenzt
- App-Namespace `apps/` sowie erste Core-URL fuer `health/` angelegt
- Baseline-Checks mit Django, pytest und Ruff erfolgreich ausgefuehrt

## 2026-07-16

### Security
- unsichere Demo-Authentifizierung aus dem oeffentlichen Frontend entfernt
- `intern.html` zu einer reinen Hinweis-Seite ohne Passwortpruefung und ohne versteckte Dashboard-Inhalte umgebaut
- `robots.txt` um Ausschluesse fuer `output/`, `reports/`, `Screenshots/`, `.agents/` und `.codex/` ergaenzt
