=== AV Froburger Site Styles ===
Contributors: avfroburger
Tags: elementor, layout, bugfix
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later

Enthält gezielte projektspezifische Layoutkorrekturen.

== Beschreibung ==

Sammelplugin für kleine, gezielte CSS-Korrekturen, die nicht in ein bestimmtes
Feature-Plugin gehören. Lädt ein einziges Stylesheet (`assets/css/site-fixes.css`) im
Frontend, nach den Elementor- und Theme-Stilen, damit gezielte Überschreibungen sicher
greifen. Keine Datenbanktabellen, keine Optionen, kein JavaScript.

== Fix: Header/Hero-Spalt auf der Startseite ==

=== Symptom ===

Zwischen dem grünen Elementor-Header und dem Hero-Bild der Startseite erschien ein
weisser und darunter ein grauer horizontaler Streifen.

=== Diagnose ===

Untersucht am 2026-07-21 per Live-DOM-/Computed-Style-Messung (Microsoft Edge headless,
Viewports 390–1920 px, Pixel-Farbabgleich mit PHP/GD) auf der tatsächlichen Frontpage
(ermittelt über `wp option get page_on_front` = Post-ID 20, Seitenvorlage
`elementor_header_footer`).

Zum Zeitpunkt der Untersuchung war der Spalt bereits **nicht mehr messbar**
(`headerRect.bottom` und `heroRect.top` lagen deckungsgleich, 0 px Differenz, an allen
getesteten Breiten). Ursache: Im Elementor-Kit „Standard-Kit" (Post-ID 10, Custom CSS,
Elementor-Datenbank) existiert bereits ein Block

`/* STARTSEITE – WORDPRESS-/ELEMENTOR-SEITENRAHMEN ZURÜCKSETZEN. Entfernt insbesondere
den weissen Spalt zwischen Header und Hero. */`

der u. a. `body.home .elementor-location-header`, `body.home main#content`,
`body.home .elementor[data-elementor-type="wp-page"]` und die jeweils direkt
darauffolgenden Elemente (`+ *` / `:first-child`) auf `margin/padding/border: 0` setzt.

Da dieser Fix nur in der Elementor-Datenbank (Kit-Custom-CSS) existiert und nicht
versioniert/dateibasiert ist, dupliziert dieses Plugin die für die **aktuelle** Startseite
tatsächlich wirksamen zwei Regeln in einer schlanken, dateibasierten Form:

`body.home .elementor-location-header` (Header-Wrapper)
`body.home .elementor[data-elementor-type="wp-page"]` (Seiten-Wrapper, dessen erstes
Kind `.avf-hero` ist)

`main#content`, `#content`, `.site-main`, `.page-content` wurden absichtlich NICHT
übernommen: Bei der Seitenvorlage `elementor_header_footer` rendert das Theme diese
Wrapper gar nicht (im DOM nicht vorhanden), sie wären auf dieser Seite wirkungslos. Sollte
die Seitenvorlage der Startseite künftig geändert werden, muss dieser Fix erneut geprüft
werden.

`.avf-hero` selbst setzt seinen eigenen `margin` bereits unbedingt (nicht auf `body.home`
beschränkt) über die gleiche Kit-Custom-CSS – daher hier nicht zusätzlich nötig.

=== Empfehlung ===

Der oben genannte Custom-CSS-Block im Elementor-Kit „Standard-Kit" kann künftig auf die
Teile reduziert werden, die dieses Plugin nicht abdeckt (falls überhaupt noch benötigt),
um Doppelpflege zu vermeiden. Dies wurde in diesem Schritt bewusst NICHT automatisiert
geändert (keine Änderung an der Elementor-Datenbank).

== Changelog ==

= 1.1.1 =
* Breadcrumb-Leiste ("Startseite / <Seitentitel>") über dem globalen
  Seitenkopf wieder vollständig entfernt - sowohl die PHP-Ausgabe
  (`avf_site_styles_print_breadcrumb()`, Hook auf
  `elementor/theme/before_do_single`) als auch die zugehörigen
  `.avf-page-breadcrumb*`-CSS-Regeln. Der grüne Seitenkopf beginnt dadurch
  wieder direkt unter dem globalen Header, ohne Lücke.

= 1.1.0 =
* Globaler Seitenkopf (".avf-page-head", Elementor-Vorlage "AVF - Seitenvorlage",
  aktiv auf allen Seiten ausser der Startseite) gestalterisch aufgewertet:
  diagonaler Grün-Verlauf mit zwei sanften Licht-Zonen statt flachem
  Vollton-Hintergrund, eine dreifarbige Couleur-Linie (Grün/Weiss/Orange) am
  unteren Rand.
* Hinweis: Die Couleur-Linie wird bewusst als eigene CSS-Regel geführt statt
  über das `custom_css`-Feld der Elementor-Vorlage - dessen "selector"-
  Platzhalter wird nur beim Speichern im Elementor-Editor selbst ersetzt,
  nicht bei der CSS-Generierung, wodurch direkt in der Datenbank gesetzte
  `custom_css`-Werte auf dieser Installation wirkungslos bleiben.

= 1.0.0 =
* Header/Hero-Spalt auf der Startseite dateibasiert und robust gegen Kit-CSS-Änderungen
  abgesichert.
