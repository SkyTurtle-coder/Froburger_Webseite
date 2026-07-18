# WEB-X CMS Security Review

Stand: 2026-07-18

## Zusammenfassung

Die sicherheitskritischen Restpunkte aus dem vorherigen Stand wurden fuer den Branch weitgehend geschlossen:

- private Mitglieder-Profilbilder laufen ueber serverseitig authorisierte Delivery
- private Dokumente laufen ueber serverseitig authorisierte Delivery
- Event- und Dokumentensichtbarkeit wird serverseitig pro Benutzer gefiltert
- die oeffentliche Mitgliederseite nutzt eine separate, freigegebene Personenprojektion
- Browser-E2E prueft kritische Redaktionswege inklusive Download- und Sichtbarkeitsregeln

## Geschlossene Findings

### Private Profilbilder

- `MemberProfile.profile_photo` verwendet privates Storage unter `PRIVATE_MEDIA_ROOT`
- Templates nutzen keine direkte Rohdatei-URL mehr
- Auslieferung erfolgt ueber `/members/profile-images/<profile_id>/`

### Private Dokumente

- Dokumentversionen liegen im geschuetzten Storage
- Downloads laufen ueber einen serverseitigen Download-View
- `X-Content-Type-Options: nosniff` wird gesetzt
- sensible Dokumente sind serverseitig von normalen Mitgliederdokumenten getrennt

### CMS-Zugriffsmatrix

- CMS-Views pruefen Login und Permissions serverseitig
- ausgeblendete Navigation ist nicht der eigentliche Schutz
- Preview- und Revisionsrouten bleiben intern

### Strukturierte Mitgliederseite

- `PublicMemberProfile` trennt oeffentliche Personendaten vom internen Mitgliederprofil
- `people_list`-Bloecke werden gegen Layout- und Optionsschema validiert
- private Kontaktfelder aus dem Mitgliederbereich erscheinen nicht oeffentlich

### Link-Validierung

- CMS-Links erlauben nur site-relative Pfade, absolute Web-URLs, `mailto:` und `tel:`
- unklare oder freie Schemen werden serverseitig verworfen

## Reduzierte Restrisiken

- relative CMS-Links koennen weiterhin kontrolliert intern verwendet werden, ohne auf erfundene absolute Produktions-URLs angewiesen zu sein
- Event-ICS und Dokument-Downloads folgen denselben Sichtbarkeitsregeln wie die Listen- und Detailseiten
- neue Browser-E2E-Tests schlagen bei Console- oder Page-Errors fehl

## Verbleibende offene Punkte

1. Fuer echte Production-Auslieferung muss ein kontrollierter interner Private-Media-Endpunkt am Reverse Proxy bereitgestellt werden.
2. Verifizierte rechtliche und Hosting-Fakten fuer Impressum und Datenschutz fehlen weiterhin.
3. Ein CI-Lauf fuer die lokalen Quality-Gates ist noch nicht eingerichtet.
