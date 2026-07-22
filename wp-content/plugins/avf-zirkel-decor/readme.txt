=== AV Froburger Zirkel Decor ===
Contributors: avfroburger
Tags: elementor, decoration, background
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later

Erzeugt wiederverwendbare dekorative Zirkel-Hintergründe für Elementor-Sektionen.

== Beschreibung ==

Dieses Plugin durchsucht das Frontend nach Elementen mit der Klasse `avf-decor` und fügt
automatisch eine rein dekorative, nicht interaktive Ebene mit ein bis drei Zirkel-Formen
(dem Vereinssymbol der AV Froburger) ein. Es verändert keine Inhalte, keine Datenbank und
keine bestehenden Seiten – Redaktor:innen müssen lediglich eine CSS-Klasse an ein
Elementor-Element vergeben.

Für ausgewählte, projektweit wiederkehrende Abschnitte (siehe „Automatische
Projekt-Presets" unten) genügt sogar eine rein semantische Klasse – Basisklasse, Variante,
Menge und Seed werden dann automatisch ergänzt.

== Automatische Projekt-Presets ==

Für die folgenden zwei Abschnittsklassen wendet das Plugin automatisch ein festes
Zirkel-Preset an, sobald die Klasse im Frontend gefunden wird:

* `avf-values-section` (Startseitenabschnitt „Was uns verbindet") →
  `avf-decor` + `avf-decor--light` + `avf-decor--dense` + Seed `werte-startseite`
* `avf-quote-section` (Zitatabschnitt) →
  `avf-decor` + `avf-decor--dark` + `avf-decor--subtle` + Seed `zitat-startseite`

Redaktor:innen müssen für diese beiden Bereiche in Elementor unter „Erweitert →
CSS-Klassen" nur noch die jeweilige semantische Klasse eintragen:

`avf-values-section`

beziehungsweise:

`avf-quote-section`

Alle übrigen Klassen (`avf-decor`, Varianten-, Mengenklasse) sowie der Seed werden vom
Plugin automatisch ergänzt und müssen nicht mehr manuell gesetzt werden. Bereits vorhandene
manuelle Angaben werden dabei nie überschrieben (siehe „Priorität manueller Angaben"
unten) – ein bestehendes `data-avf-decor-seed`-Attribut oder eine bereits gesetzte
Varianten-/Mengenklasse bleiben unangetastet und dürfen problemlos stehen bleiben.

=== Priorität manueller Angaben ===

Für Elemente, die einer der beiden Presets entsprechen, gilt:

* Ist bereits `avf-decor--light` oder `avf-decor--dark` gesetzt, ergänzt das Preset keine
  zweite Variantenklasse.
* Ist bereits `avf-decor--subtle` oder `avf-decor--dense` gesetzt, ergänzt das Preset keine
  zweite Mengenklasse.
* Ist bereits ein nicht-leeres `data-avf-decor-seed`-Attribut vorhanden, wird dieser Wert
  verwendet statt des Preset-Seeds.
* `avf-decor--desktop-only` funktioniert weiterhin unabhängig und kann zusätzlich manuell
  ergänzt werden.

== Verwendung (manuell, für alle anderen Abschnitte) ==

In Elementor ein Flexbox- oder Grid-Element (z. B. eine Section oder einen Container)
auswählen, unter „Erweitert → CSS-Klassen“ die Klasse hinzufügen:

`avf-decor avf-decor--light`

Weitere Beispiele:

`avf-decor avf-decor--light avf-decor--subtle`
`avf-decor avf-decor--dark`
`avf-decor avf-decor--light avf-decor--dense`
`avf-decor avf-decor--light avf-decor--desktop-only`

=== Verfügbare Klassen ===

* `avf-decor` – Basisklasse, zwingend erforderlich. Ohne Mengenklasse: 2 Zirkel (ein grüner,
  ein oranger).
* `avf-decor--light` – helle/warmweisse Sektion. Farben: Grün und Orange.
* `avf-decor--dark` – dunkle Sektion. Farben: Weiss statt Grün, Orange bleibt Orange.
  Deckkraft niedriger als bei `--light`.
* `avf-decor--subtle` – nur 1 Zirkel. Hat Vorrang, falls gleichzeitig `--dense` gesetzt ist.
* `avf-decor--dense` – 3 Zirkel (dritte Form wiederholt eine der beiden Farben).
* `avf-decor--desktop-only` – blendet alle Zirkel unterhalb von ca. 560 px Breite komplett
  aus.

=== Optionaler Seed ===

Die Platzierung ist deterministisch (siehe unten), springt bei einem normalen Seitenneuladen
also nicht. Für den seltenen Fall, dass zwei `avf-decor`-Elemente an derselben Position im
DOM zufällig dieselbe Optik erhalten und das unerwünscht ist, kann pro Element ein manueller
Seed vergeben werden:

`data-avf-decor-seed|werte-startseite`

Diese Zeile in das Elementor-Feld „Erweitert → Attribute → Custom Attributes“ eintragen
(Format `schlüssel|wert`, eine Zeile pro Attribut – das ist die von Elementor Pro in diesem
Projekt tatsächlich verwendete Syntax für das Feld „Custom Attributes“). Ergebnis im HTML:

`<section class="avf-decor avf-decor--light" data-avf-decor-seed="werte-startseite">`

=== Empfehlungen ===

* Maximal ein dekoriertes Hauptelement pro grösserem Abschnitt verwenden.
* Nicht jede einzelne Karte innerhalb eines Abschnitts dekorieren.
* Auf ruhige, eher leere Hintergrundflächen anwenden (z. B. Textabschnitte, Zwischenräume).
* Nicht auf Hero, Navigation oder Footer anwenden, sofern dort bereits starke Bilder oder
  eigene Gestaltung vorhanden sind.

== Funktionsweise ==

Ein kleines JavaScript ohne Abhängigkeiten (kein jQuery) durchsucht beim Laden der Seite
einmal das Dokument nach `.avf-decor`-Elementen sowie nach den Auto-Preset-Selektoren
(`.avf-values-section`, `.avf-quote-section`) – in einer einzigen kombinierten Abfrage, kein
mehrfaches Scannen des Dokuments. Für Treffer über eine Auto-Preset-Klasse werden zuerst die
fehlenden Klassen/der Seed ergänzt (siehe „Automatische Projekt-Presets"), danach läuft die
Dekoration wie gewohnt weiter und fügt eine `.avf-zirkel-decor-layer`-Ebene mit
`aria-hidden="true"` ein. Ein `MutationObserver` auf `document.body` verarbeitet mit
derselben kombinierten Abfrage zusätzlich Elemente, die Elementor später dynamisch einfügt
(z. B. beim Duplizieren einer Sektion oder in der Vorschau), und reagiert nur auf neu
hinzugefügte Elemente, nicht auf jede kleine Textänderung. Bereits verarbeitete Elemente
werden über `data-avf-decor-ready="true"` markiert und nie doppelt bearbeitet.

Die Position, Grösse, Rotation und Deckkraft jedes Zirkels wird deterministisch aus dem
URL-Pfad, dem Index der Sektion auf der Seite, dem Index des Zirkels und dem optionalen
`data-avf-decor-seed`-Attribut berechnet (einfacher FNV-1a-Hash, keine externe Bibliothek,
keine Kryptografie). Bei unverändertem DOM ergibt derselbe Pfad immer dieselbe Optik.

Die Zirkel-Grösse passt sich beim Ändern der Fensterbreite automatisch an die passende
Grössenklasse an (Desktop/Tablet/Smartphone), ohne die übrige Platzierung neu zu würfeln.

Farbe und Form entstehen über eine CSS-Maske (`mask-image` / `-webkit-mask-image`) auf Basis
des lokalen SVGs `assets/images/zirkel.svg`, eingefärbt über `background-color: currentColor`.

== Barrierefreiheit ==

Die Dekoration ist vollständig ignorierbar: `aria-hidden="true"`, `pointer-events: none`,
kein Text, keine Links, nicht fokussierbar, keine Auswirkung auf die Tab-Reihenfolge oder den
Lesefluss. Es findet keine Animation, kein Parallax und keine Mausverfolgung statt;
`prefers-reduced-motion` wird zusätzlich defensiv respektiert.

== Seitenweiter Modus (".avf-home-main" / ".avf-has-zirkel-bg") ==

Unabhängig vom oben beschriebenen `.avf-decor`-System erkennt das Plugin zusätzlich
automatisch Elemente mit der Klasse `avf-home-main` (der Hauptinhalt-Container der
Startseite, siehe `avf-homepage-layout`-Plugin) **sowie jeden beliebigen Container mit
der Klasse `avf-has-zirkel-bg`** und fügt jeweils genau eine eigene Dekorationsebene über
die **gesamte Höhe** dieses Containers ein:

=== Verwendung auf beliebigen Seiten ===

Für jede andere Seite (z. B. „Aktuelles") genügt es, dem gewünschten Elementor-Container
unter „Erweitert → CSS-Klassen" die Klasse

`avf-has-zirkel-bg`

zuzuweisen. Kein zusätzliches Widget, kein Shortcode, kein manuell eingefügtes Bild ist
nötig – dieselbe Verteilungslogik, dieselben Farben (`--avf-green`/`--avf-orange`) und
derselbe Schutz vor doppelten Ebenen wie bei `.avf-home-main` gelten automatisch. Mehrere
`.avf-has-zirkel-bg`-Container auf derselben Seite sind möglich; jeder erhält eine eigene,
unabhängig berechnete Ebene.

`<div class="avf-page-zirkel-decor-layer" aria-hidden="true">…</div>`

=== Verteilungslogik (Version 1.3.0) ===

Ring-Anzahl: ungefähr 1 Zirkel pro 700 px Containerhöhe, begrenzt auf 5–9 (Desktop), 3–7
(Tablet) beziehungsweise 2–4 (Smartphone). Die tatsächlich platzierte Anzahl kann darunter
liegen, falls für einzelne Zirkel keine kollisionsfreie Position gefunden wird (siehe unten).

Jeder Zirkel erhält unabhängig eine zufällige horizontale Position, vertikale Position,
Grösse, Rotation, Deckkraft und Farbe (Grün oder Orange, nie beide gleichzeitig). "Zufällig"
bedeutet hier: einmalig zufällig erzeugt und über einen deterministischen Seed dauerhaft
gespeichert – die Anordnung ändert sich bei einem normalen Seitenreload nicht, springt also
nicht unruhig herum. Sie ändert sich nur, wenn sich der Seed selbst ändert (siehe „Seed"
unten), zum Beispiel beim Wechsel zwischen Desktop/Tablet/Smartphone.

Horizontal sind rund 70% der Zirkel am linken oder rechten Rand platziert (30–60% ihrer
Fläche ragt dabei über den Container hinaus), die übrigen liegen näher an der horizontalen
Mitte. Vertikal wird die Containerhöhe in ungefähr gleich grosse, deterministisch gemischte
Zonen unterteilt (eine Zone pro gewünschtem Zirkel), sodass die Zirkel über die gesamte Höhe
verteilt bleiben statt sich zufällig zu häufen.

Kollisionsvermeidung: Jeder Zirkel wird als vereinfachter Kreis (Zentrum + Radius = halbe
Grösse) behandelt. Für jeden Zirkel werden zufällige Kandidaten erzeugt und gegen alle bereits
platzierten Zirkel auf Überlappung geprüft (inklusive Sicherheitsabstand: 50–80 px Desktop,
35–60 px Tablet, 20–40 px Smartphone). Bei einer Kollision wird ein neuer Kandidat erzeugt
(Rejection Sampling), maximal rund 220 Versuche pro Zirkel über mehrere Durchgänge mit
schrittweise verkleinerter Grösse. Bleibt ein Zirkel danach ohne Platz, wird er ausgelassen –
es gibt keine Endlosschleife.

Weiche Sperrzonen (nur in der ersten Versuchsrunde geprüft, blockieren die Platzierung also
nie vollständig) halten die Zirkel tendenziell von der Mitte des Featured-Post-Inhalts, der
Überschrift „Was uns verbindet" und der Mitte des Zitattexts fern.

Deckkraft: 0.08–0.15 auf hellen Flächen, 0.05–0.10 sobald ein Zirkel im dunklen Zitatband
(`.avf-home-quote`) liegt – automatisch anhand der vertikalen Position erkannt. Rotation:
etwa −35° bis +35°.

Farbe: pro Zirkel unabhängig zufällig Grün oder Orange. Bei zwei oder mehr Zirkeln wird für
den letzten Zirkel die Gegenfarbe erzwungen, falls alle bisher platzierten Zirkel dieselbe
Farbe tragen – so kommen bei mehreren Zirkeln praktisch immer beide Farben vor, ohne die
Verteilung insgesamt unnatürlich wirken zu lassen.

=== Seed ===

Der Seed für diesen Modus enthält mindestens: `window.location.pathname`, das optionale
`data-avf-decor-seed`-Attribut, den Index des Containers (falls mehrere `.avf-home-main`-
Elemente existieren) sowie den aktuellen Responsive-Bucket (Desktop/Tablet/Smartphone). Die
Anordnung bleibt dadurch über Reloads hinweg exakt identisch, ändert sich aber bewusst, wenn
der Viewport in einen anderen Responsive-Bucket wechselt (der Seed enthält den Bucket-Namen).

Position, Grösse, Rotation, Deckkraft und Farbe sind wie beim `.avf-decor`-System vollständig
deterministisch (kein `Math.random()`; ein seed-basierter Pseudozufallsgenerator, Mulberry32,
gespeist aus einem FNV-1a-Hash des obigen Seeds). Zwei unabhängige Seitenreloads erzeugen
immer dieselbe Anordnung.

Keine Interaktion mit dem normalen `.avf-decor`-Mechanismus; eigene Ready-Markierung
(`data-avf-page-decor-ready`) verhindert doppelte Ebenen – pro Container existiert
höchstens eine `.avf-page-zirkel-decor-layer` als direktes Kind, unabhängig davon, wie oft
die Initialisierung läuft. Ein Rebuild findet ausschliesslich statt bei: erstmaliger
Initialisierung, Wechsel des Responsive-Buckets oder einer deutlichen Änderung der
Containerhöhe (mehr als 60 px bzw. 8%) – gedrosselt über `requestAnimationFrame`, kein
dauerhafter Timer.

Die strukturellen Regeln (`position:relative`, `isolation:isolate`, `overflow:hidden` auf
dem Container; `position:absolute`, `z-index:0`, `pointer-events:none` auf der Ebene) sowie
die Ringfarben (`--avf-green`/`--avf-orange`) liegen für `.avf-has-zirkel-bg` vollständig in
diesem Plugin (`zirkel-decor.css`), ancestor-agnostisch – kein `body.home`-Selektor ist dafür
nötig. Für `.avf-home-main` liegt nur die Positionierung weiterhin im
`avf-homepage-layout`-Plugin (`homepage.css`), da sie dort an die bestehende Startseiten-
Struktur gebunden ist; die Ringfarben gelten dank der ancestor-agnostischen Regel hier auch
für `.avf-home-main` automatisch mit.

=== Debug-Modus ===

Wird `window.AVF_ZIRKEL_DEBUG = true` gesetzt (z. B. in der Browser-Konsole vor dem
Neuladen), gibt das Skript für jeden `.avf-home-main`-Container eine Übersichtstabelle in der
Konsole aus: gewünschte und tatsächlich platzierte Anzahl, Versuche pro Zirkel, Position,
Grösse, Farbe, Deckkraft und Abstand zum nächsten Zirkel. Im normalen Betrieb (Standardwert
`false`/nicht gesetzt) erzeugt das Skript keinerlei Konsolenausgaben.

=== Editor-Verhalten ===

Im Elementor-Editor verwendet dieser Modus dieselbe deterministische Verteilung wie das
Frontend, mit leicht reduzierter Deckkraft (Faktor 0.6) während der Live-Vorschau. Die Ebene
bleibt `position:absolute` mit `pointer-events:none`, nimmt keinen Platz im Flexbox-/
Grid-Fluss ein und verschiebt keine Elementor-Elemente – die Bearbeitung ist dadurch nicht
eingeschränkt.

== Nicht Teil dieses Plugins ==

Kein Shortcode, kein Elementor-Widget, kein Gutenberg-Block, keine Einstellungsseite, keine
Datenbankoptionen, keine externen Grafiken, keine Animationen.

== Changelog ==

= 1.6.0 =
* Dritter, unabhängiger Dekorationsmodus: `.avf-page-head` (der globale
  Seitenkopf, Elementor-Vorlage "AVF - Seitenvorlage", aktiv auf allen
  normalen Seiten ausser der Startseite) erhält automatisch zwei feste,
  art-direktierte Zirkel - einen grossen rechts (grün, am Rand angeschnitten),
  einen kleinen oben links (orange). Bewusst nicht seed-basiert/zufällig wie
  die beiden anderen Modi: eine Kopfzeile profitiert von einer bewussten
  Komposition statt Seite-zu-Seite-Variation.
* Neue CSS-Regeln für `.avf-page-head__ring--large/--small` inkl.
  responsivem Verhalten (kleiner auf Tablet, nur ein Ring auf Mobile).
* Teil der grösseren Überarbeitung von "Aktuelles": siehe auch
  `avf-site-styles` (Hintergrund-Verlauf, Couleur-Linie, Breadcrumb) für den
  Rest des neuen Seitenkopf-Looks.

= 1.5.1 =
* Fix: eine invalide, verschachtelte Doppel-Deklaration von
  ".avf-has-zirkel-bg .elementor-loop-container.elementor-grid" (Selektor
  fälschlich als Regel innerhalb der eigenen Regel notiert) hatte die
  gesamte Grid-Spalten-/Zentrierungsregel wirkungslos gemacht - Elementors
  eigene, feste 3/2/1-Spalten-Regel gewann dadurch unbemerkt wieder.
* Zentrierung korrigiert: `auto-fill` durch `auto-fit` ersetzt (kollabiert
  ungenutzte Spuren bei weniger Beiträgen als Spalten) plus
  `justify-content:center` auf dem Grid selbst - 1 Beitrag zentriert sich
  jetzt allein, 2 Beiträge zentrieren sich gemeinsam, volle Reihen nutzen
  die Breite gleichmässig ohne Leerraum.
* Live per Chrome-DevTools-Protocol verifiziert: die zuvor "gewinnende"
  Regel war exakt Elementors `.elementor-grid-3 .elementor-grid{grid-
  template-columns:repeat(3,1fr)}` (495px-Spalten) statt der eigenen
  Plugin-Regel.

= 1.5.0 =
* Deckkraft der Zirkel auf `.avf-has-zirkel-bg`-Seiten erhöht (grün ~0.22,
  orange ~0.20, über die neuen CSS-Variablen `--avf-ring-opacity-green` /
  `--avf-ring-opacity-orange`), unabhängig von der Startseite, deren
  Deckkraft unverändert bleibt.
* Ring-Anzahl-Untergrenzen (`PAGE_RING_COUNT_LIMITS`) abgesenkt
  (Desktop 3, Tablet 2, Mobile 1 statt 5/3/2), damit kurze
  `.avf-has-zirkel-bg`-Container (z. B. eine Seite mit nur 1-2 Beiträgen)
  nicht auf die Zirkel-Dichte der viel längeren Startseite gezwungen
  werden. Auf der Startseite selbst ohne Effekt, da deren berechnete
  Roh-Anzahl (Höhe / 700px) die alten wie neuen Untergrenzen bereits
  übersteigt.
* Neue, wiederverwendbare Layout-Regeln für eine Beitragsübersicht
  (Elementor Loop Grid + `.avf-post-card`) innerhalb von
  `.avf-has-zirkel-bg`: echtes containerbreiten-basiertes CSS-Grid
  (`repeat(auto-fill, minmax(min(100%,280px),350px))`) statt Elementors
  fester Desktop/Tablet/Mobile-Spaltenzahl, plus Neutralisierung eines
  `max-width:400px` auf dem Loop-Item-Wrapper (Template „AVF – Beitrags­
  kachel"), das Karten unabhängig von ihrer Grid-Zelle auf ~400px
  begrenzte - siehe Aufgabenbericht für die vollständige Ursachenanalyse.
* Live an vier Containerbreiten verifiziert (siehe Bericht): 1600px → 4
  Spalten, 1300px → 3, 800px → 2, 375px → 1 Spalte, Kartenbreite durchweg
  ~280-350px, kein horizontaler Overflow.

= 1.4.0 =
* Der seitenweite Dekorationsmodus ist jetzt eine wiederverwendbare, allgemeine Komponente:
  zusätzlich zu `.avf-home-main` erkennt das Plugin jeden Container mit der Klasse
  `avf-has-zirkel-bg` (Elementor „Erweitert → CSS-Klassen") und fügt ihm automatisch seine
  eigene `.avf-page-zirkel-decor-layer` ein – Frontend, MutationObserver und
  Elementor-Editor-Vorschau eingeschlossen.
* Ringfarben (`--avf-green`/`--avf-orange`) des seitenweiten Modus sind jetzt
  ancestor-agnostisch in `zirkel-decor.css` definiert statt `body.home`-gebunden in
  `homepage.css`; funktionieren dadurch identisch auf `.avf-home-main` und jedem
  `.avf-has-zirkel-bg`-Container.
* CSS-Spezifität der Stacking-Context-Regel für `.avf-has-zirkel-bg` auf `:where()`
  reduziert (wie bei `.avf-decor`) und die Ebenen-Regel auf den direkten Kind-Selektor
  (`>`) präzisiert, damit Links, Buttons und Elementor-Widgets zuverlässig anklickbar
  bleiben und pro Container nie mehr als eine Ebene entstehen kann.
* Verhalten von `.avf-home-main` und der Startseite vollständig unverändert.

= 1.3.0 =
* Verteilungslogik des seitenweiten Modus (`.avf-home-main`) vollständig überarbeitet: echte
  kollisionsfreie Zufallsplatzierung (Rejection Sampling mit vereinfachten kreisförmigen
  Bounding-Boxen, Sicherheitsabstand responsive 20–80 px) statt fester Links/Rechts- und
  Top/Bottom-Bänder.
* Jeder Zirkel erhält unabhängig zufällige Grösse (responsive 190–680 px), Rotation
  (−35° bis +35°), Deckkraft (0.05–0.15, automatisch dunklere Werte im Zitatband) und Farbe
  (Grün/Orange), mit Ausgleichslogik gegen "alle Zirkel gleiche Farbe" bei zwei oder mehr
  Zirkeln.
* Neue weiche Sperrzonen um Featured-Post-Inhalt, Werte-Überschrift und Zitattext.
* Seed um Containerindex und Responsive-Bucket erweitert; Anordnung bleibt über Reloads
  stabil, ändert sich gezielt nur bei Bucket-Wechsel.
* Rebuild bei Resize jetzt zusätzlich bei deutlicher Containerhöhen-Änderung, nicht nur bei
  Bucket-Wechsel; verhindert doppelte Ebenen durch Entfernen der alten Ebene vor dem Rebuild.
* Neuer Debug-Modus (`window.AVF_ZIRKEL_DEBUG = true`) mit Konsolen-Tabelle pro Container;
  keine Ausgaben im Normalbetrieb.
* Leicht reduzierte Deckkraft im Elementor-Editor (Faktor 0.6) während der Live-Vorschau.
* Cache-Busting der Assets hängt nicht mehr an `WP_DEBUG`, sondern immer an der
  Datei-Änderungszeit.
* Das `.avf-decor`-System (manuelle Sektionen, Presets, Klassen `--light`/`--dark`/
  `--subtle`/`--dense`/`--desktop-only`) ist unverändert und vollständig rückwärtskompatibel.

= 1.2.0 =
* Neuer, unabhängiger seitenweiter Dekorationsmodus für `.avf-home-main` (5–9 Zirkel über
  die volle Containerhöhe verteilt, responsive begrenzt), für den Neuaufbau der Startseite
  im `avf-homepage-layout`-Plugin.

= 1.1.0 =
* Automatische Projekt-Presets für `avf-values-section` (light + dense + Seed
  `werte-startseite`) und `avf-quote-section` (dark + subtle + Seed `zitat-startseite`).
  Manuelle Klassen und ein manueller Seed haben weiterhin Vorrang.
* Suche und `MutationObserver` erkennen nun zusätzlich zu `.avf-decor` auch die beiden
  Auto-Preset-Selektoren, in einer gemeinsamen Abfrage.
* CSS-Regeln für `.avf-values-section` ergänzt, damit die Zirkel hinter Überschrift und
  Karten sichtbar bleiben, ohne bestehende Elementor-Positionierungen zu beschädigen.
* Deckkraft-Bereich der Light-Variante leicht angehoben (0.07–0.12 statt 0.055–0.12) für
  bessere Sichtbarkeit auf hellen Flächen.

= 1.0.0 =
* Erste Version: automatische Zirkel-Dekoration für Elemente mit der Klasse `avf-decor`.
