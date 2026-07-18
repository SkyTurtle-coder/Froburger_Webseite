# Role Permission Matrix

Stand: 2026-07-18

## Rollen

- `anonymous`
- `member`
- `web_aktuar`
- `event_verantwortlich`
- `document_verantwortlich`
- `member_admin`
- `president`
- `system_admin`

## Matrix

| Faehigkeit | anonymous | member | web_aktuar | event_verantwortlich | document_verantwortlich | member_admin | president | system_admin |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Oeffentliche Seiten ansehen | ja | ja | ja | ja | ja | ja | ja | ja |
| Internes Dashboard sehen | nein | ja | ja | ja | ja | ja | ja | ja |
| Mitgliederprofil selbst pflegen | nein | ja | ja | ja | ja | ja | ja | ja |
| Oeffentliche Events sehen | ja | ja | ja | ja | ja | ja | ja | ja |
| Interne Events sehen | nein | ja, falls freigegeben | ja | ja | ja | ja | ja | ja |
| Event-CMS nutzen | nein | nein | ja | ja | nein | nein | ja | ja |
| Dokument-CMS nutzen | nein | nein | ja, ohne hochsensible Verwaltung | nein | ja | nein | ja | ja |
| Mitgliederdokumente herunterladen | nein | ja, falls freigegeben | ja | ja | ja | ja | ja | ja |
| Sensible Dokumente sehen | nein | nein, ausser Zusatzrecht oder Bursch-Rolle | nein | nein | nein | nein | ja | ja |
| Mitgliederverwaltung nutzen | nein | nein | nein | nein | nein | ja | ja | ja |
| Private Profilbilder serverseitig sehen | nein | begrenzt | begrenzt | begrenzt | begrenzt | ja | ja | ja |
| CMS-Posts, Seiten, Homepage, Medien, Karussells | nein | nein | ja | nein | nein | nein | ja | ja |
| Audit- oder Systemaufsicht | nein | nein | begrenzt ueber eigene CMS-Aktionen | nein | begrenzt ueber eigene Dokumentaktionen | nein | ja | ja |

## Hinweise

- `web_aktuar` ist eine Redaktionsrolle, kein Infrastruktur-Admin.
- `event_verantwortlich` fokussiert auf den Eventbereich.
- `document_verantwortlich` fokussiert auf Publikationsdokumente, nicht auf hochsensible Dokumente.
- `member_admin` verantwortet Personen- und Mitgliederprozesse, nicht die allgemeine Site-Redaktion.
- sensible Bereiche bleiben serverseitig geschuetzt, auch wenn UI-Elemente ausgeblendet sind.
