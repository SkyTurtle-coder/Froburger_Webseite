=== AV Froburger Events Integration ===
Contributors: avfroburger
Tags: events, api, shortcode, elementor
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.1.2
License: GPLv2 or later

Bindet öffentliche Anlässe aus dem Django-CMS in WordPress und Elementor ein.

== Beschreibung ==

Dieses Plugin ruft serverseitig Anlässe von der öffentlichen, schreibgeschützten Django-API ab
und stellt sie über Shortcodes dar. Es benötigt kein Elementor, ist aber mit dem
Elementor-Shortcode-Widget nutzbar.

Seit Version 2.0.0 ("Phase 4") unterstützt das Plugin zwei API-Generationen nebeneinander:

* **Legacy** – ein einzelner voller Endpunkt (`/api/public/events/upcoming/`), nur kommende
  Anlässe. Ausschliesslich für den ursprünglichen Shortcode `[avf_upcoming_events]` (Startseite).
* **v1** – eine konfigurierbare Basis-URL (`/api/v1/public/`), kommende **und** vergangene
  Anlässe, Einzelansicht sowie ein Kalender-Feed. Für die neuen Shortcodes.

== Installation ==

1. Plugin-Ordner nach `wp-content/plugins/avf-events-integration/` hochladen (oder bereits
   vorhandenen Ordner verwenden).
2. Plugin im Backend unter „Plugins“ aktivieren.
3. Unter „Einstellungen → AV Froburger Events“ **API-Endpunkt** (Legacy) und/oder
   **API-Basis (v1)** konfigurieren.
4. Shortcodes auf einer Seite oder in Elementor-Shortcode-Elementen einfügen (siehe unten).

== Einstellungsseite ==

Unter „Einstellungen → AV Froburger Events“ (nur für Benutzer mit `manage_options`):

* **API-Endpunkt** – vollständige URL zum Legacy-Django-Endpunkt. Wird von `[avf_upcoming_events]`
  verwendet, ausserdem automatisch als Fallback für `[avf_events_upcoming]`, falls die v1-API
  nicht konfiguriert ist oder 404 antwortet (siehe „Legacy-Kompatibilität" unten).
* **API-Basis (v1)** – Basis-URL der neuen v1-API, endet auf `/api/v1/public/`. Wird für
  `[avf_events_upcoming]`, `[avf_events_past]`, `[avf_events_calendar_actions]` und
  `[avf_event_detail]` verwendet. Leer lassen, um ausschliesslich Legacy zu nutzen.
* **Cache-Dauer** – 60 bis 3600 Sekunden, Standard 300. Gilt für beide API-Generationen.
* **Zielseite für Anlassdetails** – relativer WordPress-Pfad, Standard `/anlaesse/`, Fallback für
  interne Eventlinks, falls Django kein `source_url` liefert.
* **Event-Cache leeren** – löscht alle vom Plugin erzeugten Zwischenspeicher, Legacy und v1
  gleichermassen (Nonce-geschützt).

== API-Vertrag ==

Ermittelt live gegen den lokalen Django-Entwicklungsserver (`http://127.0.0.1:8000`) während der
Entwicklung von Version 2.0.0. Jede Annahme unten wurde tatsächlich beobachtet, keine geraten.

=== Endpunkte ===

* `GET /api/public/events/upcoming/` (Legacy) – `{"count": N, "results": [...]}`, **keine**
  `next`/`previous`-Felder.
* `GET /api/v1/public/events/upcoming/` – `{"count": N, "next": url|null, "previous": url|null,
  "results": [...]}`.
* `GET /api/v1/public/events/past/` – identisches Antwortformat wie oben.
* `GET /api/v1/public/events/<slug>/` – ein einzelnes Event-Objekt, nicht in `results` verpackt.
* `GET /api/v1/public/events/calendar.ics` – `Content-Type: text/calendar`, iCalendar-Feed
  (mehrere `VEVENT`-Blöcke). Wird von WordPress **nur verlinkt, nie geparst oder generiert.**

=== Event-Felder (identisch bei Legacy und allen v1-Routen) ===

Pflichtfelder (fehlt eines, wird das einzelne Event stillschweigend übersprungen – siehe
„Bekannte Einschränkungen"):

`id` (Zahl), `title`, `slug`, `short_description`, `start_at`, `end_at`, `timezone_name`,
`location_name` – alle als nicht-leere Strings/Skalare erwartet.

Optionale Felder: `detail_path` (relativer Django-Pfad), `source_url` (vollständige, absolute
Django-URL zur Event-Seite – wird von den neuen Shortcodes für den „Details"-Link bevorzugt).

**Kein Kategorie-/Tag-Feld vorhanden.** Die "Mehrtägig"-Kennzeichnung in
`[avf_events_upcoming]` wird deshalb aus `start_at`/`end_at` abgeleitet (unterschiedliches
Kalenderdatum), nicht aus einem API-Feld.

=== Datumsformate ===

ISO-8601 mit Offset, z. B. `2026-08-01T15:15:00+02:00`. Wird über PHPs `DateTimeImmutable`
geparst; ein nicht parsbares Datum führt zum stillen Überspringen des einzelnen Events (nicht des
gesamten Requests).

=== Pagination ===

v1-Listen liefern `count`/`next`/`previous`. Die tatsächlichen Query-Parameter-Namen für Seiten
(`limit`/`offset` vs. `page`/`page_size`) konnten mit den während der Entwicklung verfügbaren
Testdaten (durchgehend nur je 1 Eintrag pro Liste) **nicht abschliessend verifiziert** werden.
Aus diesem Grund verwendet dieses Plugin bewusst `?limit=N` (wie der bereits bewährte
Legacy-Client) **plus** eine client-seitige Kappung auf `N` Ergebnisse – so bleibt die Anzeige
auch dann korrekt, wenn die API den `limit`-Parameter ignorieren oder anders benennen sollte.
"Mehr anzeigen" fordert bei jedem Klick die kumulierte Gesamtzahl (`initial + n × step`) neu an,
statt einem unbekannten Offset-Schema zu folgen.

=== Fehlerformate ===

Bei HTTP-Fehlern (z. B. 404) liefert Django `{"detail": "..."}`; dieses Plugin liest diesen Body
nie inhaltlich, sondern behandelt jeden Nicht-200-Status einheitlich als Fehler (siehe
„Cache & Fallback" unten).

== Shortcodes ==

=== [avf_upcoming_events] – unverändert (Legacy, Startseite) ===

`[avf_upcoming_events limit="3"]`

Unverändert seit Version 1.0.0. Verwendet ausschliesslich den Legacy-Endpunkt, eigene Cache-Keys,
eigenes Stylesheet (`upcoming-events.css`), eigene Klassen (`.avf-home-events`, `.avf-event-card`,
...). Nicht Teil der Phase-4-Arbeiten - bewusst unangetastet, damit die Startseite unverändert
bleibt.

=== [avf_events_upcoming] – neu, v1 ===

`[avf_events_upcoming initial="9" step="9"]`

* `initial` – initial angezeigte Anzahl. Standard 9, Bereich 1–60. Ungültige Werte → Standard.
* `step` – Anzahl weiterer Einträge pro „Mehr anzeigen"-Klick. Standard 9, Bereich 1–60.
* Nutzt `AVF_Events_API_Client::get_upcoming_events_v1()`: v1 zuerst, Legacy-Fallback nur bei
  fehlender v1-Konfiguration oder HTTP 404 (siehe „Legacy-Kompatibilität").
* Karte zeigt: Datum, optionales „Mehrtägig"-Badge, Titel, Kurzbeschreibung, Uhrzeit + Ort,
  „Details"-Link (bevorzugt `source_url`, sonst interner Anker wie bei `[avf_upcoming_events]`),
  „Zum Kalender hinzufügen"-Link (nur wenn v1-Basis konfiguriert ist).
* „Mehr anzeigen": echter Link (funktioniert ohne JavaScript per Seiten-Reload über den Query-
  Parameter `avf_upcoming_shown`), von `assets/js/events-lists.js` progressiv zu einem
  AJAX-Button ohne Reload aufgewertet. Fällt bei jedem AJAX-Fehler automatisch auf die normale
  Link-Navigation zurück.

=== [avf_events_past] – neu, v1 ===

`[avf_events_past initial="6" step="6"]`

Wie oben, aber:

* Kein Legacy-Fallback (es gibt keinen Legacy-Endpunkt für vergangene Anlässe).
* Zurückhaltendere Darstellung: kein Badge, kein Kalender-Link, kompaktere Zeilen
  (`.avf-events-past-item`).
* Neuster vergangener Anlass zuerst - Reihenfolge kommt unverändert aus der API-Antwort.
* Query-Parameter für den No-JS-Fallback: `avf_past_shown` (unabhängig von
  `avf_upcoming_shown`, beide Shortcodes funktionieren nebeneinander auf derselben Seite).

=== [avf_events_calendar_actions] – neu, v1 ===

`[avf_events_calendar_actions]`

Zwei Links auf **denselben** von Django gelieferten `calendar.ics`-Endpunkt:

* „Kalender abonnieren" – `webcal://`-Variante (reine Schema-Ersetzung, WordPress erzeugt keine
  eigenen Kalenderdaten).
* „ICS-Datei" – `https://`-Variante (Download-Prompt).

Ohne konfigurierte v1-Basis: leere Ausgabe für Besucher, dezenter Hinweis für Administratoren.

=== [avf_event_detail] – neu, v1, optional ===

`[avf_event_detail slug="froburger-erdbeerjagd"]`

Rendert ein einzelnes Event über den v1-Detailendpunkt. Nicht auf der Seite „Anlässe" platziert
(dort genügen die drei Shortcodes oben) - vorbereitet für eine mögliche künftige eigene
Event-Detailseite, ohne dass dafür weitere Backend-Arbeit nötig wäre.

== Elementor-Integration: Seite „Anlässe" ==

Die Seite benötigt nur diese drei Shortcodes, jeweils in einem eigenen Elementor-„Shortcode"-
Widget, von oben nach unten:

1. `[avf_events_upcoming]` – Hauptinhalt, direkt unter dem Seitenkopf.
2. `[avf_events_calendar_actions]` – darunter, als eigener Abschnitt.
3. `[avf_events_past]` – am Ende der Seite.

Die regionalen Stämme bleiben als statische Elementor-Inhalte bestehen und werden von diesem
Plugin nicht berührt.

Kein HTML-Widget, kein weiterer Shortcode, keine zusätzliche Elementor-Vorlage nötig. Alle drei
Shortcodes funktionieren identisch im Frontend und in der Elementor-Vorschau (kein
Elementor-spezifischer Code vorausgesetzt).

== Cache & Fallback ==

Ein einziger HTTP-Aufruf-Ort (`request_json()`) und ein einziger Cache-Mechanismus
(WordPress-Transients) für beide API-Generationen - siehe Klassendokumentation in
`class-avf-events-api-client.php`.

Zwei Cache-Stufen pro Ressource (Legacy-Upcoming, v1-Upcoming, v1-Past, v1-Detail - jeweils
eigene, nicht kollidierende Cache-Keys):

1. **Frischer Cache** – gemäss eingestellter Cache-Dauer (Standard 300 Sekunden).
2. **Fallback-Cache** – letzte erfolgreiche Antwort, maximal 24 Stunden gültig, wird bei jedem
   API-Fehler herangezogen, sofern vorhanden - **keine technische Warnung für Besucher.**

=== Übergangsstrategie v1 → Legacy (nur `[avf_events_upcoming]`) ===

1. v1 wird zuerst versucht (`avf_events_api_base` + `events/upcoming/`).
2. Ist keine v1-Basis konfiguriert **oder** antwortet der v1-Endpunkt mit HTTP 404, wird auf den
   Legacy-Endpunkt zurückgegriffen (dessen eigene Cache-/Fallback-Logik unverändert wiederverwendet
   wird).
3. **Kein** stiller Fallback bei anderen Fehlern (500, Timeout, ungültiges JSON, fehlendes
   `results`) - diese durchlaufen stattdessen den normalen Fallback-Cache-Pfad der v1-Anfrage
   selbst, niemals einen Wechsel der Datenquelle.
4. Jede Fallback-Entscheidung wird bei aktiviertem `WP_DEBUG` über `error_log()` protokolliert
   (z. B. „v1 'upcoming' endpoint returned 404; falling back to legacy..."), nie im Frontend
   angezeigt.

=== Verhalten ohne jeglichen Erfolg ===

* Echte Leerantwort (API erreichbar, 0 Ergebnisse) **und** API nicht erreichbar ohne
  Fallback-Cache zeigen für Besucher **denselben** dezenten Text (siehe unten) - keine
  Unterscheidung anhand von technischen Details.
* Administratoren sehen bei einem echten Fehlerfall zusätzlich einen kurzen, technikfreien
  Hinweis unterhalb des Leertexts.
* Bei aktiviertem `WP_DEBUG`: Protokollierung über `error_log()`, nie sensible Daten.

=== Leertexte ===

* Keine kommenden Anlässe: „Aktuell sind keine kommenden Veranstaltungen veröffentlicht."
* Keine vergangenen Anlässe: „Derzeit sind keine vergangenen Veranstaltungen verfügbar."

== Sicherheit ==

* Alle API-Textfelder werden ausschliesslich über `esc_html()` ausgegeben (nie `wp_kses` mit
  erlaubten Tags - Django liefert reinen Text, kein HTML).
* Alle URLs (Detail-Links, Kalender-Links) über `esc_url()`, inkl. `webcal://` (seit WordPress
  4.3.0 in `wp_allowed_protocols()` enthalten - verifiziert gegen den WordPress-Core dieser
  Installation).
* Der AJAX-Endpunkt (`admin-ajax.php?action=avf_events_more`) ist über `check_ajax_referer()`
  nonce-geschützt; ungültige Nonces erhalten HTTP 403. `type`/`shown`/`step` werden serverseitig
  validiert (whitelist für `type`, `absint()` + Bereichsprüfung für `shown`/`step`) - ungültige
  Werte liefern eine generische Fehlermeldung, nie technische Details.
* Live gegen XSS-artige API-Testwerte geprüft (`<script>`, `onerror=`, Anführungszeichen in
  Titel/Beschreibung/Ort) - alle korrekt als Text escaped, kein ausführbares Markup im Output.

== JavaScript ==

`assets/js/events-lists.js` - progressive Verbesserung des „Mehr anzeigen"-Links zu einem
AJAX-Button, ohne Abhängigkeiten. Funktioniert mit beliebig vielen `[avf_events_upcoming]`/
`[avf_events_past]`-Instanzen auf derselben Seite (jeder Button trägt seinen eigenen Zustand über
Data-Attribute, kein globaler Zustand). Bei jedem Fehler (Netzwerk, ungültige Antwort) fällt der
Button auf die normale Link-Navigation zurück.

== Bekannte Einschränkungen ==

* `location_name` und `short_description` sind über `AVF_Events_API_Client::normalize_event()`
  weiterhin hart erforderlich (unverändert seit Version 1.0.0, für Legacy und v1 gleichermassen) -
  ein Event ohne Ort oder ohne Kurzbeschreibung wird komplett übersprungen, statt mit einer Lücke
  dargestellt zu werden. Bewusst nicht gelockert, um das gemeinsame Validierungsverhalten von
  Legacy und v1 nicht zu verändern (siehe „Startseitenintegration darf nicht beschädigt werden").
* Der No-JS-Fallback-Query-Parameter (`avf_upcoming_shown`/`avf_past_shown`) unterstützt zuverlässig
  nur je eine Instanz von `[avf_events_upcoming]` bzw. `[avf_events_past]` pro Seite. Der
  JS-gestützte AJAX-Button funktioniert dagegen auch mit mehreren gleichartigen Instanzen (eigener
  Zustand pro Button).
* Die v1-Pagination-Parameter (`limit`/`offset` vs. `page`/`page_size`) sind mit den verfügbaren
  Testdaten nicht abschliessend verifizierbar (siehe „API-Vertrag" oben) - die aktuelle
  „Gesamtzahl neu anfordern"-Strategie ist robust gegenüber beiden Schemata, aber ungetestet mit
  wirklich grossen Datenmengen.
* Kein PHPUnit-/WP-Test-Framework in diesem Projekt vorhanden; die Verifikation in Version 2.0.0
  erfolgte live gegen den lokalen Django-Entwicklungsserver sowie eine temporäre Fake-API
  (verschiedene Fehlerszenarien) - siehe Commit-/Change-Historie für Details.

== Changelog ==

= 2.1.2 =
* Rueckblick-Liste (`[avf_events_past]`) visuell verfeinert: breite,
  zurueckhaltende Zeilenkarten mit hellem Hintergrund, feinem Rahmen,
  dezentem Schatten, staerkerem Innenabstand und robusterem responsivem
  Umbruch fuer Desktop, Tablet und Mobil.
* Vergangene Events zeigen nun - sofern vorhanden - Uhrzeit und Ort gemeinsam
  im Meta-Bereich, ohne die kommende Eventliste oder den Legacy-Shortcode zu
  veraendern.

= 2.0.0 =
* Neue v1-API-Unterstützung: konfigurierbare Basis-URL (`avf_events_api_base`), zusätzlich zum
  bestehenden Legacy-Endpunkt.
* Neue Shortcodes: `[avf_events_upcoming]`, `[avf_events_past]`, `[avf_events_calendar_actions]`,
  `[avf_event_detail]`.
* Kontrollierte Übergangsstrategie v1 → Legacy für kommende Anlässe (nur bei fehlender
  v1-Konfiguration oder HTTP 404, nie bei anderen Fehlern).
* "Mehr anzeigen" mit progressiver Verbesserung (echter Link ohne JS, AJAX ohne Reload mit JS).
* `[avf_upcoming_events]` (Startseite) unverändert - eigener Code-Pfad, eigenes Stylesheet, keine
  gemeinsam genutzten Klassen mit den neuen Komponenten.

= 1.0.0 =
* Erste Version: Einstellungsseite, API-Client mit Cache/Fallback, Shortcode für kommende
  Anlässe.
