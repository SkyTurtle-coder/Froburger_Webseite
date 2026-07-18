# WEB-X Editor Workflows

Stand: 2026-07-18

## Ziel

Das CMS soll fuer den Web-X ohne technische Schulung bedienbar bleiben und trotzdem die
serverseitigen Sicherheits- und Publikationsregeln beibehalten.

## Beitragsworkflow

### Neuer Beitrag

1. CMS-Dashboard oder Beitragsuebersicht oeffnen
2. `Neuen Beitrag erstellen` anklicken
3. eines von drei Layouts waehlen
4. nur diese vier Felder pflegen:
   - Datum
   - Titel
   - Kurzbeschreibung
   - Beitrag
5. `Entwurf speichern`, `Vorschau`, `Jetzt veroeffentlichen` oder `Veroeffentlichung planen`

### Automatisch verwaltete Felder

Diese Felder werden fuer den vereinfachten Post-Workflow automatisch gesetzt oder verborgen:

- Slug
- Autor
- SEO-Titel
- Meta-Beschreibung
- Sichtbarkeit fuer normale News-Beitraege
- Pin-Prioritaet
- Layoutschluessel
- Revisionsgrund

### Rich-Text

Der Editor ist lokal gehostet und serverseitig abgesichert:

- Browser-Editor: Trix
- serverseitige Bereinigung: `nh3`
- erlaubte Formate: Absatz, Zwischenueberschrift, fett, kursiv, Listen, Links, Zitat

### Status

Der normale Beitragsstatus fuer den Web-X lautet:

- `Entwurf`
- `Geplant`
- `Veroeffentlicht`
- `Archiviert`

Der fruehere `review`-Status bleibt nicht mehr Teil der normalen Oberflaeche.

### Startseite

Die Beitragsliste erlaubt direktes Hervorheben fuer die Startseite.

- genau ein aktueller Beitrag darf aktiv hervorgehoben sein
- ein zweiter, zukuenftiger Beitrag darf fuer spaeter vorgemerkt sein
- beim Wechsel werden alte Markierungen serverseitig entfernt

### Aeltere Beitraege

Blockbasierte Altbeitraege bleiben bearbeitbar.

- beim Oeffnen wird ihr Inhalt als Rich Text vorbereitet
- beim naechsten Speichern wird `body_html` zur fuehrenden Struktur
- bestehende Revisionen bleiben erhalten

## Seitenworkflow

Seiten, Startseite und Mitgliederseite bleiben strukturierte CMS-Seiten.

Der Ablauf bleibt dort:

1. Seite oeffnen
2. erlaubte Inhaltsbereiche anpassen
3. speichern
4. Vorschau pruefen
5. veroeffentlichen oder zurueckziehen

## Medienworkflow

Medien werden weiterhin separat gepflegt:

1. Bild hochladen
2. Titel und Alt-Text setzen
3. Sichtbarkeit waehlen
4. spaeter in Seiten, Karussells oder Altbeitraegen verwenden

## Veranstaltungen und Dokumente

Veranstaltungen und Dokumente bleiben eigene Fachbereiche mit eigenen Formularen,
Vorschauen und Berechtigungen.

## Revisionen

Revisionen bleiben fuer Beitraege, Seiten und Karussells aktiv.

- jeder relevante Speichervorgang erzeugt eine neue Revision
- fruehere Versionen koennen intern angesehen werden
- Wiederherstellungen erzeugen wiederum eine neue Revision
