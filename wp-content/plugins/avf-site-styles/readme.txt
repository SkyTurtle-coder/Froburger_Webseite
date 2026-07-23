=== AV Froburger Site Styles ===
Contributors: avfroburger
Tags: elementor, layout, bugfix
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.4
License: GPLv2 or later

Enthaelt gezielte projektspezifische Layoutkorrekturen.

== Beschreibung ==

Sammelplugin fuer kleine, gezielte CSS-Korrekturen, die nicht in ein bestimmtes
Feature-Plugin gehoeren. Laedt ein einziges Stylesheet (`assets/css/site-fixes.css`) im
Frontend, nach den Elementor- und Theme-Stilen, damit gezielte Ueberschreibungen sicher
greifen. Keine Datenbanktabellen, keine Optionen, kein JavaScript.

== Fix: Header/Hero-Spalt auf der Startseite ==

=== Symptom ===

Zwischen dem gruenen Elementor-Header und dem Hero-Bild der Startseite erschien ein
weisser und darunter ein grauer horizontaler Streifen.

=== Diagnose ===

Untersucht am 2026-07-21 per Live-DOM-/Computed-Style-Messung (Microsoft Edge headless,
Viewports 390-1920 px, Pixel-Farbabgleich mit PHP/GD) auf der tatsaechlichen Frontpage
(ermittelt ueber `wp option get page_on_front` = Post-ID 20, Seitenvorlage
`elementor_header_footer`).

Zum Zeitpunkt der Untersuchung war der Spalt bereits **nicht mehr messbar**
(`headerRect.bottom` und `heroRect.top` lagen deckungsgleich, 0 px Differenz, an allen
getesteten Breiten). Ursache: Im Elementor-Kit "Standard-Kit" (Post-ID 10, Custom CSS,
Elementor-Datenbank) existiert bereits ein Block

`/* STARTSEITE - WORDPRESS-/ELEMENTOR-SEITENRAHMEN ZURUECKSETZEN. Entfernt insbesondere
den weissen Spalt zwischen Header und Hero. */`

der u. a. `body.home .elementor-location-header`, `body.home main#content`,
`body.home .elementor[data-elementor-type="wp-page"]` und die jeweils direkt
darauffolgenden Elemente (`+ *` / `:first-child`) auf `margin/padding/border: 0` setzt.

Da dieser Fix nur in der Elementor-Datenbank (Kit-Custom-CSS) existiert und nicht
versioniert/dateibasiert ist, dupliziert dieses Plugin die fuer die **aktuelle** Startseite
tatsaechlich wirksamen zwei Regeln in einer schlanken, dateibasierten Form:

`body.home .elementor-location-header` (Header-Wrapper)
`body.home .elementor[data-elementor-type="wp-page"]` (Seiten-Wrapper, dessen erstes
Kind `.avf-hero` ist)

`main#content`, `#content`, `.site-main`, `.page-content` wurden absichtlich NICHT
uebernommen: Bei der Seitenvorlage `elementor_header_footer` rendert das Theme diese
Wrapper gar nicht (im DOM nicht vorhanden), sie waeren auf dieser Seite wirkungslos. Sollte
die Seitenvorlage der Startseite kuenftig geaendert werden, muss dieser Fix erneut geprueft
werden.

`.avf-hero` selbst setzt seinen eigenen `margin` bereits unbedingt (nicht auf `body.home`
beschraenkt) ueber die gleiche Kit-Custom-CSS - daher hier nicht zusaetzlich noetig.

=== Empfehlung ===

Der oben genannte Custom-CSS-Block im Elementor-Kit "Standard-Kit" kann kuenftig auf die
Teile reduziert werden, die dieses Plugin nicht abdeckt (falls ueberhaupt noch benoetigt),
um Doppelpflege zu vermeiden. Dies wurde in diesem Schritt bewusst NICHT automatisiert
geaendert (keine Aenderung an der Elementor-Datenbank).

== Abschnitt: Regelmaessige regionale Staemme ==

Dieses Plugin enthaelt zusaetzlich die zentrale, dateibasierte
Gestaltungsgrundlage fuer einen statischen Elementor-Abschnitt
"Regelmaessige regionale Staemme". Die Inhalte selbst werden weiterhin
manuell in Elementor gepflegt; dieses Plugin liefert nur die
wiederverwendbaren Klassen und das responsive Kartenlayout.

=== Benoetigte Elementor-Struktur ===

1. Aeusserer Abschnitt/Container:
   Klasse `avf-regional-staemme`
2. Innerer Begrenzungs-Container:
   Klasse `avf-regional-staemme__inner`
3. Kopfbereich-Container:
   Klasse `avf-regional-staemme__heading`
4. Eyebrow-Text:
   Klasse `avf-regional-staemme__eyebrow`
5. Titel:
   Klasse `avf-regional-staemme__title`
6. Einleitungstext:
   Klasse `avf-regional-staemme__intro`
7. Kartenraster-Container:
   Klasse `avf-regional-staemme__grid`
8. Pro Karte ein Container mit:
   Klasse `avf-regional-stamm-card`
9. In jeder Karte ein Kopf-Container mit:
   Klasse `avf-regional-stamm-card__header`
10. Wappen-Container:
    Klasse `avf-regional-stamm-card__crest`
11. Kartentitel:
    Klasse `avf-regional-stamm-card__title`
12. Detailtext/Adresse:
    Klasse `avf-regional-stamm-card__details`
13. Kontaktblock:
    Klasse `avf-regional-stamm-card__contact`

=== Hinweise ===

* Das Wappen am besten als Bild-Widget innerhalb von
  `avf-regional-stamm-card__crest` platzieren.
* Kontaktlinks (Mail, Telefon, Website) direkt im Kontaktblock anlegen;
  die Fokuszustaende kommen automatisch aus dem Plugin-CSS.
* Das Grid ist auf 3 Karten Desktop, 2 Karten Tablet und 1 Karte Mobil
  ausgelegt. Bei genau 3 Karten wird die letzte Karte auf Tablet
  automatisch mittig ueber beide Spalten gezogen.

== Changelog ==

= 1.2.4 =
* Die drei Hauptbereiche der Seite "Anlaesse" - kommender Event-Heading,
  regionale Staemme und Rueckblick - auf eine gemeinsame, zentrierte
  1200px-Inhaltsachse gebracht. Dabei wurden unterschiedliche Elementor-
  Container-Paddings, Margins und Width-Begrenzungen vereinheitlicht,
  ohne Eventkarten, Stammkarten oder Shortcode-Komponenten selbst zu
  veraendern.

= 1.2.3 =
* Abschnitt "Vergangene Veranstaltungen" auf der Seite "Anlaesse" als
  zusammenhaengenden, transparenten Inhaltsblock ausgerichtet: gemeinsamer
  1200px-Innenbereich fuer Eyebrow, Titel, Einleitung und die nachfolgende
  Shortcode-Liste, ohne Elementor-IDs oder generierte CSS-Dateien.
* Der direkt folgende Elementor-Shortcode-Container wird dateibasiert auf
  volle Inhaltsbreite gebracht, so dass die Rueckblick-Liste nicht mehr in
  einem schmaleren Boxed-Wrapper haengt und der bestehende Zirkelhintergrund
  sichtbar bleibt.

= 1.2.2 =
* Im Zirkelbereich (`.avf-has-zirkel-bg`) den deckenden Hintergrund hinter
  dem Abschnitt "Regelmaessige regionale Staemme" transparent gemacht, so
  dass die dekorativen Zirkel wieder durch den hellen Seitenbereich sichtbar
  bleiben, waehrend die Karten selbst bewusst weiss bleiben.

= 1.2.1 =
* Kartenstil des statischen Elementor-Abschnitts "Regelmaessige regionale
  Staemme" gegen die tatsaechliche Live-Struktur verfeinert: robuste
  Selektoren fuer die aktuellen Kartencontainer, echtes 3/2/1-Layout,
  staerkere Kartenabgrenzung, sauberer Kopf mit Titel/Wappen, gleich hohe
  Karten und stabiler Kontaktblock am unteren Rand.

= 1.2.0 =
* Neue dateibasierte CSS-Grundlage fuer den statischen Elementor-Abschnitt
  "Regelmaessige regionale Staemme" mit responsivem 3/2/1-Kartenlayout,
  Couleur-Akzenten, Fokuszustaenden und Elementor-Bauanleitung im Readme.

= 1.1.1 =
* Breadcrumb-Leiste ("Startseite / <Seitentitel>") ueber dem globalen
  Seitenkopf wieder vollstaendig entfernt - sowohl die PHP-Ausgabe
  (`avf_site_styles_print_breadcrumb()`, Hook auf
  `elementor/theme/before_do_single`) als auch die zugehoerigen
  `.avf-page-breadcrumb*`-CSS-Regeln. Der gruene Seitenkopf beginnt dadurch
  wieder direkt unter dem globalen Header, ohne Luecke.

= 1.1.0 =
* Globaler Seitenkopf (".avf-page-head", Elementor-Vorlage "AVF - Seitenvorlage",
  aktiv auf allen Seiten ausser der Startseite) gestalterisch aufgewertet:
  diagonaler Gruen-Verlauf mit zwei sanften Licht-Zonen statt flachem
  Vollton-Hintergrund, eine dreifarbige Couleur-Linie (Gruen/Weiss/Orange) am
  unteren Rand.
* Hinweis: Die Couleur-Linie wird bewusst als eigene CSS-Regel gefuehrt statt
  ueber das `custom_css`-Feld der Elementor-Vorlage - dessen "selector"-
  Platzhalter wird nur beim Speichern im Elementor-Editor selbst ersetzt,
  nicht bei der CSS-Generierung, wodurch direkt in der Datenbank gesetzte
  `custom_css`-Werte auf dieser Installation wirkungslos bleiben.

= 1.0.0 =
* Header/Hero-Spalt auf der Startseite dateibasiert und robust gegen Kit-CSS-Aenderungen
  abgesichert.
