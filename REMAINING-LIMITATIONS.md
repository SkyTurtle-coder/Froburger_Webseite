# Remaining Limitations

Stand: Samstag, 18. Juli 2026

## Noch offen im vereinfachten Post-CMS

- direkte Bildauswahl aus der bestehenden Medienbibliothek innerhalb des neuen Post-Rich-Text-Editors ist noch nicht integriert
- der vereinfachte Zwei-Schritt-Workflow gilt fuer Beitraege; Seiten, Veranstaltungen und Dokumente haben weiterhin eigene, fachlich differenzierte Editoren

## Produktionsrelevant offen

- `impressum` und `datenschutz` enthalten weiterhin sichtbare `TODO`-Platzhalter fuer nicht verifizierte Rechts- und Hosting-Fakten
- fuer Production bleibt ein eigener starker Secret-Key zwingend noetig
- `SECURE_HSTS_INCLUDE_SUBDOMAINS` und `SECURE_HSTS_PRELOAD` bleiben bewusst erst fuer die spaetere echte HTTPS-Freigabe aktivierbar
- produktive Reverse-Proxy-Auslieferung fuer private Medien und Dokumente bleibt infra-abhaengig

## Prozess- und Tooling-Luecken

- keine CI-Pipeline fuer `ruff`, Django-Checks, pytest und Browser-E2E
- keine automatische Accessibility- oder Lighthouse-Pruefung in der lokalen Standardtoolchain

## Nicht blockierend

- lokale Akzeptanzpruefung laeuft weiterhin auf SQLite, Zielarchitektur bleibt PostgreSQL
- Altbeitraege werden erst beim naechsten Speichern vollstaendig auf die neue `body_html`-Fuehrung umgestellt
