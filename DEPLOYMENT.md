# Deployment

Stand: Donnerstag, 16. Juli 2026

## Empfehlung

- Bearbeitungsstand: Projektroot
- Deploy-Bundle: `output/`

`output/` wurde am 16. Juli 2026 erneut mit dem finalen Root-Stand synchronisiert und enthält jetzt auch die benötigten Assets.

## Vor dem Upload

1. Prüfe, ob die fehlenden rechtlichen Angaben für `impressum.html` und `datenschutz.html` verbindlich ergänzt wurden.
2. Prüfe, ob `output/` noch synchron ist.
3. Stelle sicher, dass nicht der Projektordner selbst, sondern nur der Inhalt von `output/` als Webroot veröffentlicht wird.

## Deploy-Schritte

1. Inhalt von `output/` auf die Ziel-Webroot hochladen.
2. Nicht mitdeployen:
   - `reports/`
   - `Screenshots/`
   - `.agents/`
   - `.codex/`
   - `FINAL-REPORT.md`
   - interne Arbeitsdokumente ausserhalb des Deploy-Bundles
3. Nach dem Upload kontrollieren:
   - Startseite lädt
   - `anlaesse.html` rendert
   - `mitglied-werden.html` rendert
   - `robots.txt` und `sitemap.xml` sind erreichbar

## Wichtiger Hinweis

Trotz deploybarem Bundle ist der öffentliche Release per 16. Juli 2026 fachlich noch nicht freigegeben, solange Impressum und Datenschutz nicht verbindlich vervollständigt sind.
