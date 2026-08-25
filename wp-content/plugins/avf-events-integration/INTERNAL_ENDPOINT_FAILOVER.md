# Internal Endpoint Failover

## Zweck

WordPress verwendet fuer den internen Django-Bereich eine zentrale Primary-/Fallback-Logik.

- Primary: `https://intern.avfroburger.ch`
- Fallback: `https://intern-avfroburger.ch`

Die stabile WordPress-Einstiegsroute ist:

- `/intern/`

Optionale relative Zielpfade werden ueber `?path=/accounts/login/` uebergeben.

## Wichtigste Einschraenkung

Beide Hostnamen zeigen auf denselben VPS und dieselbe Django-Anwendung.

Die Loesung schuetzt nur gegen Probleme eines einzelnen Hostnamens, DNS-Eintrags oder Zertifikats.
Sie schuetzt nicht gegen einen Ausfall von:

- VPS
- Nginx
- Django
- MariaDB

Wenn die oeffentliche WordPress-Seite selbst ausfaellt, hilft die Fallback-Domain ebenfalls nicht.

## Zentrale Konfiguration

Konfiguriert im Plugin:

- `avf_internal_primary_url`
- `avf_internal_fallback_url`

Optional koennen Konstanten verwendet werden:

- `AVF_INTERNAL_PRIMARY_URL`
- `AVF_INTERNAL_FALLBACK_URL`

Alle serverseitigen Django-Requests laufen ueber `avf_internal_api_request()`.

## Health-Check und Cache

Health-Check-Pfad:

- `GET /healthz/`

Cache-Verhalten:

- erfolgreicher Check: 60 Sekunden
- fehlgeschlagener Primary-Check: 30 Sekunden
- fehlgeschlagener Fallback-Check: 15 Sekunden

Nur HTTP `200` gilt fuer `/healthz/` als gesund.

## Failover-Regeln

### GET und HEAD

Einmaliger Retry auf den Fallback bei:

- DNS-/Transportfehlern
- TLS-Fehlern
- Timeouts
- HTTP `502`
- HTTP `503`
- HTTP `504`

Kein Failover bei fachlichen Antworten wie:

- `400`
- `401`
- `403`
- `404`
- `409`
- `422`

### POST, PUT, PATCH, DELETE

Keine automatische Wiederholung.

Damit werden doppelte Anmeldungen oder doppelte Mutationen vermieden.
Es ist derzeit kein Idempotency-Key-Konzept zwischen WordPress und Django implementiert.

## Lokale Weiterleitungsroute

`/intern/` entscheidet serverseitig:

1. gesunder Primary -> 302 auf Primary
2. Primary ausgefallen, Fallback gesund -> 302 auf Fallback
3. beide ausgefallen -> WordPress-Fehlerseite mit HTTP 503 und `Retry-After: 60`

Es gibt keine offene Redirect-Funktion.
Nur streng validierte relative Pfade werden akzeptiert.

## Admin-Diagnose

Unter `Einstellungen -> AV Froburger Events` werden angezeigt:

- Primary-URL
- Fallback-URL
- aktiver Endpoint
- letzter Status beider Hosts
- Cache-Ablauf
- abgeleitete API-Endpunkte

Die Schaltflaeche `Status erneut pruefen` leert den Health-Cache und prueft beide Hosts neu.

## Medien- und API-URL-Normalisierung

Bekannte Django-Hosts werden intern als Pfad erkannt und fuer die aktuelle aktive Basisadresse neu aufgebaut.
Das betrifft insbesondere:

- `source_url`
- bekannte interne Bild- und Media-URLs aus der Members-API

Externe legitime URLs werden nicht umgeschrieben.

## Cookie- und Login-Verhalten

`intern.avfroburger.ch` und `intern-avfroburger.ch` sind zwei verschiedene Hosts.
Beim Wechsel zwischen beiden Domains kann eine erneute Anmeldung noetig sein.
Es wird kein unsicheres Cookie-Sharing zwischen Domains implementiert.

## Deployment

1. Plugin-Backup erstellen.
2. Geaenderte Plugin- und MU-Plugin-Dateien uebertragen.
3. PHP-Syntax pruefen.
4. WordPress-Cache leeren.
5. Event-Cache und Health-Cache leeren.
6. Rewrite-Regeln einmalig neu aufbauen, zum Beispiel durch Plugin-Reaktivierung oder gezielten Flush.
7. `/intern/` testen.
8. Primary-Ausfall kontrolliert simulieren.
9. Event-GET-Failover testen.
10. Event-Anmeldung ohne doppelten POST pruefen.

## Ausfallsimulation

Sicher:

- WordPress-HTTP-Mocks in Tests
- temporaerer Entwicklerfilter
- lokales Konfigurations-Override

Nicht sicher:

- produktives DNS loeschen
- Firewall blockieren
- Nginx stoppen
- VPS herunterfahren

## Manuelle Elementor- und Datenbank-Aenderungen

Im Repository wurden keine direkt im Code verdrahteten Frontend-Links auf `intern.avfroburger.ch` innerhalb von `public/wp-content` gefunden.
Hart codierte Elementor- oder Datenbank-Links muessen deshalb manuell auf die lokale Route umgestellt werden, zum Beispiel:

- vorher: `https://intern.avfroburger.ch/accounts/login/`
- nachher: `/intern/?path=/accounts/login/`

Keine SQL-Massenersetzung ohne vorherige inhaltliche Pruefung.
