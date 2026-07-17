# Changelog

## 2026-07-16

### Security
- Unsichere Demo-Authentifizierung aus dem öffentlichen Frontend entfernt.
- `intern.html` zu einer reinen Hinweis-Seite ohne Passwortprüfung und ohne versteckte Dashboard-Inhalte umgebaut.
- `robots.txt` um Ausschlüsse für `output/`, `reports/`, `Screenshots/`, `.agents/` und `.codex/` ergänzt.

### Functionality
- `script.js` komplett neu aufgebaut.
- Hauptnavigation als Disclosure-Navigation mit echtem Button, `aria-expanded`, Escape-Unterstützung und Klick-/Touch-Steuerung umgesetzt.
- Kalenderlogik für Listen- und Monatsansicht repariert.
- Monatssicht rendert jetzt dynamisch, unterstützt mehrere Termine pro Tag und zeigt verständliche Statusmeldungen.
- Auf kleinen Viewports wird die Monatsansicht nicht mehr als horizontale Scroll-Falle angeboten; dort bleibt die Listenansicht aktiv.
- Platzhalterbeiträge und tote "Mehr laden"-Logik aus `aktuelles.html` entfernt.

### Content and UX
- Neue Seite `mitglied-werden.html` in den bestehenden Stil integriert und inhaltlich präzisiert.
- Tote Footer-Links, Platzhalter `Gegründet [Jahr]` und leere Social-Links entfernt.
- `impressum.html` und `datenschutz.html` angelegt, damit keine toten Rechtslinks mehr existieren.
- Öffentliche Navigation bewirbt den deaktivierten Mitgliederbereich nicht mehr prominent.

### Accessibility
- Falsche `role="menu"`-/`role="menuitem"`-Struktur entfernt.
- Kalenderumschalter als semantische Gruppe ausgezeichnet.
- Redundante Platzhalterbilder im Mitgliederbereich als dekorativ markiert.
- CTA-Kontraste verbessert.

### Performance
- Hero-Hintergrund auf `Bilder/DSC02521-hero.jpg` umgestellt.
- Grosse Inhaltsbilder auf optimierte Web-Varianten umgestellt:
  - `Bilder/DSC01173-web.jpg`
  - `Bilder/DSC01282-web.jpg`
  - `Bilder/DSC01621-web.jpg`
- Startseitenwappen auf `Bilder/Schild.svg` umgestellt.
- Öffentliche Seiten mit Canonical- und Open-Graph-Metadaten ergänzt.

### Deployment
- `sitemap.xml` auf den Stand vom `2026-07-16` aktualisiert und um `impressum.html` sowie `datenschutz.html` ergänzt.
- `output/` als selbstkonsistenter statischer Bundle-Stand synchronisiert, inklusive `Bilder/`, `Zirkel.svg` und `kalender.ics`.
