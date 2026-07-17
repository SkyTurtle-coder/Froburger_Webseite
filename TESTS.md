# Tests

Stand: Donnerstag, 16. Juli 2026

## Ausgeführt

### Statische Prüfungen

- Suche nach:
  - `froburger-demo`
  - `sessionStorage`
  - `role="menu"`
  - `role="menuitem"`
  - `href="#"`
  - `Gegründet [Jahr]`
- Ergebnis:
  - Root: keine Treffer für die kritischen Altlasten
  - `output/`: keine Treffer für die kritischen Altlasten

### Link- und Asset-Prüfungen

- Prüfung aller lokalen `href`-/`src`-Ziele in allen Root-HTML-Dateien
- Prüfung aller lokalen `href`-/`src`-Ziele in allen `output`-HTML-Dateien
- Ergebnis:
  - keine fehlenden lokalen Ziele in Root
  - keine fehlenden lokalen Ziele in `output`

### Root-/Output-Abgleich

- SHA256-Vergleich der zentralen Webdateien und Assets:
  - HTML-Dateien
  - `styles.css`
  - `script.js`
  - `robots.txt`
  - `sitemap.xml`
  - `Zirkel.svg`
  - `kalender.ics`
- Ergebnis:
  - ausgewählte Root-/`output/`-Dateien byte-identisch

### Headless-Browser-Prüfungen mit Edge

- Screenshots erzeugt für:
  - Desktop `index.html`
  - Mobile `anlaesse.html`
  - Mobile `mitglied-werden.html`
  - Desktop `ueber-uns.html`
- DOM-Dumps erzeugt für:
  - `index.html`
  - `anlaesse.html`
  - `intern.html`
- Ergebnis:
  - Kalender-DOM wird dynamisch gerendert
  - `intern.html` enthält keine Demo-Auth-Logik mehr
  - Hero-Wappen und optimierte Bildpfade sind im finalen DOM vorhanden

### Manuelle Sichtprüfung

- Sichtprüfung der erzeugten Screenshots:
  - kein offensichtliches horizontales Scrollen im mobilen Kalenderausschnitt
  - Startseiten-Hero mit neuem Wappen und optimiertem Hintergrund aktiv
  - `ueber-uns.html` mit wieder sichtbarem Wappen

## Nicht ausgeführt

- Axe
- Lighthouse
- Playwright
- HTML-/CSS-Linter über Node

Grund:

- Am 16. Juli 2026 waren lokal weder `node`, `npx` noch `python` verfügbar.
