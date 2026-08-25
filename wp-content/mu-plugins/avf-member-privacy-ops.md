# AVF Member Privacy Ops

## Architektur

- Der Django-Endpunkt wird nur waehrend `AVF_Members_Page::refresh_snapshot()` abgefragt.
- Mitgliederbilder werden lokal validiert, neu encodiert und privat unter `app/member-media-private/` gespeichert.
- Die oeffentliche Auslieferung erfolgt ausschliesslich ueber `/member-media/<32hex>/`.
- Die Token-Registry liegt in der WordPress-Option `avf_member_media_registry_v1`.
- Bekannte alte Upload-Pfade werden nach Migration als Tombstones in `avf_member_media_tombstones_v1` gehalten.
- Das Frontend rendert nur Token-URLs mit lokal vorhandener Datei. Sonst erscheint ein neutraler Platzhalter.

## Verzeichnisse

- Lokal: `C:\Users\phili\Local Sites\av-froburger\app\member-media-private\`
- Lokal oeffentlicher Altordner: `public/wp-content/uploads/avf-members/`
- Testsystem/Produktion: analog ausserhalb des Webroots konfigurieren, bevorzugt ueber `AVF_MEMBER_MEDIA_PRIVATE_DIR`

> **Sicherheitsvorgabe:** `member-media-private` enthaelt personenbezogene Medien und darf nie innerhalb eines Git-Repository-Roots, eines gemeinsamen Workspace oder eines pauschal gesicherten/archivierten Verzeichnisses liegen. Backup-, Sync- und Support-Archive muessen diesen Ordner explizit ausschliessen.

## Lokalen privaten Medienordner aus dem Workspace auslagern

Der Standardpfad ist nur aus Kompatibilitaetsgruenden vorhanden. Neue lokale Installationen verwenden einen Speicherort ausserhalb von `app/`, zum Beispiel `C:\AVF-private-data\member-media-private`.

1. WordPress und lokale Sync-Prozesse anhalten.
2. Den Zielordner erstellen und die vorhandenen Dateien kopieren: `New-Item -ItemType Directory -Force C:\AVF-private-data\member-media-private`; `robocopy C:\Users\phili\Local Sites\av-froburger\app\member-media-private C:\AVF-private-data\member-media-private /E /COPY:DAT /R:1 /W:1`.
3. In der lokalen, nicht versionierten WordPress-Konfiguration vor dem Laden des Mu-Plugins `define( 'AVF_MEMBER_MEDIA_PRIVATE_DIR', 'C:/AVF-private-data/member-media-private' );` setzen.
4. Einen Token-Bildabruf lokal pruefen. Erst nach erfolgreicher Pruefung den alten Workspace-Ordner manuell und recoverable entfernen.

## Snapshot-Refresh

- Lock: `avf_members_refresh_lock_v1`
- Timeout Django: 10s pro Bild, 8s JSON-Request
- Redirects: maximal 1 pro Bildrequest
- Erlaubte Bildtypen: `image/jpeg`, `image/png`, `image/webp`
- Maximalgroesse: `8388608` Bytes
- Fehlerverhalten: alter Snapshot und alte Registry bleiben aktiv, temporaere Dateien werden bereinigt

## Dry-Run

- Admin: `Werkzeuge -> AVF Member Privacy`
- Gespeicherter Plan: WordPress-Option `avf_member_privacy_migration_plan_v1`
- Plan-ID: `avfmp-<hashprefix>`
- Statuswerte: `planned`, `running`, `completed`, `failed`
- Quellzustands-Hash: stabil ueber Varianten, Dateigroessen, Content-Hashes und Attachment-Metadaten
- Wiederholter Dry-Run mit identischem Quellzustand verwendet denselben gespeicherten Plan

## Migration

- Nur lokal oder auf dem Testsystem ausfuehren, nie direkt in Produktion beginnen
- Vorher Backup von Datenbank und `wp-content/uploads/`
- Ablauf:
  1. Dry-Run erstellen oder wiederverwenden
  2. Quellzustands-Hash pruefen
  3. Plan ausfuehren
  4. Neue private Datei erzeugen
  5. Registry aktualisieren
  6. Tombstones fuer alte URLs registrieren
  7. Alte oeffentliche Dateien entfernen
- Alte URLs werden nicht weitergeleitet
- Bekannte alte Dateien liefern nach Migration `410 Gone`

## HTTP-Pruefung

Mitgliederseite:

```powershell
curl.exe -I --connect-to test.avfroburger.ch:80:127.0.0.1:10004 "http://test.avfroburger.ch/mitglieder/"
```

Token-Bild:

```powershell
curl.exe -I --connect-to test.avfroburger.ch:80:127.0.0.1:10004 "http://test.avfroburger.ch/member-media/REPLACE_WITH_32HEX/"
```

Bedingte Anfrage:

```powershell
curl.exe -I -H 'If-None-Match: "ETAG_HERE"' --connect-to test.avfroburger.ch:80:127.0.0.1:10004 "http://test.avfroburger.ch/member-media/REPLACE_WITH_32HEX/"
```

Alte Datei:

```powershell
curl.exe -I --connect-to test.avfroburger.ch:80:127.0.0.1:10004 "http://test.avfroburger.ch/wp-content/uploads/avf-members/Foto_farbig.webp"
```

Smoke-Test:

```powershell
powershell -ExecutionPolicy Bypass -File intern/tools/wordpress/test-member-privacy-smoke.ps1
```

## Hostpoint / Nginx

- Die aktive Schutzschicht fuer aktuelle Mitgliederbilder liegt in PHP auf `/member-media/<token>/`, nicht in `.htaccess`
- `.htaccess` im Altordner kann fuer Apache-Kompatibilitaet bleiben, ist unter Nginx nicht die Hauptschutzschicht
- Alte oeffentliche Dateien muessen physisch entfernt werden, damit Nginx sie nicht mehr statisch mit `200` ausliefert
- Fuer private Verzeichnisse ausserhalb des Webroots muessen Schreibrechte vorhanden sein
- Nach Deployment eventuell Nginx-/Page-/Object-Cache leeren

## Google Search Console

Nach erfolgreichem Deployment auf dem Testsystem bzw. spaeter in Produktion:

- alte Bild-URLs entfernen
- alte Attachment-URLs entfernen
- Mitgliederseite auf `noindex` pruefen
- Token-URLs auf `X-Robots-Tag: noindex, noimageindex` pruefen

## Restrisiken

- Oeffentlich sichtbare Bilder bleiben manuell speicherbar
- Suchmaschinen-Deindexierung benoetigt Zeit
- Search-Console-Entfernung ist nach Deployment weiterhin sinnvoll
