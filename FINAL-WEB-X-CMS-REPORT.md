# FINAL WEB-X CMS REPORT

Stand: 2026-07-18

## 1. Branch und Ausgangslage

- Arbeitsbranch: `feature/simplify-web-x-cms`
- Basis: `feature/web-x-block-cms`

Ausgangslage war ein technisch funktionierendes, aber fuer den Web-X zu komplexes Post-CMS mit
Block-Formsets, zu vielen Entscheidungen und sichtbaren internen Feldern.

## 2. Umgesetzter neuer Beitragsworkflow

Der vereinfachte Ablauf ist jetzt:

1. `Neuen Beitrag erstellen`
2. eines von drei Layouts waehlen
3. Datum, Titel, Kurzbeschreibung und Beitrag erfassen
4. Entwurf speichern, Vorschau pruefen, sofort veroeffentlichen oder planen

Versteckt oder automatisch verwaltet werden:

- Slug
- Autor
- SEO-Titel
- Meta-Beschreibung
- Layoutschluessel
- Pin-Prioritaet
- Revisionsgrund
- Review-Status in der normalen Beitragsoberflaeche

## 3. Drei aktive Layouts

Fuer neue Beitraege stehen genau drei Layouts zur Verfuegung:

- `Klassisch`
- `Fokus`
- `Magazin`

Jedes Layout hat:

- eine visuelle HTML/CSS-Karte
- eine klare Beschreibung
- dieselben vier Inhaltsfelder

## 4. Rich-Text und Sicherheit

Verwendeter Editor:

- lokal gehostetes Trix

Serverseitige Sanitization:

- `nh3`

Absicherung:

- erlaubte Tags und Attribute werden serverseitig gewhitelistet
- `script`, `iframe`, Event-Handler und `javascript:`-Links werden entfernt
- Vorschau-, Publish-, Restore- und Startseitenrechte bleiben serverseitig geprueft

## 5. Startseitenmarkierung

Die Beitragsliste erlaubt direktes Hervorheben fuer die Startseite.

Regeln:

- genau ein aktueller, sichtbarer Startseitenbeitrag
- optional ein zusaetzlicher zukuenftiger geplanter Startseitenbeitrag
- beim Wechsel werden alte Markierungen serverseitig entfernt
- Audit-Eintraege und Revisionen bleiben aktiv

## 6. Migration bestehender Beitraege

- neues Feld `Post.body_html`
- Migration `apps/content/migrations/0005_post_body_html.py`
- bisherige `review`-Beitraege werden sicher nach `draft` ueberfuehrt
- bestehende `PostBlock`-Inhalte werden nach `body_html` uebertragen
- Altbeitraege bleiben bearbeitbar und werden beim naechsten Speichern auf den vereinfachten Editor uebernommen

## 7. Verifikation

Verifiziert am 2026-07-18:

- `git diff --check`
- `uv run ruff check .`
- `uv run python manage.py check`
- `uv run python manage.py makemigrations --check`
- `uv run python manage.py migrate`
- `uv run pytest -q` -> `110 passed`
- `uv run coverage run -m pytest`
- `uv run coverage report` -> `69%`
- `uv run pytest tests/e2e -q` -> `5 passed`
- `uv run python manage.py check --deploy --settings=config.settings.production`

Verbleibende lokale Deploy-Warnungen:

- `security.W005`
- `security.W009`
- `security.W021`

## 8. Offene Einschraenkungen

- keine direkte Bildauswahl aus der Medienbibliothek innerhalb des neuen Post-Rich-Text-Editors
- Seiten, Veranstaltungen und Dokumente verwenden weiterhin eigene, fachlich detailliertere Editoren
- lokaler Acceptance-Stand nutzt SQLite, Zielarchitektur bleibt PostgreSQL
- produktiver Secret-Key und echte HSTS-Freigabe bleiben infra-abhaengig

## 9. Push- und PR-Status

- Push-Status: noch nicht ausgefuehrt in diesem Lauf
- PR-Status: Draft unter `docs/PR_WEB_X_CMS_DRAFT.md`
