# Projektueberblick

## Zweck

Die Website praesentiert die AV Froburger als statische, oeffentlich erreichbare Vereinswebsite. Das Repository soll sowohl den produktiven Quellstand als auch den organisatorischen Kontext fuer spaetere Pflege sauber dokumentieren.

## Architektur

- statische HTML-Seiten pro Inhaltsbereich
- gemeinsames Styling in `styles.css`
- gemeinsames Verhalten in `script.js`
- produktive Medien in `Bilder/`
- zusaetzliche Betriebsdateien fuer Suche, Kalender und Branding

Es gibt bewusst:

- kein Framework
- keinen Build-Prozess
- keine Datenbank
- kein CMS
- keine echte serverseitige Benutzerverwaltung

## Oeffentliche Seiten und Verantwortung

| Seite | Funktion im Projekt |
| --- | --- |
| `index.html` | Startpunkt der Website und erste Orientierung |
| `aktuelles.html` | redaktionelle Berichte und Rueckblicke |
| `anlaesse.html` | Termine, Monatsansicht und Kalender-Download |
| `mitglieder.html` | Personen- und Gruppenauftritt |
| `mitglied-werden.html` | Conversion-Seite fuer Interessenten |
| `ueber-uns.html` | Kontext, Geschichte und Selbstverstaendnis |
| `impressum.html` | rechtliche Pflichtseite |
| `datenschutz.html` | Datenschutzinformationen |

## Gemeinsame Frontend-Funktionen

`script.js` ist bewusst klein gehalten, uebernimmt aber zentrale Aufgaben:

- mobile Hauptnavigation mit Toggle
- Dropdown-Steuerung fuer `Ueber uns`
- Tastaturverhalten wie Escape und Arrow-Navigation
- Wechsel zwischen Listen- und Monatsansicht im Kalender
- Rendern der Monatsansicht aus den vorhandenen Terminartikeln
- Erzeugung lokaler ICS-Dateien aus `data-*`-Attributen

Wichtig dabei:

- Die Kalender-Monatsansicht wird nicht separat gepflegt, sondern aus den vorhandenen `calendar-event`-Elementen erzeugt.
- Unter kleinen Viewports bleibt die Listenansicht aktiv, damit die Nutzung mobil stabil bleibt.

## Medienstrategie

- Im Repository liegen nur die verwendeten Web-Assets.
- Grosse Originaldateien sollen nur dann versioniert werden, wenn sie wirklich benoetigt werden.
- Jeder Bildtausch auf einer oeffentlichen Seite braucht:
  - passenden `alt`-Text
  - korrekte `width`- und `height`-Werte
  - Pruefung der Open-Graph-Bilder, falls das Motiv auch in Social Previews verwendet wird

## SEO- und Betriebsdateien

- `sitemap.xml` beschreibt die oeffentlich indexierbaren Seiten.
- `robots.txt` schliesst interne oder rein lokale Arbeitsbereiche aus.
- `kalender.ics` bietet einen abonnierbaren Kalender fuer externe Clients.
- `Zirkel.svg` dient als Branding- und Favicon-Basis.

## Nicht versionierte Arbeitsbereiche

Diese Pfade sind bewusst nicht Teil des GitHub-Repositories:

- `output/`
- `reports/`
- `Screenshots/`
- `.agents/`
- `.codex/`

Sie koennen lokal weiterhin hilfreich sein, sind aber nicht Teil des offiziellen Quellstands.

## Aktuelle fachliche Risiken

- Impressum noch nur unter Vorbehalt belastbar
- Datenschutz noch nur unter Vorbehalt belastbar
- kein echter geschuetzter Mitgliederbereich

Diese Punkte muessen bei jeder Release-Entscheidung sichtbar bleiben.
