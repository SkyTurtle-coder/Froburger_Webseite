# Projektueberblick

## Zweck

Die Website praesentiert die AV Froburger als oeffentlich sichtbare Vereinswebsite mit geschuetztem Mitgliederbereich. Das Repository enthaelt den produktiven Django-Quellstand, die Web-X-CMS-Basis sowie die betriebliche und organisatorische Dokumentation fuer Pflege, Rollout und Review.

## Architektur

- Django-Projekt mit getrennten Settings fuer Development, Test und Production
- modulare Apps fuer Accounts, Members, Content, Events, Documents, Media Library und Audit
- Django-Templates fuer oeffentliche und interne Seiten
- `static/` fuer oeffentliche Assets
- `private_media/` fuer geschuetzte Dateien wie Profilbilder und Dokumentversionen
- relationale Datenbank ueber `DATABASE_URL`, lokal typischerweise SQLite oder PostgreSQL

Es gibt bewusst:

- serverseitige Berechtigungspruefungen statt rein versteckter Navigation
- strukturierte CMS-Modelle statt freier HTML-Eingabe
- getrennte Datenfluesse fuer oeffentliche und private Personendaten

## Oeffentliche Seiten und Verantwortung

| Route | Funktion im Projekt |
| --- | --- |
| `/` | Startseite mit CMS-Inhalten und hervorgehobenen Beitraegen |
| `/aktuelles/` | redaktionelle Berichte und Rueckblicke |
| `/anlaesse/` | oeffentliche Veranstaltungen und Kalenderintegration |
| `/mitglieder/` | oeffentliche Mitgliederseite aus freigegebenen Personendaten |
| `/mitglied-werden/` | Conversion-Seite fuer Interessenten |
| `/ueber-uns/` | Kontext, Geschichte und Selbstverstaendnis |
| `/impressum/` | rechtliche Pflichtseite mit sichtbaren TODO-Platzhaltern bis zur Verifikation |
| `/datenschutz/` | Datenschutzinformationen mit sichtbaren TODO-Platzhaltern bis zur Verifikation |

## Interne Bereiche

- `/accounts/` fuer Authentifizierung und Kontoablaufe
- `/intern/` als Einstieg in den geschuetzten Mitgliederbereich
- `/members/` fuer Profil, Verzeichnis, Dokumente und Mitgliederfunktionen
- `/cms/` fuer Web-X-CMS, Veranstaltungen, Dokumente und strukturierte Seitenpflege

## CMS- und Datenstrategie

- Startseite, News, Seiten, Karussells, Veranstaltungen und Dokumente werden ueber Django-Modelle gepflegt.
- Bestehende statische Inhalte werden ueber idempotente Management-Commands in CMS-Modelle uebernommen.
- Die oeffentliche Mitgliederseite verwendet `members.PublicMemberProfile` statt direkter Freigabe interner Profildaten.
- Dokumente und Profilbilder werden nicht direkt oeffentlich ausgeliefert, sondern serverseitig authorisiert.

## SEO- und Betriebsdateien

- `sitemap.xml` beschreibt die oeffentlich indexierbaren Seiten.
- `robots.txt` schliesst interne oder lokale Arbeitsbereiche aus.
- ICS-Feeds werden ueber Django-Endpunkte fuer sichtbare Veranstaltungen erzeugt.
- Branding-Assets liegen unter `static/`.

## Nicht versionierte Arbeitsbereiche

Diese Pfade sind bewusst nicht Teil des GitHub-Repositories:

- `output/`
- `reports/`
- `Screenshots/`
- `.agents/`
- `.codex/`

Sie koennen lokal weiterhin hilfreich sein, sind aber nicht Teil des offiziellen Quellstands.

## Aktuelle fachliche Restrisiken

- `impressum` und `datenschutz` bleiben bis zur Verifikation rechtlicher und technischer Fakten bewusst unvollstaendig.
- Die produktive Reverse-Proxy-Konfiguration fuer private Medien und Downloads ist dokumentiert, aber env-abhaengig.
- Eine CI-Pipeline fuer die lokalen Quality-Gates fehlt noch.
