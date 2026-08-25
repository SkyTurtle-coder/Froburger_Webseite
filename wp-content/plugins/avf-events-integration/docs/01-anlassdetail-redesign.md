# Claude-Code-Auftrag: Redesign der Anlassdetail-Seite (AV Froburger)

**Referenzseite:** https://test.avfroburger.ch/anlaesse/semesterversand-floss/
**Betroffene Dateien (bestätigt via Browser-Inspektion):**

- `wp-content/plugins/avf-events-integration/assets/css/events-lists.css` — enthält alle 49 `.avf-event-detail*`-Regeln
- das zugehörige PHP-Template im selben Plugin, das `<article class="avf-event-detail">` rendert (Markup-Reihenfolge muss geändert werden)
- Elementor-Hero-Container `.elementor-element-2eabd2b` (Seitentitel-Band)

**Design-Tokens, die bereits existieren und zwingend weiterverwendet werden:**

```
--avf-green: #245c2b
--avf-green-dark: #183f1e
--avf-orange: #d87836
--avf-night: #0e1711
--avf-text: #202620
--avf-text-muted: #657067
--avf-font-display: "Cormorant Garamond", Georgia, serif
--avf-font-body: "Inter", Arial, sans-serif
--avf-content-width: 1200px
```

Nicht definiert, aber im CSS referenziert (Fallbacks greifen still): `--avf-cream`, `--avf-line`, `--avf-sand`. Diese Variablen sind zu ergänzen oder die Referenzen zu entfernen.

---

## Aktuelles Markup (Ist-Zustand)

```
article.avf-event-detail
  header.avf-event-detail__header          ← grid 1.15fr / 0.85fr
    p.avf-event-detail__date
    h1.avf-event-detail__title
    p.avf-event-detail__lead
    div.avf-event-detail__meta             ← grid, 3 Spalten
    p.avf-event-detail__actions > a.avf-events-tile__ics
  section.avf-event-detail__signup
    div.avf-event-detail__signup-status--open  (h2 + p)
    div.avf-event-detail__signup-grid          ← grid 634px / 519px
      section.avf-event-detail__table-block   (h2 + table)
      section.avf-event-detail__form-block    (h2 + form)
```

---

## Befunde und Aufgaben

### 1. KRITISCH — Header-Grid ist gebrochen (Hauptproblem)

**Befund.** `.avf-event-detail__header` ist ein zweispaltiges Grid (`minmax(0,1.15fr) minmax(300px,0.85fr)`), aber die fünf Kinder werden per Auto-Placement verteilt: Datum → Spalte 1, Titel → Spalte 2, Lead → Spalte 1, Meta → Spalte 2, Button → Spalte 1. Das Layout wurde ursprünglich für ein Bild in Spalte 2 gebaut (es existiert eine ungenutzte Regel `.avf-event-detail__image-wrap`). Ohne Bild entsteht:

- Der Titel steht bei `x = 851 px`, der Lead bei `x = 208 px` — der Blick springt im Zickzack statt der Leserichtung zu folgen.
- Zwischen Datumspille und Lead-Text klafft ein exakt **129 px** hohes leeres Feld.
- Rechts unter den Meta-Karten bleiben weitere **127 px** ungenutzt.
- Die Karte ist 444 px hoch für ~5 Zeilen Inhalt.

**Aufgabe.** Header auf eine einspaltige, hierarchische Struktur umbauen, wenn kein Bild vorhanden ist:

```
Datum-Pille (klein, links)
H1 Titel
Lead-Text (max 60ch)
Meta-Zeile: 3 Karten nebeneinander, volle Breite
Aktionen: "Zum Kalender hinzufügen" + primärer "Jetzt anmelden"-Anker (#anmelden)
```

Zweispaltigkeit nur beibehalten, wenn `.avf-event-detail__image-wrap` tatsächlich gerendert wird. Umsetzung z. B. per `:has()` oder über eine Modifier-Klasse `--with-image`, die das Template setzt. Kein `1fr 1fr`-Grid ohne Inhalt für die zweite Spalte.

### 2. KRITISCH — Datumspille dehnt sich auf 611 px

**Befund.** `.avf-event-detail__date` hat `display: inline-flex; align-self: flex-start`. In einem Grid-Container steuert `align-self` die Blockachse, nicht die Inline-Achse — die Pille füllt daher die gesamte Spaltenbreite und wirkt als graues Band mit linksbündigem Text.

**Aufgabe.** `align-self: flex-start` durch `justify-self: start` ersetzen (bzw. `width: fit-content`). Gesamtes Stylesheet nach demselben Fehlermuster durchsuchen.

### 3. HOCH — Status-Banner "Anmeldung" ist reine Platzverschwendung

**Befund.** `.avf-event-detail__signup-status` ist 1180 × 114 px gross und enthält nur `<h2>Anmeldung</h2>` plus einen Satz "Anmeldungen sind offen." Die Überschrift dupliziert semantisch die darunterliegende `<h2>Anmelden</h2>`. Ein Zustand ("offen") verdient keinen Block in Sektionsgrösse.

**Aufgabe.** Banner durch einen kompakten Status-Chip ersetzen und in den Header neben `ANMELDESCHLUSS` setzen: grüner Punkt + „Anmeldung offen — bis 19.08.2026". Bei `--closed` derselbe Chip in Rot/Grau mit Begründung. Der `<h2>`-Wrapper entfällt; die verbleibende Sektion bekommt eine sinnvolle `<h2>Anmeldung</h2>` als echte Sektionsüberschrift über beiden Karten.

### 4. HOCH — Dekorative Zirkel überlagern den Content

**Befund.** Insgesamt 4 `.avf-zirkel`-Elemente im DOM, drei davon in `.avf-page-zirkel-decor-layer` mit 589 px, 841 px und 934 px Kantenlänge bei `opacity: .24` bzw. `.27`. Zwei liegen bei `x = -465` und `x = -385`, ragen also links aus dem Viewport und werden hart abgeschnitten. Der dritte (`x = 328, y = -127`) liegt direkt hinter der Header-Karte. Die kräftig orangen Formen konkurrieren mit dem eigentlichen Inhalt und lassen die Seite unaufgeräumt wirken.

**Aufgabe.**

- Maximal zwei Zirkel, `opacity` auf 0.06–0.10 senken.
- Keine harten Schnittkanten: Zirkel vollständig innerhalb des Viewports platzieren oder mit `mask-image: radial-gradient(...)` weich auslaufen lassen.
- `z-index` sicherstellen, dass die Ebene hinter allen Karten liegt.
- Unter 900 px Viewport-Breite komplett ausblenden.
- `@media (prefers-reduced-motion: reduce)` ist bereits vorhanden — beibehalten.

### 5. HOCH — Drei H1 auf einer Seite, kaputte Überschriften-Hierarchie

**Befund.** Die Seite enthält drei `<h1>`: „Anlassdetail" (Elementor-Hero), „Semesterversand & FLOSS" (Plugin) und ein leeres `<h1>` im Footer. Zusätzlich ist das Logo ein `<h2>AV Froburger</h2>` mit `<h5>Seit 1939</h5>`.

**Aufgabe.**

- Genau ein `<h1>` pro Seite: der Anlasstitel.
- Hero-Titel „Anlassdetail" auf `<p>` bzw. `<h2>` herabstufen — oder besser: das generische Wort durch den Anlasstitel ersetzen und den Plugin-Titel entsprechend zurücknehmen. Aktuell steht derselbe Anlass in zwei unterschiedlichen Formulierungen untereinander.
- Leeres `<h1>` im Footer entfernen.
- Logo-Markup auf `<p>`/`<span>` umstellen.
- Reihenfolge H1 → H2 → H3 ohne Sprünge sicherstellen.

### 6. MITTEL — Hero-Band und Content sind nicht auf derselben Achse

**Befund.** Der Hero-H1 beginnt bei `x = 200 px`, die Content-Karte bei `x = 166 px` — 34 px Versatz. Ursache: Elementor-Container und `--avf-content-width: 1200px` verwenden unterschiedliche Breiten und Paddings.

**Aufgabe.** Eine gemeinsame Container-Definition erzwingen (`width: min(100%, 1200px); margin-inline: auto; padding-inline: clamp(16px, 4vw, 24px)`) und auf Hero, Content und Footer identisch anwenden. Verifizieren, dass alle drei Blöcke exakt dieselbe linke Kante haben.

### 7. MITTEL — Meta-Karten stapeln sich auf Mobile zu dritt untereinander

**Befund.** `@media (max-width: 900px)` setzt `.avf-event-detail__meta { grid-template-columns: 1fr }`. Zeit, Ort und Anmeldeschluss belegen damit drei volle Karten mit je ~90 px Höhe plus Rahmen — der Nutzer scrollt an drei Zeilen Information vorbei.

**Aufgabe.**

- 601–900 px: `repeat(2, 1fr)`.
- ≤ 600 px: Karten-Optik aufgeben, stattdessen eine `<dl>`-artige Liste mit Trennlinien (Label links, Wert rechts). Spart ~200 px Höhe.
- Uhrzeit `19:15 - 22:00 Uhr` nicht umbrechen lassen (`white-space: nowrap` oder `&nbsp;`); aktuell bricht sie mitten im Bereich.

### 8. MITTEL — Formular ohne Pflichtfeld-Kennzeichnung, Autocomplete und Validierungsfeedback

**Befund.** `vulgo` (Text) und `attending` (Select) sind `required`, aber visuell nicht als Pflichtfeld markiert. Kein `autocomplete`-Attribut auf `vulgo`. Keine Inline-Fehlermeldung, kein `aria-describedby`. — Der Honeypot ist korrekt umgesetzt (`left: -9999px`, `aria-hidden="true"`, `tabindex="-1"`, `autocomplete="off"`) und braucht **keine** Änderung.

**Aufgabe.**

- Pflichtfelder mit `*` plus Legende „* Pflichtfeld" kennzeichnen.
- `autocomplete="nickname"` auf Vulgo.
- Clientseitige Validierung mit Inline-Fehlertext unter dem Feld, verknüpft via `aria-describedby`, Fehlercontainer als `role="alert"`.
- Erfolgs-/Fehlermeldung (`.avf-event-detail__notice`) nach dem Absenden in den Fokus setzen und via `aria-live="polite"` ankündigen.

### 9. MITTEL — Leerzustände sind unbrauchbar

**Befund.** Die Tabelle „Öffentliche Anmeldungen" zeigt in einer normalen Datenzeile (`colspan="2"`, grauer Zellhintergrund) den Text „Noch keine öffentlichen Anmeldungen vorhanden." Das sieht aus wie ein Datensatz, nicht wie ein Leerzustand. Die Karte ist zudem 635 px breit für zwei schmale Spalten.

**Aufgabe.** Bei null Anmeldungen die `<table>` gar nicht rendern, sondern einen zentrierten Leerzustand: Icon, Satz „Noch niemand angemeldet.", Sekundärzeile „Sei der Erste." Sobald Einträge vorhanden sind, zusätzlich einen Zähler in der Überschrift zeigen: „Öffentliche Anmeldungen (4)".

### 10. NIEDRIG — Typografie und Schriftgrössen inkonsistent

**Befund.**

- `body` = 17 px, Tabelle = 15.3 px, Meta-Werte = 16 px, Lead = 20 px, Formularlabels = 14 px, Meta-Labels = 11.5 px, Tabellen-`th` = 12 px, Datumspille = 12 px. Neun Stufen ohne erkennbares System.
- H1 = 68 px bei `line-height: 0.95` — die Unterlängen von „Semesterversand" berühren fast die zweite Zeile.
- `font-weight` des H1: CSS deklariert 600, computed ist 700 (Elementor überschreibt).
- Meta-Labels tragen `letter-spacing: 0.12em` bei nur 11.5 px — schlecht lesbar.
- Die Kontraste sind formal in Ordnung (`#657067` auf Karte = 4.95:1), aber 11.5 px ist unabhängig davon zu klein.

**Aufgabe.** Eine Typo-Skala als CSS-Variablen definieren und ausschliesslich diese verwenden:

```
--fs-xs: 0.8125rem   /* 13px – Labels, Captions */
--fs-sm: 0.9375rem   /* 15px – Tabelle, Hilfetexte */
--fs-base: 1.0625rem /* 17px – Fliesstext */
--fs-lg: 1.25rem     /* 20px – Lead */
--fs-xl: 1.75rem     /* 28px – H2 */
--fs-2xl: clamp(2.25rem, 4vw, 3.5rem) /* H1 */
```

Meta-Labels und `th` auf `--fs-xs` (13 px) anheben, `letter-spacing` auf `0.06em` reduzieren. H1-`line-height` auf `1.05`. Elementor-Überschreibung des `font-weight` mit ausreichender Spezifität oder über den Elementor-Kit auflösen — kein `!important`.

### 11. NIEDRIG — Kein sichtbarer primärer Call-to-Action im Header

**Befund.** Die einzige Aktion oberhalb der Falz ist „Zum Kalender hinzufügen" (Outline-Button, sekundäre Optik). Der eigentliche Zweck der Seite — sich anmelden — ist erst nach Scrollen erreichbar.

**Aufgabe.** Im Header einen primären Button „Jetzt anmelden" (gefüllt, `--avf-green`) ergänzen, der auf `#anmelden` scrollt. „Zum Kalender hinzufügen" bleibt als sekundäre Outline-Variante daneben. Auf Mobile (< 768 px) den primären Button als Sticky-Bar am unteren Rand einblenden, sobald das Formular ausserhalb des Viewports liegt.

### 12. NIEDRIG — Redundanz und fehlende Struktur

- „Semesterversand & FLOSS" (Titel) und „Semesterversand und Konzert am Rhein (FLOSS)" (Lead) sagen fast dasselbe. Der Lead sollte ergänzen, nicht wiederholen — Template so anlegen, dass der Lead redaktionell befüllt wird.
- „Ueli Brau Bar" ohne Adresse und ohne Kartenlink. Ort verlinken (OpenStreetMap/Google Maps) und Strasse/Ort ergänzen.
- Anmeldeschluss „19.08.2026 00:01 Uhr" am Veranstaltungstag selbst ist verwirrend. Relative Angabe ergänzen: „noch 6 Tage".
- Kein Breadcrumb. `Anlässe → Semesterversand & FLOSS` unter dem Hero ergänzen, mit `BreadcrumbList`-Schema.
- Kein `Event`-Schema.org-Markup. `JSON-LD` mit `name`, `startDate`, `endDate`, `location`, `organizer`, `eventStatus` ergänzen.

---

## Randbedingungen

1. **Bestehende Klassennamen beibehalten.** Die BEM-Struktur `avf-event-detail__*` ist sauber und wird vom PHP-Template referenziert. Klassen nur ergänzen, nicht umbenennen — es sei denn, das Template wird im selben Durchgang angepasst.
2. **Keine neuen Abhängigkeiten.** Kein Tailwind, kein Bootstrap, kein zusätzliches JS-Framework. Vanilla CSS mit Custom Properties, Vanilla JS wo nötig.
3. **Kein `!important`.** Konflikte mit Elementor über Spezifität oder Cascade Layers lösen.
4. **Farbpalette und Schriften bleiben unverändert.** Grün/Orange/Cream und Cormorant Garamond + Inter sind die Verbindungsidentität.
5. **Mobile-First.** Bestehende Breakpoints 600 px / 767 px / 900 px beibehalten und konsequent nutzen.
6. **Progressive Enhancement.** Das Anmeldeformular muss ohne JavaScript funktionieren (Server-Roundtrip ist bereits implementiert, Nonce vorhanden).

## Reihenfolge der Umsetzung

1. Header-Grid und Datumspille reparieren (Punkte 1, 2) — grösster sichtbarer Effekt
2. Status-Banner in Chip umbauen (Punkt 3)
3. Überschriften-Hierarchie und Container-Ausrichtung (Punkte 5, 6)
4. Zirkel-Dekoration zurücknehmen (Punkt 4)
5. Typo-Skala einführen (Punkt 10)
6. Mobile-Meta-Layout (Punkt 7)
7. Formular-Zugänglichkeit und Leerzustände (Punkte 8, 9)
8. CTA, Breadcrumb, Schema.org (Punkte 11, 12)

## Abnahmekriterien

- Genau ein `<h1>` im DOM; Überschriften ohne Ebenensprünge
- Datumspille ist nicht breiter als ihr Textinhalt (< 140 px)
- Header-Karte enthält keine leere Fläche > 40 px zwischen zwei Elementen
- Hero-H1, Content-Karte und Footer haben identische linke Kante (Toleranz 0 px)
- Bei 375 px, 768 px, 1024 px, 1440 px und 1920 px kein horizontaler Overflow (`scrollWidth === clientWidth`)
- Alle interaktiven Elemente ≥ 44 × 44 px Trefferfläche und mit sichtbarem `:focus-visible`-Ring
- Formular per Tastatur vollständig bedienbar; Honeypot bleibt aus dem Tab-Fokus
- Kontrast aller Textfarben ≥ 4.5:1 (Fliesstext) bzw. ≥ 3:1 (≥ 24 px)
- Lighthouse Accessibility ≥ 95
- Keine neue `!important`-Deklaration im Diff
