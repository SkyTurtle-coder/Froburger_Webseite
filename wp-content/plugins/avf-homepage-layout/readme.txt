=== AV Froburger Homepage Layout ===
Contributors: avfroburger
Tags: elementor, homepage, wp-cli, rebuild
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Reproduzierbarer, versionierter Neuaufbau der Startseiten-Elementor-Struktur inklusive
Backup/Restore.

== Zweck ==

Die Startseite (Post-ID 20) war strukturell beschädigt: verschachtelte, teils fälschlich
mit der Hero-Klasse versehene Container erzeugten grossflächige schwarze Leerflächen, von
drei Wertekarten war nur noch eine vorhanden, und das Zitatband fehlte komplett. Dieses
Plugin baut den Bereich unterhalb des bestehenden, funktionierenden Heros kontrolliert,
deterministisch und **idempotent** neu auf – über Elementors eigene Dokument-API, niemals
per Rohzugriff auf die Datenbank.

Das Plugin verändert die Startseite **nie automatisch**. Es passiert ausschliesslich, wenn
explizit einer der folgenden WP-CLI-Befehle ausgeführt wird.

== WP-CLI-Befehle ==

`wp avf homepage analyze`
Zeigt die aktuelle Elementor-Struktur der Startseite (Top-Level-Elemente, aufgelöste
Klassen, Kinderanzahl, ob ein Element leer ist) als JSON. Verändert nichts.

`wp avf homepage rebuild --dry-run`
Prüft die Frontpage-ID, liest die aktuelle Struktur und zeigt genau an, was ein echter
Lauf tun würde (welches Element als Hero erkannt und behalten wird, welche Top-Level-
Elemente entfernt/ersetzt werden, welche Global Classes angelegt werden). **Verändert
nichts.**

`wp avf homepage rebuild`
Führt den vollständigen, echten Neuaufbau aus:

1. Prüft `page_on_front === 20`.
2. Erstellt ein vollständiges, zeitgestempeltes Backup unter
   `wp-content/uploads/avf-backups/homepage/` sowie eine Entwurfsseiten-Kopie.
3. Ermittelt das bestehende, funktionierende Hero-Element eindeutig über seine stabile
   globale Klassen-ID und lässt es **unverändert**.
4. Legt die für die neue Struktur benötigten Elementor Global Classes an beziehungsweise
   aktualisiert sie (idempotent).
5. Baut den kompletten Bereich „Hauptinhalt" (Events, Featured Post, Werte, Zitatband)
   neu auf, mit deterministischen Element-IDs.
6. Validiert die neue Struktur als JSON.
7. Speichert über `\Elementor\Plugin::$instance->documents->get(20)->save(...)` (die von
   Elementor selbst unterstützte Speicherfunktion).
8. Regeneriert Elementors generiertes CSS über Elementors eigene Cache-Mechanismen.

`wp avf homepage restore --backup=<Zeitstempel-oder-Datei>`
Stellt `_elementor_data` aus einer zuvor geschriebenen Sicherung wieder her (z. B.
`--backup=2026-07-21_191838`) und regeneriert danach ebenfalls das Elementor-CSS.

== Idempotenz ==

Ein erneuter Lauf von `wp avf homepage rebuild` erzeugt **keine** zusätzlichen oder
doppelten Container:

* Alle neu erzeugten Element-IDs sind deterministisch aus einem festen Namensschema
  abgeleitet (derselbe semantische Name ergibt immer dieselbe ID).
* Der komplette Bereich „Hauptinhalt" wird bei jedem Lauf **vollständig ersetzt** (nicht
  ergänzt) – die Top-Level-Struktur ist nach jedem Lauf exakt „Hero + Hauptinhalt", nie
  mehr.
* Global Classes werden anhand ihres Namens wiederverwendet statt dupliziert.

== CSS-Architektur ==

Alle startseitenspezifischen Layoutregeln liegen in
`assets/css/homepage.css`, geladen ausschliesslich auf der Startseite, nach Elementor-,
Theme- und `avf-site-styles`-Stylesheets. Jede Regel ist mit `body.home` und/oder den
neuen `avf-home-*`-Klassen begrenzt. Der Header/Hero-Randfix bleibt bewusst allein im
Plugin `avf-site-styles`, um denselben Fix nicht an mehreren Stellen zu pflegen.

== Zirkel-Hintergrund ==

Der Container `.avf-home-main` erhält automatisch (über das Plugin `avf-zirkel-decor`)
eine einzige, seitenweite Dekorationsebene (`.avf-page-zirkel-decor-layer`) mit 5–9
Zirkeln über die volle Containerhöhe. Dieselbe Komponente lässt sich auf jeder anderen
Seite über die Elementor-Klasse `avf-has-zirkel-bg` aktivieren – ohne Änderungen an
diesem Plugin. Details dazu stehen im readme.txt von `avf-zirkel-decor`.

== Rollback ==

Jeder echte Rebuild-Lauf schreibt vorher automatisch ein vollständiges Backup. Zum
Zurücksetzen:

`wp avf homepage restore --backup=<Zeitstempel>`

Der Zeitstempel steht in der Ausgabe des Rebuild-Laufs sowie in den Dateinamen unter
`wp-content/uploads/avf-backups/homepage/` (Format `YYYY-MM-DD_HHMMSS`). Alternativ kann
ein vollständiger Pfad zu einer `*-elementor-data-before-*.json`-Datei übergeben werden.
Nach dem Restore automatisch: Elementor-CSS-Regeneration. Zusätzlich existiert für jeden
Lauf eine Entwurfsseiten-Kopie „Startseite – Backup vor Neuaufbau – …" zum manuellen
Vergleich im Elementor-Editor.

== Nicht Teil dieses Plugins ==

Kein automatischer Lauf bei Plugin-Aktivierung, keine Änderung an Header/Footer, keine
Änderung an anderen Seiten, keine Änderung an generierten Elementor-CSS-Dateien (nur über
Elementors eigene Regenerations-Mechanismen), keine Rohzugriffe auf die Datenbank.

== Changelog ==

= 1.0.0 =
* Erste Version: Neuaufbau der Startseite über WP-CLI, Backup/Restore, homepage.css.
