# Deployment-Anleitung

## Zielbild

Dieses Repository enthaelt den versionierten Quellstand der Website. Deployments sollen bewusst und reproduzierbar aus dem aktuellen Git-Stand erfolgen.

## Verbindliche Quelle

- Verbindlich ist der Projektroot.
- Es gibt keinen Build-Schritt.
- Ein Deployment ist im Kern ein sauberes Ausliefern der oeffentlichen Dateien plus `Bilder/`.

## Was ausgerollt werden soll

- `index.html`
- `aktuelles.html`
- `anlaesse.html`
- `mitglieder.html`
- `mitglied-werden.html`
- `ueber-uns.html`
- `intern.html`
- `impressum.html`
- `datenschutz.html`
- `styles.css`
- `script.js`
- `Bilder/`
- `Zirkel.svg`
- `kalender.ics`
- `robots.txt`
- `sitemap.xml`

## Was nicht ausgerollt werden soll

- `.git/`
- `.agents/`
- `.codex/`
- `docs/`
- `reports/`
- `Screenshots/`
- `output/`
- interne Arbeitsdokumente und Altberichte, sofern sie nicht absichtlich oeffentlich mit ausgeliefert werden sollen

## Pre-Deployment-Checkliste

1. `git status` ist sauber.
2. Der letzte Commit auf `main` entspricht dem gewuenschten Auslieferungsstand.
3. Alle lokal referenzierten Bilder und Dateien existieren.
4. `robots.txt` und `sitemap.xml` sind aktuell.
5. `kalender.ics` ist aktuell, falls Termine geaendert wurden.
6. `impressum.html` und `datenschutz.html` sind fachlich freigegeben.

## Technische Deployment-Schritte

1. Zielserver oder Hosting-Webroot bestimmen.
2. Oeffentliche Root-Dateien und `Bilder/` hochladen.
3. Darauf achten, dass keine lokalen Hilfsordner mitveroeffentlicht werden.
4. Falls das Hosting bestehende Dateien cached, Cache leeren oder Versionierung kontrollieren.

## Nachkontrolle

Nach dem Upload mindestens pruefen:

- Startseite laedt
- `aktuelles.html` laedt
- `anlaesse.html` laedt
- Monatsansicht auf `anlaesse.html` funktioniert
- `kalender.ics` ist erreichbar
- `robots.txt` ist erreichbar
- `sitemap.xml` ist erreichbar
- Bildpfade und Favicons funktionieren

## Release-Entscheidung

Ein technisch erfolgreiches Deployment ist nicht automatisch eine fachliche Freigabe.

Kein oeffentlicher Release ohne:

- finales Impressum
- finale Datenschutzerklaerung
- letzte Sichtpruefung der betroffenen Inhalte

## Ruecknahme

Falls ein Release rueckgaengig gemacht werden muss:

- vorherigen funktionierenden Git-Stand identifizieren
- erneut deployen
- zusaetzlich [ROLLBACK.md](../ROLLBACK.md) beachten
