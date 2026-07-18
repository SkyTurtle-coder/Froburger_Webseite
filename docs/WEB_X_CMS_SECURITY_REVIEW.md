# WEB-X CMS Security Review

Stand: 2026-07-18

## Zusammenfassung

Der sicherheitskritischste offene Punkt aus dem vorherigen Stand wurde geschlossen: Mitglieder-Profilbilder laufen nicht mehr ueber eine direkte oeffentliche Rohdatei-URL, sondern ueber einen geschuetzten Members-Endpunkt mit serverseitiger Berechtigungspruefung.

## Geschlossene Findings

### Private Profilbilder

- `MemberProfile.profile_photo` verwendet jetzt privates Storage unter `PRIVATE_MEDIA_ROOT`.
- Templates im Members-/Accounts-Bereich verwenden keine direkte `.url` mehr.
- Auslieferung erfolgt ueber `/members/profile-images/<profile_id>/`.
- Tests decken erlaubten und verweigerten Zugriff sowie die Migrationslogik ab.

### CMS-Zugriffsmatrix

- CMS-Views fuer Dashboard, Beitraege, Medien, Karussells, Homepage und generische Seiten pruefen Login und Permissions serverseitig.
- Preview-Routen bleiben intern und fuer anonyme Benutzer gesperrt.

### Layout- und Eingabegrenzen

- `LayoutPreset` bleibt serverseitige Whitelist.
- kein freies HTML, CSS oder JavaScript im CMS
- Medienvalidierung fuer Bildtypen und Alt-Texte bleibt aktiv

## Reduzierte Risiken

### Oeffentliche Seiten aus CMS

- `about` und `join` koennen jetzt strukturiert aus `Page`/`PageSection` kommen.
- der Import ist idempotent und ueberschreibt bestehende CMS-Inhalte nicht stillschweigend.

### Production-Vorbereitung

- `config.settings.production` nutzt jetzt explizite Security-Env-Variablen.
- `check --deploy` ist ohne Scheinwerte fuer Cookies, Redirect und HSTS-Basis vorbereitet.
- HSTS `includeSubDomains` und `preload` bleiben bewusst separat dokumentierte Spaetschritte.

## Verbleibende offene Punkte

1. `documents` und `events` haben noch keine private Datei- oder Download-Logik.
2. `anlaesse` und `mitglieder` sind noch nicht ans strukturierte CMS angebunden.
3. Fuer echte Production-Auslieferung muss ein kontrollierter interner Private-Media-Endpunkt am Reverse Proxy bereitgestellt werden.
4. Es gibt noch kein Browser-Automationssetup fuer visuelle oder Console-Regressionen.
