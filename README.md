# Froburger_Webseite

Versioniertes GitHub-Repository der statischen Website der AV Froburger.

## Projektstatus

- Produktive Ziel-Domain: `https://avfroburger.ch`
- Technische Basis: reines HTML, CSS und JavaScript
- Build-System: keines
- Source of truth: die Dateien im Projektroot
- Oeffentlicher Release bleibt fachlich blockiert, bis `impressum.html` und `datenschutz.html` verbindlich finalisiert sind

## Ziel des Repositories

Dieses Repository dient als zentrale, nachvollziehbare Projektbasis fuer:

- inhaltliche Pflege der Website
- technische Weiterentwicklung ohne CMS oder Framework
- saubere Versionierung ueber Git und GitHub
- dokumentierte Deployments und nachvollziehbare Aenderungen

## Seitenuebersicht

| Datei | Zweck |
| --- | --- |
| `index.html` | Startseite mit Positionierung, Einstieg und Hero-Bereich |
| `aktuelles.html` | Berichte, Rueckblicke und Blog-artige Inhalte |
| `anlaesse.html` | Semesterprogramm, Kalenderansichten und Alt-Froburger-Staemme |
| `mitglieder.html` | Vorstellung von Aktivitas und Alt-Froburgern |
| `mitglied-werden.html` | Eintrittsseite mit Kontakt- und Einstiegsinformationen |
| `ueber-uns.html` | Geschichte, Umfeld und Einordnung der Verbindung |
| `intern.html` | bewusst deaktivierter Hinweis statt pseudo-gesicherter Bereich |
| `impressum.html` | rechtliche Pflichtseite |
| `datenschutz.html` | Datenschutzseite mit noch offenem Finalisierungsbedarf |

## Technischer Zuschnitt

- `styles.css` enthaelt das komplette visuelle System der Website.
- `script.js` steuert die mobile Navigation, das Dropdown-Verhalten, die Kalenderlogik und die lokale ICS-Erzeugung.
- `Bilder/` enthaelt die produktiv verwendeten Web-Assets.
- `kalender.ics`, `robots.txt`, `sitemap.xml` und `Zirkel.svg` gehoeren zum oeffentlichen Auslieferungsumfang.
- Es gibt keinen Build-Schritt und keine serverseitige Anwendungslogik.

## Repository-Struktur

```text
.
|-- Bilder/
|-- docs/
|-- index.html
|-- aktuelles.html
|-- anlaesse.html
|-- mitglieder.html
|-- mitglied-werden.html
|-- ueber-uns.html
|-- intern.html
|-- impressum.html
|-- datenschutz.html
|-- styles.css
|-- script.js
|-- kalender.ics
|-- robots.txt
|-- sitemap.xml
|-- CHANGELOG.md
|-- DEPLOYMENT.md
|-- TESTS.md
|-- FINAL-IMPLEMENTATION-REPORT.md
`-- README.md
```

## Lokal arbeiten

1. Repository klonen.
2. HTML-, CSS- oder JS-Dateien direkt im Projektroot bearbeiten.
3. `index.html` lokal im Browser oeffnen oder ueber einen einfachen statischen Server ausliefern.
4. Vor einem Commit Links, Bilder, Kalenderdaten und Metadaten pruefen.

## Wichtige Arbeitsregeln

- Der Projektroot ist die verbindliche Quelle. Keine parallele Wahrheit in einem zweiten Quellordner pflegen.
- Lokale Arbeitsartefakte wie `output/`, `reports/`, `Screenshots/`, `.agents/` und `.codex/` gehoeren nicht ins Repository.
- Fuer produktive Bilder nur optimierte Web-Varianten versionieren, nicht unbenutzte Kamera-Originale.
- Bei inhaltlichen Aenderungen an oeffentlichen Seiten muessen `lastmod`, Open-Graph-Angaben und gegebenenfalls `sitemap.xml` mitgepflegt werden.
- Navigation, Footer und rechtliche Links muessen ueber alle oeffentlichen Seiten konsistent bleiben.

## Dokumentation im Repository

- [docs/PROJEKTUEBERBLICK.md](docs/PROJEKTUEBERBLICK.md): fachlicher und technischer Aufbau
- [docs/INHALTSPFLEGE.md](docs/INHALTSPFLEGE.md): Pflegeprozess fuer Inhalte, Bilder und Seiten
- [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md): Deployment-Vorgehen und Checklisten
- [DEPLOYMENT.md](DEPLOYMENT.md): kompakte Deployment-Zusammenfassung im Root
- [TESTS.md](TESTS.md): dokumentierte Pruefungen
- [CHANGELOG.md](CHANGELOG.md): nachvollziehbare Aenderungshistorie
- [FINAL-IMPLEMENTATION-REPORT.md](FINAL-IMPLEMENTATION-REPORT.md): technischer Abschlussstand der letzten grossen Ueberarbeitung
- [REMAINING-LIMITATIONS.md](REMAINING-LIMITATIONS.md): offene Einschraenkungen
- [ROLLBACK.md](ROLLBACK.md): Hinweise fuer Ruecknahmen

## Bekannte Release-Blocker

- `impressum.html` ist nur dann releasefaehig, wenn verantwortliche Vertretung und Postanschrift verbindlich geprueft wurden.
- `datenschutz.html` ist nur dann releasefaehig, wenn Hosting, Drittanbieter und Fonts rechtlich sauber abgebildet sind.
- Die Website ist absichtlich rein statisch; es existiert keine serverseitige Authentifizierung fuer einen internen Bereich.

## Git-Workflow

1. Aenderungen lokal vornehmen.
2. Sichtpruefung und Dateichecks durchfuehren.
3. Aenderungen mit klarem Commit-Text committen.
4. Nach `main` pushen.
5. Deployment separat und bewusst ausloesen.

## Maintainer-Hinweis

Wenn kuenftig neue Seiten, neue Medientypen oder ein echtes Deployment-Setup hinzukommen, muss zuerst die Dokumentation in `docs/` aktualisiert werden. Das Repository soll nicht nur Code ablegen, sondern den realen Betriebsstand sauber beschreiben.
