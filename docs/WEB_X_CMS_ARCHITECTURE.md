# WEB-X CMS: Architektur

Stand: 2026-07-18

## Zielbild

Das Web-X-CMS folgt dem Prinzip der kontrollierten Gestaltungsfreiheit:

- Inhalte werden strukturiert gespeichert.
- Layouts werden serverseitig freigegeben.
- Templates, CSS und JavaScript bleiben im Code.
- Veroeffentlichung, Vorschau und Wiederherstellung werden ueber eigene Workflows gesteuert.

## App-Grenzen

### `apps.content`

- `LayoutPreset`
- `Page`
- `PageSection`
- `Post`
- `PostBlock`
- `Carousel`
- `CarouselItem`
- Revisionsmodelle fuer Seiten, Beitraege und Karussells

### `apps.media_library`

- `MediaAsset`
- Uploadvalidierung
- Alt-Text- und Sichtbarkeitsregeln
- Metadaten fuer Breite, Hoehe, MIME-Type und Dateigroesse

### Weiterhin getrennt

- `apps.events` fuer strukturierte Anlaesse
- `apps.documents` fuer private und versionierte Dateien
- `apps.audit` fuer unveraenderbare Aktionsprotokolle

## Modellprinzipien

### Layouts

- `LayoutPreset` speichert nur freigegebene Layoutschluessel und Metadaten.
- Der eigentliche Templatepfad kommt aus einer serverseitigen Whitelist.
- Layouts sind nach `page`, `post` und `block` getrennt.

### Publikation

- `Page` und `Post` teilen Status- und Sichtbarkeitsfelder.
- Querysets bilden die Veroeffentlichungslogik zentral ab.
- Geplante Inhalte koennen ohne Hintergrundjob sichtbar werden, sobald `scheduled_for` erreicht ist.

### Bloecke

- `PageSection` und `PostBlock` verwenden feste Blocktypen.
- Darstellungsoptionen sind nur als validierte Teilmenge im JSON-Feld erlaubt.
- Medien- und Karussellzuteilung bleiben referenziert statt freien HTML-Embeds.

### Revisionen

- Jede Hauptentitaet erzeugt Snapshot-Daten als JSON.
- Wiederherstellungen sollen spaeter neue Revisionen erzeugen, nie alte ueberschreiben.

## Homepage-Strategie

- Die Startseite bleibt als besonderer Seitentyp modelliert.
- `Page.page_key = homepage` identifiziert die Systemseite eindeutig.
- Angepinnte Beitraege kommen aus `Post`.
- Zusaetzliche Hero-, CTA- und Listenbereiche kommen aus `PageSection`.

## Medienstrategie

- CMS-Bilder laufen ueber `MediaAsset`.
- Nur JPEG, PNG und WebP sind aktuell erlaubt.
- SVG bleibt gesperrt, bis eine sichere Sanitization vorhanden ist.
- Nicht dekorative Bilder brauchen einen Alt-Text.

## Berechtigungsstrategie

- `web_aktuar` erhaelt Content- und Medienrechte, aber keine Mitgliederverwaltungsrechte.
- `member_admin` bleibt auf Personen- und Einladungsprozesse beschraenkt.
- `president` und `system_admin` koennen die CMS-Grundrechte ebenfalls tragen.
- Vorschau- und Publish-Rechte sind von normalen Aenderungsrechten getrennt.

## Naechste technische Schritte

1. Editor-Views, Formsets und Templates fuer `Post`, `Page` und `MediaAsset`
2. Preview- und Publish-Endpunkte
3. Audit-Integration fuer CMS-Aktionen
4. Umstellung der Startseite und `Aktuelles` auf datenbankgestuetztes Rendering
5. Inhaltstransfer der statischen Templates per Management Command
