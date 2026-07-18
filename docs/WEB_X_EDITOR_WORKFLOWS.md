# WEB-X Editor Workflows

Stand: 2026-07-18

## Zielgruppe

Der Web-X soll Inhalte ohne HTML-, CSS-, JavaScript- oder Python-Kenntnisse pflegen koennen.

## Ziel-Workflows

### Neuer Beitrag

1. Im Mitgliederbereich anmelden
2. Bereich "Redaktion" oeffnen
3. Beitragstyp und Layout waehlen
4. Titel, Teaser und Titelbild setzen
5. kontrollierte Inhaltsbloecke hinzufuegen
6. Entwurf speichern
7. Vorschau im echten Seitendesign oeffnen
8. Beitrag veroeffentlichen oder planen
9. optional auf Startseite anpinnen

### Bestehende Seite bearbeiten

1. Seite im Editor oeffnen
2. nur freigegebene Inhaltsbereiche sehen
3. Text, Bilder, CTA und erlaubte Varianten anpassen
4. Vorschau pruefen
5. neue Version speichern
6. veroeffentlichen oder zurueckziehen

### Medienverwaltung

1. Bild hochladen
2. Titel, Alt-Text, Legende und Sichtbarkeit pflegen
3. Bild in Beitrag, Seite oder Karussell auswaehlen

### Wiederherstellung

1. Revisionsliste oeffnen
2. Zeitpunkte und Aenderungsgrund vergleichen
3. fruehere Version zur Vorschau laden
4. bewusst als neue Version wiederherstellen

## Aktueller Zwischenstand

### Bereits umgesetzt

- die fachlichen Datenmodelle fuer Beitraege, Seiten, Medien, Karussells und Revisionen
- Layout-Presets fuer Seiten, Beitraege und Blocks
- CMS-Permissions fuer `web_aktuar`

### Noch nicht umgesetzt

- die eigentliche Web-X-Oberflaeche
- die Formularfluesse fuer Blockbearbeitung
- Preview- und Publish-Aktionen
- Restore-Dialoge
- Medienbrowser mit Suche und Filterung

## UX-Leitplanken fuer die naechsten Schritte

- nur sprechende Layoutnamen anzeigen, nie technische Templatepfade
- Blocktypen klar gruppieren: Text, Bild, Galerie, CTA, Listen
- Pflichtfelder vor dem Publizieren sichtbar markieren
- fehlende Alt-Texte vor der Publikation blockieren
- Aktionen sprachlich klar trennen: speichern, Vorschau, publizieren, planen, zurueckziehen
- mobile Nutzung fuer einfache Korrekturen sicherstellen
