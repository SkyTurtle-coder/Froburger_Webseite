# Remaining Limitations

Stand: Samstag, 18. Juli 2026

## Blockierend fuer oeffentlichen Release

- `impressum` ist absichtlich noch nicht rechtsverbindlich vollstaendig, weil verantwortliche Vertretung und vollstaendige Postanschrift im Projekt nicht verifiziert vorliegen.
- `datenschutz` ist absichtlich noch nicht abschliessend, weil endgueltige Angaben zu Hosting und Drittanbietern noch nicht verifiziert sind.

## Sicherheitsrelevant offen

- `documents` und `events` sind fuer private oder redaktionelle Workflows noch nicht umgesetzt.
- Reverse-Proxy-Auslieferung fuer private Medien ist fuer Production nur vorbereitet, aber nicht an eine echte Infrastruktur gebunden.

## Funktional offen

- Die oeffentlichen Seiten `anlaesse` und `mitglieder` rendern noch nicht aus den neuen CMS-Modellen.
- Browser-Automation fuer Keyboard-, Konsole- und Viewport-Regressionen fehlt weiterhin.

## Nicht blockierend, aber offen

- Fuer Accessibility und Performance fehlen weiterhin instrumentierte Tool-Laeufe wie Axe oder Lighthouse in der lokalen Toolchain.
- Die PostgreSQL-Zielumgebung ist architektonisch gesetzt, die Standard-Tests laufen lokal aber weiterhin auf SQLite.
