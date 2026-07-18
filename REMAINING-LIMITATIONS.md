# Remaining Limitations

Stand: Samstag, 18. Juli 2026

## Blockierend fuer oeffentlichen Release

- `impressum` ist absichtlich noch nicht rechtsverbindlich vollstaendig, weil verantwortliche Vertretung und vollstaendige Postanschrift im Projekt nicht verifiziert vorliegen.
- `datenschutz` ist absichtlich noch nicht abschliessend, weil endgueltige Angaben zu Hosting und Drittanbietern noch nicht verifiziert sind.

## Sicherheitsrelevant offen

- Profilfotos aus dem Mitgliederbereich liegen weiterhin im oeffentlichen Media-Root und nicht in einem privaten Auslieferungsfluss.
- Private Medienauslieferung ausserhalb des oeffentlichen `/media/`-Flows ist noch nicht umgesetzt.
- `documents` und `events` sind fuer private oder redaktionelle Workflows noch nicht umgesetzt.

## Funktional offen

- Die CMS-Oberflaeche deckt aktuell Dashboard, Beitraege, Medien, Karussells und die Homepage ab; generische Redaktionsseiten ausserhalb der Homepage fehlen noch.
- Die oeffentlichen Seiten `anlaesse`, `mitglieder`, `mitglied-werden` und `ueber-uns` rendern noch nicht aus den neuen CMS-Modellen.
- Es gibt noch keinen Import-Command fuer bestehende Seiteninhalte.

## Nicht blockierend, aber offen

- Fuer Accessibility und Performance fehlen weiterhin instrumentierte Tool-Laeufe wie Axe oder Lighthouse in der lokalen Toolchain.
- Die PostgreSQL-Zielumgebung ist architektonisch gesetzt, die Standard-Tests laufen lokal aber weiterhin auf SQLite.
