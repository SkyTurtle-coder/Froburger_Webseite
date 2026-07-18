# WEB-X CMS: Aktueller Stand

Stand: 2026-07-18

## Kurzfazit

Das Repository ist heute kein fertiges Web-X-CMS, aber auch nicht mehr nur ein statischer Seitenprototyp. Die Django-Basis, der Mitgliederbereich und die Rollenlogik sind vorhanden. Mit diesem Arbeitsstand existiert neu eine strukturierte CMS-Grundlage in `apps/content` und `apps/media_library`, inklusive Layout-Presets, Publikationsstatus, Pinning, Karussells und Revisions-Snapshots.

Noch offen sind die redaktionelle Oberflaeche, Preview-/Publish-Views, Restore-Workflows, die Anbindung der oeffentlichen Seiten an die neuen Modelle sowie die Migration der bestehenden statischen Inhalte.

## Bereits vorhanden

### Technische Basis

- Django-Projekt mit getrennten Settings fuer Development, Test und Production
- Custom User, Einladungssystem und Audit-Basis
- oeffentliche Seiten ueber Django-Templates
- Mitgliederbereich mit Profilpflege, Verzeichnis und Rollen-Bootstrap
- getrennte Verzeichnisse fuer `static/`, `media/` und `private_media/`

### Neue CMS-Grundlage

- `media_library.MediaAsset` fuer kontrollierte Bilduploads mit Alt-Text-Regel, MIME-/Formatpruefung und Metadaten
- `content.LayoutPreset` als serverseitig freigegebene Layout-Whitelist
- `content.Page` und `content.PageSection` fuer bearbeitbare Seiten mit geschuetzter Grundstruktur
- `content.Post` und `content.PostBlock` fuer flexible redaktionelle Inhalte
- `content.Carousel` und `content.CarouselItem` fuer Galerien und Karussells
- `content.PageRevision`, `content.PostRevision` und `content.CarouselRevision` fuer nachvollziehbare Snapshots
- Queryset-Helfer fuer publizierte und sichtbare Inhalte
- Homepage-Pinning mit konfigurierbarem Standardlimit ueber `CMS_HOMEPAGE_PIN_LIMIT`
- `bootstrap_roles` erweitert um echte CMS- und Medienrechte fuer `web_aktuar`

## Noch fehlend

### Redaktionsoberflaeche

- eigenes Web-X-Dashboard fuer Seiten, Beitraege, Medien und Karussells
- Formulare fuer Blockbearbeitung ohne technische Kenntnisse
- Preview-URLs im echten Seitendesign
- Restore-Oberflaeche fuer Revisionsstaende

### Oeffentliche Auslieferung

- `home`, `aktuelles`, `anlaesse`, `mitglieder`, `mitglied-werden` und `ueber-uns` werden noch nicht aus den neuen CMS-Modellen gerendert
- bestehende Template-Inhalte wurden noch nicht in `Page`, `Post` oder `Carousel` migriert
- es gibt noch keinen Import-Command fuer bestehende Seiteninhalte

### Sicherheits- und Betriebsgaenge

- Profilfotos aus dem Mitgliederbereich liegen weiterhin im oeffentlichen Media-Root
- Audit ist fuer Einladungen vorhanden, aber noch nicht fuer CMS-Aktionen integriert
- `events`, `documents` und private Dateiauslieferung sind fuer das CMS noch nicht umgesetzt

## Teilweise umgesetzt

- Layout-Presets sind modelliert und per Datenmigration seedbar, aber noch nicht in einer benutzerfreundlichen Auswahloberflaeche verfuegbar
- Revisionsmodelle und Snapshot-Methoden existieren, eine Wiederherstellung aus der Oberflaeche fehlt
- Homepage-Pinning ist fachlich modelliert, aber noch nicht im oeffentlichen Startseitentemplate eingebunden
- Medienvalidierung ist implementiert, aber eine redaktionelle Such-/Filteroberflaeche fehlt

## Relevante Modelle

- `apps/accounts/models.py`: `User`, `AccountInvitation`
- `apps/members/models.py`: `MemberProfile`
- `apps/audit/models.py`: `AuditLogEntry`
- `apps/media_library/models.py`: `MediaAsset`
- `apps/content/models.py`: `LayoutPreset`, `Page`, `PageSection`, `Post`, `PostBlock`, `Carousel`, `CarouselItem`, `PageRevision`, `PostRevision`, `CarouselRevision`

## Relevante URLs und Templates

### Bestehende oeffentliche Routen

- `/`, `/aktuelles/`, `/anlaesse/`, `/mitglieder/`, `/mitglied-werden/`, `/ueber-uns/`, `/impressum/`, `/datenschutz/`
- Templates weiterhin unter `templates/public/pages/`

### Bestehende interne Routen

- `/accounts/`
- `/members/me/`
- `/members/directory/`
- `/members/admin/`

### Noch fehlende CMS-Routen

- Web-X-Dashboard
- Post-Editor
- Seiteneditor
- Medienbibliothek
- Karussellverwaltung
- Preview- und Restore-Routen

## Relevante Berechtigungen

### Bereits vorhanden

- `accounts.add_accountinvitation`
- `members.manage_member_profiles`
- `members.view_sensitive_documents`
- Default-Model-Permissions auf bestehenden Apps

### Neu hinzugekommen

- `content.publish_page`
- `content.preview_page`
- `content.restore_page_revision`
- `content.publish_post`
- `content.preview_post`
- `content.pin_post_homepage`
- `content.restore_post_revision`
- `content.preview_unpublished_content`
- `content.preview_carousel`
- `content.restore_carousel_revision`
- `media_library.publish_mediaasset`
- `media_library.manage_private_mediaasset`

## Empfohlene Erweiterungsstrategie

1. Die neue Modellbasis als verbindliche Source of Truth fuer CMS-Inhalte verwenden.
2. Zuerst `Post` und `MediaAsset` ueber eine einfache Web-X-Oberflaeche nutzbar machen.
3. Danach die Startseite und `Aktuelles` auf die neuen Datenmodelle umstellen.
4. Anschliessend geschuetzte Seitenstrukturen ueber `Page` und `PageSection` migrieren.
5. `anlaesse` spaeter ueber eigene Event-Modelle statt ueber generische Blocks anbinden.

## Hauptrisiken

- Die oeffentlichen Templates sind visuell fein abgestimmt; unkontrollierte Markup-Aenderungen riskieren Designregressionen.
- `static/script.js` ist eng an die heutige Event-HTML-Struktur gekoppelt.
- Mitgliederdaten sind intern und oeffentlich doppelt modelliert; eine spaetere Zusammenfuehrung braucht Datenschutz- und Freigaberegeln.
- Profilfotos sind derzeit noch kein privater Medienfluss.
