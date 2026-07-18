# WEB-X CMS Guide

Stand: 2026-07-18

## Uebersicht

Das Web-X CMS deckt aktuell folgende Bereiche ab:

- Beitraege
- Seiten
- Startseite
- Medien
- Karussells
- Veranstaltungen
- Dokumente

Fuer normale Mitglieder ist das CMS nicht sichtbar. Der Arbeitsbereich ist fuer `web_aktuar`,
`president` und `system_admin` freigeschaltet.

## 1. Neuen Beitrag erstellen

1. Im Mitgliederbereich anmelden.
2. `CMS oeffnen` waehlen.
3. `Neuen Beitrag erstellen` anklicken.
4. Eines von drei Layouts waehlen:
   - `Klassisch`
   - `Fokus`
   - `Magazin`
5. `Weiter` anklicken.

## 2. Layout auswaehlen

Neue Beitraege starten immer mit einer Layoutauswahl.

- `Klassisch`: ruhiger Aufbau fuer normale Vereinsbeitraege
- `Fokus`: grosse Ueberschrift fuer wichtige Mitteilungen
- `Magazin`: moderner Aufbau fuer Rueckblicke und laengere Geschichten

Fuer neue Beitraege werden im normalen Web-X-Ablauf nur diese drei Layouts angezeigt.

## 3. Text erfassen

Danach sind nur vier Felder wichtig:

- `Datum`
- `Titel`
- `Kurzbeschreibung`
- `Beitrag`

Technische Felder wie Slug, SEO, Autor, Layoutschluessel oder Pin-Prioritaet muessen nicht
mehr manuell gepflegt werden.

## 4. Text formatieren

Der Feldbereich `Beitrag` ist ein visueller Rich-Text-Editor.

Erlaubt sind:

- Absatz
- Zwischenueberschrift
- fett
- kursiv
- Aufzaehlung
- nummerierte Liste
- Link
- Zitat
- Rueckgaengig und Wiederholen

Nicht moeglich sind freie Schriftfarben, Tabellen, Skripte oder rohe HTML-Eingaben als
normaler Bearbeitungsmodus.

## 5. Entwurf speichern

`Entwurf speichern` speichert den aktuellen Stand, ohne etwas zu veroeffentlichen.

Dabei werden automatisch gesetzt:

- Autor = aktueller Benutzer
- SEO-Titel = Titel, falls leer
- Meta-Beschreibung = Kurzbeschreibung, falls leer
- Slug aus dem Titel

## 6. Vorschau

`Vorschau` zeigt den Beitrag im echten oeffentlichen Design.

- die Vorschau ist nur intern sichtbar
- Suchmaschinen sollen sie nicht indexieren
- die Vorschau ist hilfreich vor `Jetzt veroeffentlichen`

## 7. Sofort veroeffentlichen

`Jetzt veroeffentlichen` schaltet den Beitrag sofort frei.

Der Review-Schritt wurde aus der normalen Beitragsoberflaeche entfernt. Fuer Beitraege gibt es
im vereinfachten Ablauf nur noch:

- `Entwurf`
- `Geplant`
- `Veroeffentlicht`
- `Archiviert`

## 8. Veroeffentlichung planen

Falls ein Beitrag spaeter erscheinen soll:

1. Bereich `Veroeffentlichung planen` aufklappen.
2. Datum und Uhrzeit eingeben.
3. `Veroeffentlichung planen` anklicken.

Die Zeitzone ist `Europe/Zurich`.

## 9. Beitrag auf der Startseite hervorheben

In der Beitragsuebersicht kann pro Beitrag der Stern fuer die Startseite gesetzt werden.

Wichtig:

- es gibt immer nur einen aktuell sichtbaren Startseitenbeitrag
- zusaetzlich darf genau ein geplanter Startseitenbeitrag fuer spaeter vorgemerkt sein
- Entwuerfe koennen nicht auf die Startseite

## 10. Layout spaeter aendern

Bei einem bestehenden Beitrag gibt es die Aktion `Layout aendern`.

- der Inhalt bleibt erhalten
- es werden erneut die drei Layoutkarten angezeigt
- nach dem Speichern kann die Vorschau sofort geprueft werden

## 11. Beitrag zurueckziehen oder archivieren

Bestehende Beitraege koennen spaeter:

- `Zurueckziehen`
- `Archivieren`

Archivierte Beitraege sind nicht mehr oeffentlich sichtbar und verlieren eine allfaellige
Startseitenmarkierung.

## 12. Aeltere Beitraege

Aeltere Block-Beitraege bleiben erhalten.

- beim Oeffnen wird ihr Inhalt in den vereinfachten Editor uebernommen
- beim naechsten Speichern wird der Beitrag auf die neue Rich-Text-Struktur umgestellt
- Revisionen bleiben weiterhin verfuegbar

## Weitere Bereiche

Neben den Beitraegen bleiben auch Seiten, Veranstaltungen und Dokumente im CMS verfuegbar.
Diese Bereiche nutzen weiterhin ihre eigenen, fachlichen Editoren.
