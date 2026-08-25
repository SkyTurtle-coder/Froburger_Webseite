# AV Froburger WordPress Deployment Runbook

## Zweck

Diese Anleitung definiert das verbindliche Standardverfahren fuer Deployments am oeffentlichen WordPress-Projekt der AV Froburger.

Ziel ist ein kleines, kontrolliertes Deployment:

`Repository als Source of Truth -> betroffene Dateien identifizieren -> nur diese Dateien deployen -> Live verifizieren -> gezielt rollbacken, falls noetig`

> [!WARNING]
> Stand 25.08.2026: `test.avfroburger.ch` ist derzeit **NICHT** als isolierte
> Staging-Umgebung freigegeben. Test und Produktion verwenden beide die
> WordPress-Datenbank `avfrobur_newton` mit Tabellenpraefix `wp_`.
> Im Test-Webroot keine schreibenden WordPress-/WP-CLI-Operationen ausfuehren.

> [!WARNING]
> Bei einem normalen Code-Deployment niemals pauschal ueberschreiben:
> - `wp-content/uploads`
> - `/home/avfrobur/www/member-media-private`
> - die WordPress-Datenbank
> - `home` / `siteurl`
> - komplette WordPress-Installationen

## Architektur und Umgebungen

| Umgebung | Oeffentliche URL | Webroot | Datenbank / Prefix | Status |
| --- | --- | --- | --- | --- |
| Produktion | `https://www.avfroburger.ch` | `/home/avfrobur/www/gamma.avfroburger.ch` | `avfrobur_newton` / `wp_` | verbindlicher Live-Stand |
| Test | `https://test.avfroburger.ch` | `/home/avfrobur/www/newton/test` | `avfrobur_newton` / `wp_` | nicht als Staging freigegeben |

### Wichtige Namensregel

Der Produktions-Webroot heisst bei Hostpoint historisch:

`/home/avfrobur/www/gamma.avfroburger.ch`

Dieser Ordnername ist **nur** der Hostpoint-Webrootname. Die oeffentliche Live-Domain bleibt:

`https://www.avfroburger.ch`

Der Ordner darf nicht wegen seines Namens umbenannt werden.

### Verifizierter Ist-Zustand: Test und Produktion teilen Daten

Die Serverpruefung vom 25.08.2026 hat fuer beide Webroots identische Werte
festgestellt:

```text
www.avfroburger.ch
|- Webroot: /home/avfrobur/www/gamma.avfroburger.ch
`- DB:      avfrobur_newton, Prefix wp_

test.avfroburger.ch
|- Webroot: /home/avfrobur/www/newton/test
`- DB:      avfrobur_newton, Prefix wp_
```

Beide Installationen verwenden damit dieselben WordPress-Tabellen. Die im
Test-Webroot konfigurierten Werte fuer `home` und `siteurl` zeigen ebenfalls
auf `https://www.avfroburger.ch`.

### Dateien und Datenbank sind getrennte Ebenen

Unterschiedliche Verzeichnisse bedeuten **nicht automatisch** unterschiedliche WordPress-Datenbanken.

Die Pfade

```text
/home/avfrobur/www/gamma.avfroburger.ch
/home/avfrobur/www/newton/test
```

sind getrennte Dateisystempfade, koennen aber trotzdem dieselbe Datenbank und
dieselben Tabellen verwenden. Genau dies ist derzeit der Fall.

Getrennt zu betrachten sind insbesondere:

- Dateisystem unter `wp-content/`, Plugins, MU-Plugins, Themes
- WordPress-Datenbank mit `wp_posts`, `wp_postmeta`, `wp_options`, Menues, Elementor-Daten und Plugin-Einstellungen
- persistente Produktionsdaten wie Uploads, Benutzer, Event-Anmeldungen und Formulardaten

### Test-Webroot bis zur Isolation gesperrt

Bis eine separate Staging-Datenbank eingerichtet und verifiziert ist, sind im
Test-Webroot ausschliesslich eindeutig read-only Pruefungen erlaubt.

Insbesondere verboten sind:

```text
wp option update home ...
wp option update siteurl ...
wp search-replace ...
wp cache flush
wp transient delete --all
wp elementor flush-css
wp eval ...
WordPress-/Elementor-Aenderungen im Browser
Plugin-Migrationen oder andere DB-Schreiboperationen
```

Auch ein Wechsel von `https://www.avfroburger.ch` auf
`https://test.avfroburger.ch` per `wp search-replace` wuerde gegenwaertig die
Produktionsdaten aendern und ist daher verboten.

Sicherer Diagnoseblock fuer kuenftige Read-only-Isolationspruefungen:

```bash
for ROOT in \
  /home/avfrobur/www/gamma.avfroburger.ch \
  /home/avfrobur/www/newton/test
do
    echo
    echo "ROOT: $ROOT"

    cd "$ROOT" || exit 1

    printf "WordPress home:     "
    wp option get home

    printf "WordPress siteurl:  "
    wp option get siteurl

    printf "Database:           "
    wp db query 'SELECT DATABASE();' --skip-column-names

    printf "Table prefix:       "
    wp db prefix

    printf "WordPress version:  "
    wp core version
done
```

Der Block liest nur Konfiguration und Metadaten und eignet sich zur
Verifikation, nicht zur Reparatur der Umgebungen.

## Grundregel fuer Deployments

> [!IMPORTANT]
> Auf Live wird nur das uebertragen, was fuer die konkrete Aenderung notwendig ist.

Wenn nur ein Plugin, eine PHP-Datei, eine CSS-Datei oder ein klar abgegrenztes Verzeichnis geaendert wurde, wird nur genau dieser Scope deployed.

Standardreihenfolge:

1. Aenderung entwickeln.
2. Aenderung lokal pruefen.
3. Betroffene Dateien exakt identifizieren.
4. Rollback definieren.
5. Live-Datei oder Live-Verzeichnis sichern.
6. Nur die betroffenen Dateien uebertragen.
7. Syntax und Integritaet pruefen.
8. Cache nur falls erforderlich leeren.
9. Oeffentliche Live-Seite pruefen.
10. Bei Fehlern gezielt rollbacken.

## Deployment-Arten

### A. Code-Deployment

Beispiele:

- PHP
- CSS
- JavaScript
- Plugin-Code
- MU-Plugins

Das ist der bevorzugte und haeufigste Deployment-Typ.

### B. WordPress-/Elementor-Inhaltsaenderungen

Beispiele:

- Seiten
- Elementor-Templates
- Beitraege
- Menues
- globale Elementor-Einstellungen

Diese Aenderungen liegen typischerweise in der Datenbank und werden **nicht** durch das Kopieren eines Plugin-Verzeichnisses uebertragen.

Solange Test und Produktion dieselbe Datenbank verwenden, duerfen solche
Aenderungen nicht ueber den Test-Webroot ausgefuehrt werden. Sie muessen
entweder:

- bewusst direkt in Produktion gemacht werden, oder
- als eigene Datenbank-Migration geplant und abgesichert werden

### C. Medien

`wp-content/uploads`

Produktive Medien duerfen nie pauschal durch eine aeltere Testversion ersetzt werden.

### D. Datenbankaenderungen / Migrationen

Nur dann ausfuehren, wenn sie ausdruecklich Teil des Deployments sind.

Vorher immer Datenbankbackup erstellen.

`wp search-replace` ist **keine** normale Deployment-Routine.

## Persistente Produktionsdaten

Folgende Daten sind bei normalen Code-Deployments als persistent und unantastbar zu behandeln:

- `wp-content/uploads`
- private Mitgliedermedien
- Benutzer
- Event-Anmeldungen
- WordPress-Datenbank
- Formulardaten
- produktive Plugin-Einstellungen

### Private Medien ausserhalb des Webroots

Produktiver privater Pfad:

`/home/avfrobur/www/member-media-private`

Die Privacy-Implementierung verwendet im Code:

`dirname(ABSPATH) . '/member-media-private'`

Daher gilt:

- diesen Ordner nie loeschen, verschieben oder ueberschreiben
- keine Vollsynchronisation ueber diesen Pfad laufen lassen
- keine Testdaten in diesen Ordner kopieren

## Standardverfahren fuer ein Plugin-Deployment

Beispiel-Plugin:

`wp-content/plugins/avf-events-integration`

Produktionspfad:

`/home/avfrobur/www/gamma.avfroburger.ch/wp-content/plugins/avf-events-integration`

### 1. In das produktive WordPress wechseln

```bash
cd ~/www/gamma.avfroburger.ch
```

### 2. Vorheriges Backup anlegen

Bei groesseren Plugin-Aenderungen:

```bash
mkdir -p ~/backups

cp -Rp \
wp-content/plugins/avf-events-integration \
~/backups/avf-events-integration-before-YYYYMMDD-HHMM
```

Bei kleinen Einzeldatei-Aenderungen ist ein dateispezifisches Backup oft sinnvoller:

```bash
cp \
wp-content/plugins/avf-events-integration/includes/example.php \
wp-content/plugins/avf-events-integration/includes/example.php.bak-YYYYMMDD-HHMM
```

### 3. Nur die betroffenen Dateien deployen

Beispiele fuer typische kleine Deployments:

- `wp-content/plugins/avf-events-integration/assets/css/events-lists.css`
- `wp-content/plugins/avf-events-integration/includes/class-avf-event-detail-shortcode.php`
- `wp-content/mu-plugins/avf-member-privacy.php`

### 4. PHP-Syntax pruefen

Ein Deployment ist nicht abgeschlossen, solange ein PHP-Syntaxfehler besteht.

Einzeldatei:

```bash
php -l wp-content/plugins/avf-events-integration/includes/class-avf-event-detail-shortcode.php
```

Mehrere geaenderte PHP-Dateien:

```bash
php -l wp-content/plugins/avf-events-integration/includes/class-avf-event-detail-router.php
php -l wp-content/plugins/avf-events-integration/includes/class-avf-event-detail-shortcode.php
php -l wp-content/mu-plugins/avf-member-privacy.php
```

### 5. WP-CLI-Grundpruefung

```bash
wp core version
wp option get home
wp option get siteurl
```

Erwartete produktive URL:

```text
https://www.avfroburger.ch
```

> [!WARNING]
> Keine schreibenden WP-CLI-Kommandos gegen `~/www/newton/test` ausfuehren.
> Der Servercheck vom 25.08.2026 hat die gemeinsame Datenbank
> `avfrobur_newton` mit Praefix `wp_` bestaetigt.

### 6. Cache nur bei Bedarf leeren

Wenn noetig:

```bash
wp cache flush
```

Wichtig:

- `wp cache flush` ist **nicht** automatisch bei jeder kleinen Dateiaenderung noetig
- Browser-Cache bei der Verifikation mitdenken
- fuer Tests ggf. Hard Reload, Inkognito-Fenster oder DevTools verwenden

## Elementor-generierte Dateien

### Unterschied zwischen Quellcode und generierten Dateien

Normaler Quellcode:

`wp-content/plugins/avf-events-integration/assets/css/events-lists.css`

Generierte Elementor-Dateien:

`wp-content/uploads/elementor/css/...`

Generierte Elementor-Dateien duerfen nicht wie normal versionierter Quellcode behandelt werden.
Wenn moeglich, werden sie aus der zugrundeliegenden Elementor-/WordPress-Konfiguration gezielt neu erzeugt statt manuell ersetzt.

### `wp elementor flush-css` nicht routinemaessig verwenden

Beim Go-live wurde beobachtet, dass nach einer Elementor-CSS-Regeneration Dateien wie

- `post-355.css`
- `post-26.css`

im HTML referenziert waren, auf dem Dateisystem aber fehlten. Das fuehrte zu `404` und fehlerhaftem Layout.

Deshalb gilt:

- `wp elementor flush-css` **nicht** nach jedem Deployment ausfuehren
- nur bei konkretem Grund verwenden
- fehlende Einzeldateien bevorzugt gezielt regenerieren

Gezielte Regeneration einzelner Post-CSS-Dateien:

```bash
wp eval '
$ids = [355, 26];

foreach ($ids as $id) {
    $css = new \Elementor\Core\Files\CSS\Post($id);
    $css->update();
    echo "CSS fuer {$id} erzeugt\n";
}
'
```

Danach pruefen:

```bash
ls -lh \
wp-content/uploads/elementor/css/post-355.css \
wp-content/uploads/elementor/css/post-26.css
```

Und HTTP pruefen:

```bash
curl -sI "https://www.avfroburger.ch/wp-content/uploads/elementor/css/post-355.css"
```

Erwartet:

```text
HTTP/2 200
```

Nicht blind alle Elementor-CSS-Dateien regenerieren, wenn nur eine einzelne Datei betroffen ist.

## Domainwechsel / Staging -> Produktion

Ein Domainwechsel ist **keine** normale Deployment-Operation.

Beim frueheren Wechsel von

`https://test.avfroburger.ch`

nach

`https://www.avfroburger.ch`

wurde beobachtet, dass Elementor lokal generierte Google-Font-CSS-Dateien unter

`wp-content/uploads/elementor/google-fonts/css/`

absolute URLs behalten koennen.

Vor einem Domainwechsel deshalb pruefen:

```bash
grep -RIn "test.avfroburger.ch" \
wp-content/uploads/elementor/google-fonts
```

Im normalen taeglichen Deployment ist hier **kein** Domain-Replacement noetig.

### `wp search-replace` nur fuer echte Migrationsfaelle

Vorher immer:

- Backup
- Dry Run
- Scope pruefen

Beispiel:

```bash
wp search-replace \
'https://old.example' \
'https://new.example' \
--all-tables-with-prefix \
--skip-columns=guid \
--dry-run
```

Erst danach ohne `--dry-run`.

Dieser Ablauf gilt nur innerhalb einer bereits separaten Migrations- oder
Staging-Datenbank. Im aktuellen Test-Webroot ist auch ein `--dry-run` kein
Ersatz fuer eine isolierte Umgebung und ein schreibendes `wp search-replace`
ist ausnahmslos verboten.

## Datenbank-Backups und Migrationen

Vor folgenden Aktionen ist ein Datenbankbackup zwingend:

- Datenbankmigrationen
- Elementor-Massenaenderungen
- `wp search-replace`
- Plugin-Migrationen mit Schemaaenderungen
- grosse WordPress-Konfigurationsaenderungen

Backup:

```bash
mkdir -p ~/backups

cd ~/www/gamma.avfroburger.ch

wp db export \
~/backups/wordpress-before-YYYYMMDD-HHMM.sql
```

Datei pruefen:

```bash
ls -lh ~/backups/wordpress-before-YYYYMMDD-HHMM.sql
```

> [!WARNING]
> Ein altes DB-Backup darf nicht leichtfertig ueber eine inzwischen aktive Produktionsdatenbank importiert werden.
> Sonst koennen neue Event-Anmeldungen, Benutzer-Aenderungen, Beitraege, Formulareingaben und Einstellungen verloren gehen.

## Verifikation nach dem Deployment

### Technische Schnellpruefungen

```bash
wp option get home
wp option get siteurl
curl -sI https://www.avfroburger.ch/
curl -sI https://avfroburger.ch/
```

Erwartungen:

- `https://www.avfroburger.ch/` liefert `HTTP 200`
- `https://avfroburger.ch/` leitet auf `https://www.avfroburger.ch/` weiter

### Dateipruefungen

- Dateien vorhanden?
- Dateirechte korrekt?
- PHP-Syntax korrekt?
- keine versehentlich oeffentlich erreichbaren `.bak`-Dateien?

### Smoke Test

Mindestens pruefen:

- Startseite
- `Aktuelles`
- `Veranstaltungen`
- eine Anlassdetailseite
- eine Beitragsdetailseite
- Mitgliederbereich, soweit oeffentlich pruefbar
- Desktop
- Mobile
- Browser-Konsole
- keine `404` fuer CSS/JS

## Event-Detail-Seiten und Zirkel-Hintergrund

Oeffentliche Anlassdetailseiten werden durch das Plugin

`avf-events-integration`

geroutet.

Oeffentliche URL-Struktur:

`/anlaesse/<slug>/`

Der Router verwendet die Query-Variable:

`avf_event_slug`

Fuer das Layout ist wichtig:

- Anlassdetailseiten sollen zentral den Zirkel-Hintergrund ueber das gemeinsame System verwenden
- der allgemeine Zirkel-Hintergrund wird vom Plugin `avf-zirkel-decor` ueber `.avf-has-zirkel-bg` gesteuert
- der Event-Detail-Router setzt `.avf-has-zirkel-bg` nur fuer echte oeffentliche Anlassdetail-Anfragen auf die konfigurierte Detailseite
- event-spezifische Zirkel-Sonderpositionierung ist entfernt; Anlassdetailseiten verwenden ausschliesslich das zentrale `avf-zirkel-decor`-Verhalten
- keine eigenen event-spezifischen Zirkel-Sonderregeln neu einfuehren, wenn nicht ausdruecklich gewuenscht

## Rollback

Vor jedem Deployment muss klar beantwortet werden:

`Wie stelle ich den vorherigen Zustand wieder her?`

Einzeldatei-Rollback:

```bash
cp \
path/file.php.bak-YYYYMMDD-HHMM \
path/file.php
```

Verzeichnis-Rollback:

- aktuelles Verzeichnis sichern oder entfernen
- Backup zurueckkopieren
- falls noetig anschliessend `wp cache flush`

Bei Datenbank-Rollback nur mit grosser Vorsicht arbeiten, weil zwischen Backup und Restore neue produktive Daten entstanden sein koennen.

## Was bei normalem Code-Deployment ausdruecklich verboten ist

```text
- komplette WordPress-Installation kopieren
- komplette Datenbank importieren
- wp-content/uploads ersetzen
- private Mitgliedermedien ersetzen
- globales search-replace durchfuehren
- home/siteurl veraendern
- WordPress-Webroot umbenennen
- wp elementor flush-css routinemaessig ausfuehren
- Testdaten auf Live synchronisieren
```

## Git- und Drift-Regeln

Direkte Produktionsaenderungen, die nicht im Repository landen, erzeugen Code-Drift.

Folge:

- ein spaeteres Deployment kann einen funktionierenden Live-Fix wieder ueberschreiben

Deshalb gilt:

- jede produktive Codeaenderung anschliessend im Repository nachvollziehen
- vor jedem Deployment die zu deployende Version bewusst pruefen

Vorher:

```bash
git status
git diff
```

## Bestehende Deployment-Helfer im Repository

Im Repository existieren bereits Hostpoint-Test-Helfer:

- `intern/tools/wordpress/deploy-test.ps1`
- `intern/tools/wordpress/deploy-test-file.ps1`
- `intern/tools/wordpress/DEPLOY-TEST.md`
- `intern/tools/wordpress/TEST-SYSTEM-STATUS.md`

Einordnung:

- diese Dateien betreffen primaer `test.avfroburger.ch`
- `deploy-test.ps1` erstellt einen breiten Spiegel und verwendet serverseitig `rsync --delete`
- beide Test-Skripte fuehren schreibende Nachlaufaktionen aus: `wp cache flush`, `wp transient delete --all` und gegebenenfalls `wp elementor flush-css`
- `deploy-test-file.ps1` ist zwar dateigenau, fuehrt aber dieselben schreibenden WordPress-Nachlaufaktionen aus

Diese Helfer sind bis zur bestaetigten Staging-Isolation **nicht** fuer
Staging-Tests ausfuehrbar. Insbesondere duerfen weder die breite
Synchronisation noch die WordPress-Nachlaufaktionen gegen den aktuellen
Test-Webroot gestartet werden.

Die Skripte werden nicht automatisch umgebaut. Nach der Staging-Isolation
muessen ihr Vollsync- und `rsync --delete`-Risiko separat bewertet werden.
Fuer Produktion gilt dieses Runbook mit minimalen, gezielten Deployments.

## Offene Deployment-Risiken

### 1. Test-Isolation ist aktuell blockiert

Der aktuelle Servercheck vom 25.08.2026 hat fuer Test und Produktion dieselbe
Datenbank `avfrobur_newton` und denselben Tabellenpraefix `wp_` bestaetigt.
Der Server-Ist-Zustand hat Vorrang vor aelteren Notizen.

`intern/tools/wordpress/TEST-SYSTEM-STATUS.md` enthaelt historische Aussagen
vom 11.08.2026, nach denen `test` von `beta` getrennt worden sei und `home` /
`siteurl` auf `test.avfroburger.ch` zeigten. Diese Aussagen sind fuer die
heute verifizierte Beziehung zwischen Test und Produktion veraltet und nicht
mehr als Nachweis einer Staging-Isolation zu verwenden.

### 2. Anlassdetail-Zirkel-Fix ist im Repository synchronisiert

Der am 25. August 2026 direkt auf Live vorgenommene Fix ist auch im Repository enthalten:

- der Event-Detail-Router setzt `avf-has-zirkel-bg` fuer echte oeffentliche Anlassdetailseiten
- event-spezifische Zirkel-Sonderpositionierung in `events-lists.css` wurde entfernt
- Anlassdetailseiten verwenden damit ausschliesslich das zentrale Verhalten von `avf-zirkel-decor`

Der Fix muss bei kuenftigen Aenderungen im Event-Detail-Router und in `events-lists.css` erhalten bleiben.

### 3. Das WordPress-Repo ist aktuell nicht sauber

Die Git-Arbeitskopie unter `public/` enthaelt bereits lokale Aenderungen und neue Dateien.
Vor jedem Deployment muss deshalb klar sein, welche Version tatsaechlich uebertragen wird.

## Geplanter Zielzustand: isoliertes Staging

Dieser Abschnitt ist ein Plan und beschreibt keine bereits ausgefuehrten
Arbeiten. Der Name einer zukuenftigen Staging-Datenbank ist bewusst nicht
festgelegt; `avfrobur_staging` waere nur ein Beispielname.

### `test.avfroburger.ch`

- eigener Webroot
- eigene WordPress-Datenbank
- eigene Testdaten
- keine produktiven Formulare oder Anmeldungen
- keine produktiven privaten Medien
- keine schreibende Kopplung an Produktionsdaten

### `www.avfroburger.ch`

- eigener Webroot
- eigene Produktionsdatenbank
- produktive Medien
- produktive Benutzer
- produktive Anmeldungen

Bevor Staging wieder freigegeben wird, ist folgende Reihenfolge geplant:

1. Separate MySQL-Datenbank fuer Staging anlegen.
2. Produktionsdatenbank einmalig in diese neue Datenbank kopieren.
3. Ausschliesslich `wp-config.php` des Test-Webroots auf die neue Datenbank umstellen.
4. In der neuen Staging-Datenbank `home` auf `https://test.avfroburger.ch` setzen.
5. In der neuen Staging-Datenbank `siteurl` auf `https://test.avfroburger.ch` setzen.
6. Kontrolliertes Domain-Search/Replace ausschliesslich in der neuen Staging-Datenbank ausfuehren.
7. Elementor- und Font-CSS pruefen.
8. Suchmaschinenindexierung deaktivieren.
9. E-Mail-Versand absichern.
10. Formulare und Event-Anmeldungen absichern.
11. Private Mitgliedermedien pruefen und einen Isolationstest durchfuehren.
12. Staging erst nach erfolgreicher Verifikation freigeben.

Beim Aufbau darf Staging eine initiale Kopie erhalten, aber keine automatische
Ruecksynchronisation auf Produktion. Nie unkontrolliert bidirektional
synchronisieren: Produktionsdatenbank, Event-Anmeldungen, Benutzer,
Formulardaten, `wp-content/uploads` und
`/home/avfrobur/www/member-media-private`.

Staging ist erst READY, wenn mindestens Folgendes bestaetigt ist:

- Test und Produktion verwenden unterschiedliche Datenbanken oder vollstaendig getrennte Tabellen.
- Test-`home` und Test-`siteurl` zeigen auf `https://test.avfroburger.ch`.
- Produktion zeigt weiter auf `https://www.avfroburger.ch`.
- Aenderungen auf Test beeinflussen Produktion nicht.
- E-Mail-Versand, Formulare und Event-Anmeldungen sind abgesichert.
- Suchmaschinenindexierung ist deaktiviert.
- Private Medien werden nicht produktiv veraendert und Testdaten koennen nicht zurueck nach Produktion geschrieben werden.

## Temporarer Workflow bis zur Staging-Isolation

```text
Aenderung
   ->
lokal testen
   ->
git diff pruefen
   ->
kleines gezieltes Code-Deployment auf Live
   ->
Live Smoke Test
```

Nicht verwenden:

```text
lokal -> test.avfroburger.ch -> Live
```

Nach verifizierter Isolation gilt wieder:

```text
lokal -> Staging -> Smoke Test -> gezieltes Code-Deployment auf Live -> Live Smoke Test
```

Auch dann bleiben Vollkopien der WordPress-Installation sowie automatische
DB-, Upload- und private Medien-Synchronisationen verboten. Keine
Serverdateien direkt aendern, wenn dieselbe Aenderung anschliessend nicht auch
im Repository nachvollziehbar ist.

## Deployment Checklist

### Vorher

- [ ] Aenderung lokal getestet
- [ ] `git diff` geprueft
- [ ] betroffene Live-Dateien identifiziert
- [ ] Rollback definiert
- [ ] Backup erstellt
- [ ] bei DB-Aenderung DB-Backup erstellt
- [ ] keine persistenten Produktionsdaten werden ueberschrieben
- [ ] bei Arbeit auf `test` nur read-only Pruefungen; keine WP-/WP-CLI-Schreiboperation
- [ ] Staging-Deploy nur nach separater DB und erfolgreichem Isolationstest freigeben

### Deployment

- [ ] nur erforderliche Dateien uebertragen
- [ ] PHP-Syntax geprueft
- [ ] Integritaetspruefungen ausgefuehrt
- [ ] Cache nur falls noetig geleert
- [ ] keine unnoetige Elementor-CSS-Regeneration ausgefuehrt

### Nachher

- [ ] Startseite geprueft
- [ ] betroffene Seite geprueft
- [ ] Desktop geprueft
- [ ] Mobile geprueft
- [ ] Browser-Konsole geprueft
- [ ] keine `404` fuer CSS/JS
- [ ] Live-Fix im Git-Repository vorhanden
- [ ] Rollback-Backup bis nach erfolgreicher Pruefung behalten
