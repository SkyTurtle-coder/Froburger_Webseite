# WEB-X CMS Acceptance Test

Stand: 2026-07-18

## Testumgebung

- Datum: 2026-07-18
- Umgebung: lokale Windows-Entwicklung, PowerShell, Django, SQLite (`DATABASE_URL=sqlite:///db.sqlite3`)
- CMS-Rolle: `web_aktuar`
- Abgedeckte Basis:
  - automatisierte CMS- und Public-Tests
  - serverseitige Workflow-Pruefung fuer Preview, Publish, Withdraw und Revisionen
  - Template- und Breakpoint-Sichtpruefung im Code

## Ergebnis

- Automatisierte CMS-Abnahme: bestanden
- Relevante Testdateien:
  - `tests/test_cms_views.py`
  - `tests/test_public_pages.py`
  - `tests/test_member_profiles.py`
- Lokaler Pflichtlauf am 2026-07-18:
  - `uv run ruff check .`: erfolgreich
  - `uv run python manage.py check`: erfolgreich
  - `uv run python manage.py makemigrations --check`: erfolgreich
  - `uv run pytest -q`: `69 passed`

## Abnahmeszenario

1. Als Web-X anmelden.
   Erwartet: geschuetzter Zugang zum Mitgliederbereich.
   Ergebnis: durch Rollen- und View-Tests abgedeckt, erfolgreich.
2. `/cms/` oeffnen.
   Erwartet: Dashboard nur fuer berechtigte Benutzer sichtbar.
   Ergebnis: erfolgreich.
3. Neues Bild hochladen.
   Erwartet: Upload erlaubt, Audit-Eintrag entsteht.
   Ergebnis: erfolgreich.
4. Alt-Text erfassen.
   Erwartet: Medium speichert Alt-Text.
   Ergebnis: erfolgreich.
5. Neuen Beitrag erstellen.
   Erwartet: Formular verfuegbar, Entwurf speicherbar.
   Ergebnis: erfolgreich.
6. Vordefiniertes Layout auswaehlen.
   Erwartet: nur freigegebene Layouts sichtbar.
   Ergebnis: erfolgreich.
7. Titelbild auswaehlen.
   Erwartet: nur sichtbarkeitskompatible Medien nutzbar.
   Ergebnis: erfolgreich.
8. Teaser erfassen.
   Erwartet: Beitrag speichert Teaser.
   Ergebnis: erfolgreich.
9. Textblock ergaenzen.
   Erwartet: Blockformular speichert Inhalt.
   Ergebnis: erfolgreich.
10. Bild-Text-Block ergaenzen.
    Erwartet: Blockformset akzeptiert erlaubte Blocktypen.
    Ergebnis: serverseitig vorbereitet und validiert.
11. Beitrag als Entwurf speichern.
    Erwartet: Entwurfsstatus bleibt intern.
    Ergebnis: erfolgreich.
12. Vorschau oeffnen.
    Erwartet: Vorschau nur intern sichtbar.
    Ergebnis: erfolgreich.
13. Beitrag veroeffentlichen.
    Erwartet: Statuswechsel und Revision.
    Ergebnis: erfolgreich.
14. Beitrag auf der Startseite anpinnen.
    Erwartet: Pinning nur fuer publizierte oeffentliche Beitraege.
    Ergebnis: erfolgreich.
15. Pin-Prioritaet setzen.
    Erwartet: Reihenfolge wird uebernommen.
    Ergebnis: erfolgreich.
16. Oeffentliche Startseite pruefen.
    Erwartet: angepinnte Beitraege und Homepage-Inhalte werden angezeigt.
    Ergebnis: erfolgreich.
17. Oeffentliche Seite `Aktuelles` pruefen.
    Erwartet: publizierte CMS-Beitraege erscheinen.
    Ergebnis: erfolgreich.
18. Beitragsdetailseite pruefen.
    Erwartet: publizierter Beitrag ist ueber `/aktuelles/<slug>/` erreichbar.
    Ergebnis: erfolgreich.
19. Beitrag bearbeiten.
    Erwartet: bestehender Beitrag laesst sich aktualisieren.
    Ergebnis: erfolgreich.
20. Revision pruefen.
    Erwartet: Revisionsliste vorhanden.
    Ergebnis: erfolgreich.
21. Fruehere Revision wiederherstellen.
    Erwartet: alter Inhalt wird restauriert, neue Revision entsteht.
    Ergebnis: erfolgreich.
22. Neues Karussell erstellen.
    Erwartet: Editor verfuegbar.
    Ergebnis: erfolgreich.
23. Mindestens drei Bilder zuweisen.
    Erwartet: mehrere Slides moeglich.
    Ergebnis: erfolgreich.
24. Reihenfolge aendern.
    Erwartet: Positionen werden validiert und gespeichert.
    Ergebnis: erfolgreich.
25. Karussell der Startseite zuweisen.
    Erwartet: Homepage-Editor akzeptiert verknuepftes Karussell.
    Ergebnis: erfolgreich.
26. Startseite erneut pruefen.
    Erwartet: neue Karussellausgabe sichtbar.
    Ergebnis: erfolgreich.
27. Beitrag zurueckziehen.
    Erwartet: Status zurueck auf Entwurf.
    Ergebnis: erfolgreich.
28. Pruefen, dass er oeffentlich nicht mehr erreichbar ist.
    Erwartet: kein oeffentlicher Treffer fuer unveroeffentlichte Inhalte.
    Ergebnis: erfolgreich.
29. Als normales Mitglied `/cms/` aufrufen.
    Erwartet: kein CMS-Zugriff.
    Ergebnis: erfolgreich.
30. Als anonymer Benutzer eine Entwurfs- oder Preview-URL aufrufen.
    Erwartet: kein Zugriff.
    Ergebnis: erfolgreich.

## UX- und Frontend-Pruefung

- Desktop- und Mobil-Breakpoints wurden auf Codeebene in `static/css/cms.css` und den CMS-Templates geprueft.
- Sichtbare Fokuszustaende bleiben ueber bestehende Button- und Linkstile erhalten.
- Leere Zustaende fuer Listen und Karten sind in CMS-Views vorhanden.
- Eine echte Browser-Konsole oder Playwright-/Selenium-Automation ist im Repository derzeit nicht vorhanden; ein interaktiver Browserlauf ist deshalb noch offen.

## Gefundene Fehler

- keine fachlichen CMS-Workflowfehler im automatisierten Akzeptanzlauf

## Behobene Fehler

- generische CMS-Seitenliste, Editor, Preview, Publish/Withdraw und Revisionen fuer `Page`
- oeffentliche CMS-Anbindung fuer `about` und `join` ueber `import_existing_public_pages`
- geschuetzte Profilbild-Auslieferung statt direkter `profile_photo.url`

## Verbleibende Einschraenkungen

- kein Browser-Automationssetup fuer Konsole, Keyboard-Flow und visuelle Viewport-Regressionen
- `anlaesse` und `mitglieder` sind noch nicht an das strukturierte CMS angeschlossen
- private Dokumenten- und Event-Workflows bleiben offen
