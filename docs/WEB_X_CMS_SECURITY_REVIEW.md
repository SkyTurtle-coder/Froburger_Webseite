# WEB-X CMS Security Review

Stand: 2026-07-18

## Zusammenfassung

Die neue CMS-Grundlage verbessert die fachliche Trennung deutlich, loest aber noch nicht alle sicherheitsrelevanten Punkte. Besonders wichtig bleibt die saubere Trennung zwischen oeffentlichen CMS-Medien und privaten Mitgliederdaten.

## Kritische Findings

### Profilfotos sind weiterhin public media

- `MemberProfile.profile_photo` wird noch ueber das oeffentliche Media-Root ausgeliefert.
- Das ist fuer den Mitgliederbereich nicht akzeptabel.
- Folgearbeit: auf privaten Medienfluss oder bewusst oeffentliche Freigabe mit eigener Policy umstellen.

## Hohe Findings

### CMS-Oberflaeche fehlt noch

- Es gibt noch keine Preview-, Publish- oder Restore-Views.
- Die neuen Permissions koennen darum noch nicht auf echte CMS-Endpunkte angewendet werden.

### Audit ist fuer CMS-Aktionen noch nicht verdrahtet

- Modell- und Rollenbasis ist vorhanden.
- `publish`, `unpublish`, `restore`, `pin`, `upload` und `replace` muessen spaeter protokolliert werden.

### Private Dokumenten- und Event-Fluesse fehlen noch

- `documents` und `events` sind weiterhin Platzhalter.
- Private Dateizugriffe duerfen spaeter nie ueber direkte URLs geloest werden.

## Bereits behoben oder reduziert

### Unkontrollierte Layoutwahl

- `LayoutPreset` validiert nur serverseitig freigegebene Schluessel.
- Templatepfade kommen aus einer Whitelist im Code.

### Freie Bildformate

- `MediaAsset` akzeptiert aktuell nur JPEG, PNG und WebP.
- SVG bleibt gesperrt.

### Fehlende Alt-Texte

- nicht dekorative CMS-Bilder brauchen einen Alt-Text
- dekorative Bilder muessen bewusst als dekorativ markiert werden

### Rollentrennung fuer Web-X

- `web_aktuar` traegt jetzt eigene CMS- und Medienrechte
- `member_admin` erhaelt diese CMS-Rechte nicht automatisch

## Offene Folgearbeiten

1. private Profilmedien absichern
2. CMS-Views mit Login-, Permission- und Objektpruefungen bauen
3. Preview-Zugriffe signieren oder serverseitig strikt an Sessions binden
4. Audit-Events fuer CMS-Aktionen implementieren
5. spaetere Upload-Validierung um Bildergroessen, Metadatenstrategie und Derivate erweitern
