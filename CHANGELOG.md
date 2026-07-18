# Changelog

## 2026-07-18

### Web-X CMS Foundation
- Neuen Arbeitsbranch `feature/web-x-block-cms` fuer das strukturierte Web-X-CMS angelegt.
- `media_library.MediaAsset` mit Uploadvalidierung, Alt-Text-Regeln, Sichtbarkeit, Publikationsstatus und Bildmetadaten eingefuehrt.
- `content.LayoutPreset`, `Page`, `PageSection`, `Post`, `PostBlock`, `Carousel`, `CarouselItem` sowie Revisionsmodelle fuer Seiten, Beitraege und Karussells eingefuehrt.
- Seed-Migration fuer kontrollierte Seiten-, Beitrags- und Blocklayouts hinzugefuegt.
- Queryset-Helfer fuer publizierte und sichtbare Inhalte sowie Homepage-Pinning modelliert.
- `bootstrap_roles` um echte CMS- und Medienrechte fuer `web_aktuar`, `president` und `system_admin` erweitert.
- Admin-Registrierungen fuer die neue Content- und Media-Basis hinzugefuegt.
- Neue Tests fuer CMS-Modelle, Layout-Seeding, Medienvalidierung, Publikationslogik, Pin-Limit, Revisions-Snapshots und Rollen-Bootstrap ergaenzt.

### Web-X CMS Interface
- Redaktionelle CMS-Oberflaeche unter `/cms/` mit Dashboard, Listen und Editoren fuer Beitraege, Medien, Karussells und die Homepage umgesetzt.
- Interne Preview-, Publish-, Schedule-, Withdraw-, Archive- und Restore-Workflows fuer Beitraege und die Homepage hinzugefuegt.
- Revisionslisten und Restore-Ansichten fuer Beitraege, Startseite und Karussells umgesetzt.
- Home und `Aktuelles` minimal-invasiv an die neuen CMS-Datenmodelle angebunden, inklusive Detailroute `/aktuelles/<slug>/` und Sitemap-Eintraegen fuer publizierte Posts.
- Startseitenkarussell, angepinnte Beitraege und Homepage-Fallbacks in die oeffentlichen Templates integriert.
- Zugriffstests fuer CMS-Routen, Preview-Schutz, Media-Upload-Regeln, Restore-Flow und Public-News-Integration hinzugefuegt.

### Members Security and Reusable Pages
- Mitglieder-Profilbilder auf privates Storage unter `PRIVATE_MEDIA_ROOT` umgestellt und ueber geschuetzte Members-URLs ausgeliefert.
- `migrate_profile_photos_to_private_storage` als idempotenten Migrations-Command mit `--dry-run` und optionalem Quellbehalt hinzugefuegt.
- generischen CMS-Seiteneditor unter `/cms/seiten/` mit Preview, Publish, Withdraw und Revisionen eingefuehrt.
- `about` und `join` ueber `import_existing_public_pages` an strukturierte `Page`-/`PageSection`-Inhalte angebunden.
- `config.settings.production` auf explizite Security-Env-Variablen und schrittweise HSTS-Aktivierung vorbereitet.
- Akzeptanzdokumentation, Private-Media-Doku und aktualisierte Deployment-/Security-Guides ergaenzt.

### Documentation
- `README.md` auf den Django- und CMS-Stand umgestellt.
- Neue Web-X-CMS-Dokumente fuer Ist-Stand, Architektur, Workflows, Security, Guide und Testing hinzugefuegt.
- `docs/EXECUTION_PLAN.md`, `docs/MIGRATION_STATUS.md` und `docs/ROLE_PERMISSION_MATRIX.md` auf den neuen CMS-Grundstand aktualisiert.

## 2026-07-17

### Documentation
- `README.md` fuer GitHub deutlich ausgebaut und auf den aktuellen Repo-Betrieb ausgerichtet.
- Dokumentationsbereich `docs/` mit Projektueberblick, Inhaltspflege und Deployment angelegt.
- `DEPLOYMENT.md` auf die versionierte GitHub-Realitaet umgestellt: Root als Source of truth, kein veralteter `output/`-Pfad mehr als verbindlicher Repo-Stand.
- Phase-0-Dokumentation fuer die Django-Migration angelegt: `AGENTS.md`, Ausfuehrungsplan, Architektur-, Security-, Rollen-, Datenklassifikations- und Migrationsdokumente sowie ADRs.

### Backend Foundation
- Django-Projektgeruest mit `config/settings/{base,development,test,production}.py` angelegt.
- Python- und Paketbasis ueber `uv`, `requirements/` und `pyproject.toml` vorbereitet.
- `.env.example` und `compose.yaml` fuer lokale PostgreSQL-Entwicklung ergaenzt.
- App-Namespace `apps/` sowie erste Core-URL fuer `health/` angelegt.
- Baseline-Checks mit Django, pytest und Ruff erfolgreich ausgefuehrt.

### Authentication
- Eigenes User-Modell `accounts.User` mit eindeutiger E-Mail-Adresse als Login-Kennung eingefuehrt.
- Login, Logout, Passwort-Reset, Passwortaenderung und geschuetzte Konto-Startseite ueber Django-Auth-Views angebunden.
- Einladungsbasierte Kontoerstellung mit ablaufenden Einmal-Tokens und Aktivierung nach Passwortvergabe umgesetzt.
- Audit-Basis fuer Einladungserstellung und Einladungsannahme angelegt.
- Django-Admin fuer Custom-User und schreibgeschuetzte Audit-Eintraege konfiguriert.
- Regressionstests fuer Login, Logout, Passwort-Reset, deaktivierte Benutzer, Passwortaenderung und Einladungsfluss ergaenzt.

### Public Site Migration
- Oeffentliche Seiten auf Django-Templates umgestellt und in `templates/public/pages/` ueberfuehrt.
- Gemeinsame Layout-Bausteine fuer Head, Navigation, Footer und Meldungen eingefuehrt.
- `styles.css`, `script.js`, `Zirkel.svg` und die produktiv genutzten Bilder nach `static/` verschoben.
- Kanonische Slash-URLs eingefuehrt und permanente Redirects von den bisherigen `.html`-Pfaden angelegt.
- `robots.txt`, `sitemap.xml` und der vorlaeufige Kalenderfeed `/kalender.ics` werden jetzt ueber Django ausgeliefert.
- Externe Google-Fonts-Einbindung entfernt und auf lokale Fallback-Fonts umgestellt.
- Oeffentliche Smoke-Tests fuer Seitenzugriff, Redirects, Sitemap, Robots und ICS-Endpunkt ergaenzt.

### Member Profiles and Roles
- Separates `members.MemberProfile` als fachliches Profilmodell neben dem Auth-Account eingefuehrt.
- Mitgliedsstatus, Sichtbarkeit, Charge, Mitgliedsnummer sowie Ein- und Austrittsdaten modelliert.
- Automatische Profilerzeugung fuer neue Benutzer und Status-Aktivierung nach Einladungsannahme umgesetzt.
- Interne Routen fuer eigenes Profil sowie permission-geschuetzte Mitgliederverwaltung hinzugefuegt.
- `bootstrap_roles`-Management-Command fuer `member`, `web_aktuar`, `member_admin`, `president` und `system_admin` angelegt.
- Einladungserstellung auf explizite Berechtigung `accounts.add_accountinvitation` umgestellt.
- Tests fuer Profilanlage, Self-Service, Berechtigungsdurchsetzung, Profilverwaltung und Rollen-Bootstrap ergaenzt.

### Internal Area
- `/intern/` als Login-Gateway auf den geschuetzten Django-Mitgliederbereich umgestellt.
- Dashboard mit Kacheln fuer Profil, Mitgliederverzeichnis, Medien, allgemeine Dokumente und sensible Dokumente eingefuehrt.
- Mitgliederverzeichnis fuer angemeldete Benutzer mit Sichtbarkeitsfilterung und eigener Profil-Sicht umgesetzt.
- Sensible Dokumente ueber eigene Berechtigung `members.view_sensitive_documents` abgesichert und in den Rollen-Bootstrap aufgenommen.
- Platzhalter-Hubs fuer Medien sowie allgemeine und sensible Dokumente angelegt.
- Regressionstests fuer Dashboard-Kacheln, Verzeichniszugriff und Berechtigungspruefung ergaenzt.

## 2026-07-16

### Security
- Unsichere Demo-Authentifizierung aus dem öffentlichen Frontend entfernt.
- `intern.html` zu einer reinen Hinweis-Seite ohne Passwortprüfung und ohne versteckte Dashboard-Inhalte umgebaut.
- `robots.txt` um Ausschlüsse für `output/`, `reports/`, `Screenshots/`, `.agents/` und `.codex/` ergänzt.

### Functionality
- `script.js` komplett neu aufgebaut.
- Hauptnavigation als Disclosure-Navigation mit echtem Button, `aria-expanded`, Escape-Unterstützung und Klick-/Touch-Steuerung umgesetzt.
- Kalenderlogik für Listen- und Monatsansicht repariert.
- Monatssicht rendert jetzt dynamisch, unterstützt mehrere Termine pro Tag und zeigt verständliche Statusmeldungen.
- Auf kleinen Viewports wird die Monatsansicht nicht mehr als horizontale Scroll-Falle angeboten; dort bleibt die Listenansicht aktiv.
- Platzhalterbeiträge und tote "Mehr laden"-Logik aus `aktuelles.html` entfernt.

### Content and UX
- Neue Seite `mitglied-werden.html` in den bestehenden Stil integriert und inhaltlich präzisiert.
- Tote Footer-Links, Platzhalter `Gegründet [Jahr]` und leere Social-Links entfernt.
- `impressum.html` und `datenschutz.html` angelegt, damit keine toten Rechtslinks mehr existieren.
- Öffentliche Navigation bewirbt den deaktivierten Mitgliederbereich nicht mehr prominent.

### Accessibility
- Falsche `role="menu"`-/`role="menuitem"`-Struktur entfernt.
- Kalenderumschalter als semantische Gruppe ausgezeichnet.
- Redundante Platzhalterbilder im Mitgliederbereich als dekorativ markiert.
- CTA-Kontraste verbessert.

### Performance
- Hero-Hintergrund auf `Bilder/DSC02521-hero.jpg` umgestellt.
- Grosse Inhaltsbilder auf optimierte Web-Varianten umgestellt:
  - `Bilder/DSC01173-web.jpg`
  - `Bilder/DSC01282-web.jpg`
  - `Bilder/DSC01621-web.jpg`
- Startseitenwappen auf `Bilder/Schild.svg` umgestellt.
- Öffentliche Seiten mit Canonical- und Open-Graph-Metadaten ergänzt.

### Deployment
- `sitemap.xml` auf den Stand vom `2026-07-16` aktualisiert und um `impressum.html` sowie `datenschutz.html` ergänzt.
- `output/` als selbstkonsistenter statischer Bundle-Stand synchronisiert, inklusive `Bilder/`, `Zirkel.svg` und `kalender.ics`.
