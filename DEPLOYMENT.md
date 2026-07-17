# Deployment

Kurzfassung fuer GitHub-Leser und Maintainer.

## Grundsatz

- Source of truth ist der Projektroot.
- Es gibt keinen Build-Schritt.
- Deployt werden die produktiven Dateien aus dem Root plus `Bilder/`.
- Lokale Hilfsordner wie `output/`, `reports/` oder `Screenshots/` sind nicht Teil des versionierten Deploy-Stands.

## Vor dem Deployment

1. Oeffentliche HTML-Seiten inhaltlich pruefen.
2. `robots.txt`, `sitemap.xml` und `kalender.ics` auf Aktualitaet pruefen.
3. Bildpfade, Meta-Daten und interne Links pruefen.
4. `impressum.html` und `datenschutz.html` fachlich freigeben.

## Auslieferungsumfang

- alle oeffentlichen `*.html`-Dateien im Root
- `styles.css`
- `script.js`
- `Bilder/`
- `Zirkel.svg`
- `kalender.ics`
- `robots.txt`
- `sitemap.xml`

## Details

Die ausfuehrliche Anleitung steht in [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).
