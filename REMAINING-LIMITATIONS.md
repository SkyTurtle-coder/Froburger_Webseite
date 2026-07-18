# Remaining Limitations

Stand: Samstag, 18. Juli 2026

## Blockierend fuer oeffentlichen Release

- `impressum` ist absichtlich noch nicht rechtsverbindlich vollstaendig, weil verantwortliche Vertretung und vollstaendige Postanschrift im Projekt nicht verifiziert vorliegen.
- `datenschutz` ist absichtlich noch nicht abschliessend, weil endgueltige Angaben zu Hosting und Drittanbietern noch nicht verifiziert sind.

## Sicherheitsrelevant offen

- Profilfotos aus dem Mitgliederbereich liegen weiterhin im oeffentlichen Media-Root und nicht in einem privaten Auslieferungsfluss.
- Die neue CMS-Schicht besitzt noch keine Preview-, Publish- oder Restore-Views mit serverseitiger Audit-Integration.
- `documents` und `events` sind fuer private oder redaktionelle Workflows noch nicht umgesetzt.

## Funktional offen

- Die Web-X-CMS-Grundmodelle sind vorhanden, aber die benutzerfreundliche Editor-Oberflaeche fehlt noch.
- Die oeffentlichen Seiten `home`, `aktuelles`, `anlaesse`, `mitglieder`, `mitglied-werden` und `ueber-uns` rendern noch nicht aus den neuen CMS-Modellen.
- Es gibt noch keinen Import-Command fuer bestehende Seiteninhalte.

## Nicht blockierend, aber offen

- Fuer Accessibility und Performance fehlen weiterhin instrumentierte Tool-Laeufe wie Axe oder Lighthouse in der lokalen Toolchain.
- Die PostgreSQL-Zielumgebung ist architektonisch gesetzt, die Standard-Tests laufen lokal aber weiterhin auf SQLite.
