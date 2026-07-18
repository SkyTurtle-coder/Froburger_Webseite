# Remaining Limitations

Stand: Samstag, 18. Juli 2026

## Blockierend fuer oeffentlichen Release

- `impressum` ist absichtlich noch nicht rechtsverbindlich vollstaendig, weil verantwortliche Vertretung und vollstaendige Postanschrift im Projekt nicht verifiziert vorliegen.
- `datenschutz` ist absichtlich noch nicht abschliessend, weil endgueltige Angaben zu Hosting, Auftragsverarbeitung und Drittanbietern noch nicht verifiziert sind.

## Produktionsrelevant offen

- Reverse-Proxy-Auslieferung fuer private Medien und Dokument-Downloads ist vorbereitet, aber noch nicht an eine echte Zielinfrastruktur gebunden.
- `SECURE_HSTS_INCLUDE_SUBDOMAINS` und `SECURE_HSTS_PRELOAD` bleiben bewusst erst fuer die spaetere echte HTTPS-Freigabe aktivierbar.
- Produktionspfade, Service-User und Zertifikatspfade bleiben env-spezifisch offen.

## Prozess- und Tooling-Luecken

- Eine CI-Pipeline fuer `ruff`, Django-Checks und pytest ist noch nicht eingerichtet.
- Fuer Accessibility und Performance fehlen weiterhin instrumentierte Tool-Laeufe wie Axe oder Lighthouse in der lokalen Toolchain.

## Nicht blockierend

- Die lokale Testbaseline laeuft weiterhin auf SQLite, obwohl die Zielarchitektur PostgreSQL vorsieht.
- Browser-E2E fuer die kritischsten Redaktionswege ist vorhanden; breitere Cross-Browser-Matrix und Performance-Messungen sind aber noch nicht Teil der Standardchecks.
