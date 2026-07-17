# Inhaltspflege

## Grundregeln

- Oeffentliche Inhalte immer direkt im Projektroot pflegen.
- Neue Inhalte muessen sprachlich, fachlich und technisch konsistent mit den bestehenden Seiten bleiben.
- Nach jeder inhaltlichen Aenderung die betroffene Seite im Browser pruefen.

## `aktuelles.html` pflegen

Die Seite besteht aus zwei Ebenen:

- Teaser-Karten im Bereich `.blog-grid`
- ausfuehrliche Beitraege als `.article-section`

Wenn ein neuer Bericht hinzukommt:

1. Neue Teaser-Karte im `blog-grid` anlegen.
2. Passende `href="#..."`-Verlinkung auf eine neue `article-section` setzen.
3. Neue `article-section` mit eigener `id` einfuegen.
4. `time datetime="YYYY-MM-DD"` konsistent pflegen.
5. Falls der neue Beitrag der wichtigste aktuelle Inhalt ist, Open-Graph-Bild und Beschreibung im `<head>` mitpruefen.

## `anlaesse.html` pflegen

Die Terminseite hat drei Pflegebereiche:

- `calendar-event` fuer kommende Anlaesse
- Monatsansicht, die automatisch aus den Events gerendert wird
- Staemme in den `stamm-card`-Bloecken

Fuer einen neuen Anlass:

1. Neues `article.calendar-event` anlegen.
2. Sichtbare Angaben pflegen:
   - Datum
   - Titel
   - Kurzbeschreibung
   - Uhrzeit
   - Ort
3. `data-*`-Attribute der `calendar-btn` sauber setzen:
   - `data-title`
   - `data-start`
   - `data-end`
   - `data-location`
   - `data-description`
4. Chronologische Sortierung in der Liste einhalten.

Wichtig:

- Die Monatsansicht darf nicht manuell nachgebaut werden.
- Fehlerhafte `data-*`-Attribute fuehren zu falschen ICS-Dateien oder inkonsistenten Monatsdaten.

## Bilder austauschen oder neu einfuegen

- Nur optimierte Web-Dateien in `Bilder/` verwenden.
- Aussagekraeftige Dateinamen nutzen.
- Immer `alt`, `width`, `height`, `loading` und `decoding` mitpruefen.
- Wenn ein Bild auf mehreren Seiten genutzt wird, die Verwendungsstellen gemeinsam pruefen.

## Statische Seiten pflegen

### `index.html`

- Hero, Einstiegstexte und Startseiten-Highlights konsistent halten.
- Bei groesseren Inhaltsaenderungen Open-Graph-Daten und Prioritaeten pruefen.

### `mitglieder.html`

- Abschnittslogik und Reihenfolge der Gruppen beibehalten.
- Keine Platzhalter oder leeren Karten stehen lassen.

### `mitglied-werden.html`

- Kontaktwege und CTA-Texte muessen fachlich korrekt bleiben.
- Wenn sich der Eintrittsprozess aendert, diese Seite zuerst aktualisieren.

### `ueber-uns.html`

- Historische und institutionelle Aussagen nur angepasst veroeffentlichen, wenn sie fachlich verifiziert sind.

### `impressum.html` und `datenschutz.html`

- Diese Seiten sind nicht nur redaktionell, sondern rechtlich sensibel.
- Aenderungen nur mit verifizierter Quelle vornehmen.

## Globale Aenderungen

Wenn Navigation, Footer oder Metadaten geaendert werden:

- alle oeffentlichen HTML-Seiten mitziehen
- Canonical-URL pruefen
- Open-Graph-Angaben pruefen
- Favicon- und Stylesheet-Referenzen konsistent halten

## Nach jeder Aenderung pruefen

1. Seite oeffnen und Layout pruefen.
2. Links und Anker pruefen.
3. Bilder pruefen.
4. Bei `anlaesse.html` auch Listenansicht, Monatsansicht und ICS-Buttons pruefen.
5. Falls oeffentliche Seiten betroffen sind, `sitemap.xml` und gegebenenfalls `robots.txt` aktualisieren.
