# Rollback

Stand: Donnerstag, 16. Juli 2026

## Ausgangslage

Das sichtbare `.git` im Projekt war während der Überarbeitung nicht als verlässliches Repository nutzbar. Ein Git-basierter Rollback kann deshalb nicht vorausgesetzt werden.

## Empfohlene Rollback-Strategie

1. Vor jedem Upload den aktuellen Inhalt von `output/` separat archivieren.
2. Vor jedem Upload zusätzlich den vorherigen Live-Stand ausserhalb dieses Projekts sichern.
3. Für einen Rollback den zuletzt funktionierenden Bundle-Stand wieder als Webroot ausrollen.

## Dateien mit grösserer Relevanz für einen Rollback

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
- `robots.txt`
- `sitemap.xml`
- `Bilder/`
- `Zirkel.svg`
- `kalender.ics`

## Minimaler operativer Rollback

- den früheren funktionierenden Deploy-Stand in `output/` oder aus dem Hosting-Backup wiederherstellen
- die aktuelle Version nicht teilweise mischen
- nach dem Rollback Startseite, Anlässe, Mitglieder und `robots.txt` kurz prüfen
