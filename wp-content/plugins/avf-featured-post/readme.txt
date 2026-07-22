=== AV Froburger Featured Post ===
Contributors: avfroburger
Tags: posts, shortcode, elementor, sticky post
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Zeigt den favorisierten öffentlichen WordPress-Beitrag auf der Startseite an.

== Beschreibung ==

Dieses Plugin stellt den Shortcode `[avf_featured_post]` bereit, der automatisch genau einen
veröffentlichten WordPress-Beitrag anzeigt. Es wird kein separates "Favoriten"-Feld benötigt:
Redaktor:innen verwenden die eingebaute WordPress-Funktion „An der Startseite halten“
(Sticky Post).

== Installation ==

1. Plugin-Ordner nach `wp-content/plugins/avf-featured-post/` hochladen (oder bereits
   vorhandenen Ordner verwenden).
2. Plugin im Backend unter „Plugins“ aktivieren.
3. Shortcode `[avf_featured_post]` auf einer Seite oder in einem Elementor-Shortcode-Element
   einfügen, z. B. direkt unter dem Shortcode `[avf_upcoming_events]` auf der Startseite.

== Beitrag favorisieren ==

1. WordPress → Beiträge
2. gewünschten Beitrag öffnen
3. rechte Dokument-Seitenleiste öffnen
4. Option „An der Startseite halten“ aktivieren
5. Beitrag aktualisieren
6. bei einem Wechsel die Markierung beim vorherigen Beitrag entfernen

Falls mehrere Beiträge markiert sind, zeigt der Shortcode automatisch den neuesten (nach
Veröffentlichungsdatum). Die Elementor-Startseite muss dafür nicht bearbeitet werden.

== Auswahllogik ==

1. Veröffentlichte Beiträge (Post-Typ „post“), die als Sticky Post markiert sind, werden
   ermittelt.
2. Sind mehrere vorhanden, wird der neueste nach Veröffentlichungsdatum angezeigt.
3. Ist kein veröffentlichter Sticky Post vorhanden, wird – abhängig vom Attribut `fallback`
   – ersatzweise der neueste veröffentlichte Beitrag angezeigt.
4. Existiert kein veröffentlichter Beitrag, gibt der Shortcode einen leeren String zurück.
5. Entwürfe, private Beiträge, geplante Beiträge und Beiträge im Papierkorb erscheinen nie.

== Shortcode ==

`[avf_featured_post]`
`[avf_featured_post eyebrow="Rückblick" link_text="Bericht lesen" fallback="latest"]`

Attribute:

* `eyebrow` – Text oberhalb des Titels. Standard: „Rückblick“.
* `link_text` – Text des Detail-Links. Standard: „Bericht lesen“.
* `fallback` – `latest` (Standard) oder `none`. Bestimmt, ob bei fehlendem Sticky Post der
  neueste Beitrag angezeigt wird oder der Shortcode leer bleibt. Ungültige Werte werden auf
  `latest` zurückgesetzt.

Das Stylesheet wird nur geladen, wenn der Shortcode tatsächlich einen Beitrag rendert.

== Elementor-Einbindung ==

Der Shortcode benötigt kein Elementor. Er funktioniert im normalen WordPress-Inhalt, im
Elementor „Shortcode“-Widget, im Elementor-Editor sowie in der Elementor-Vorschau und im
öffentlichen Frontend. Es werden keine Elementor-spezifischen Klassen oder Hooks vorausgesetzt.

== Changelog ==

= 1.0.0 =
* Erste Version: Shortcode für favorisierten Beitrag basierend auf Sticky Posts mit Fallback
  auf den neuesten veröffentlichten Beitrag.
