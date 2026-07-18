from __future__ import annotations

from pathlib import Path
from urllib.parse import urljoin

import pytest
from django.contrib.auth.models import Group
from django.core.management import call_command
from django.urls import reverse
from playwright.sync_api import Page, expect

from apps.accounts.models import User
from apps.content.constants import ALL_LAYOUT_PRESET_DEFINITIONS
from apps.content.models import LayoutPreset
from apps.members.models import PublicMemberProfile

pytestmark = pytest.mark.django_db(transaction=True)

PDF_BYTES = b"%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"


def configure_test_media(settings, tmp_path: Path):
    settings.PUBLIC_MEDIA_ROOT = tmp_path / "public-media"
    settings.PRIVATE_MEDIA_ROOT = tmp_path / "private-media"
    settings.MEDIA_ROOT = settings.PUBLIC_MEDIA_ROOT


def create_pdf_file(tmp_path: Path, filename: str) -> Path:
    target = tmp_path / filename
    target.write_bytes(PDF_BYTES)
    return target


def ensure_layout_presets():
    for definition in ALL_LAYOUT_PRESET_DEFINITIONS:
        LayoutPreset.objects.update_or_create(
            scope=definition.scope,
            key=definition.key,
            defaults={
                "name": definition.name,
                "description": definition.description,
                "preview_static_path": "",
                "allowed_block_types": list(definition.allowed_block_types),
                "required_fields": list(definition.required_fields),
                "max_images": definition.max_images,
                "is_active": True,
            },
        )


def login(page: Page, base_url: str, *, email: str, password: str):
    page.goto(urljoin(base_url, reverse("accounts:login")))
    page.get_by_label("E-Mail-Adresse").fill(email)
    page.get_by_label("Passwort").fill(password)
    page.get_by_role("button", name="Anmelden").click()
    expect(page).to_have_url(urljoin(base_url, reverse("accounts:home")))


def logout(page: Page, base_url: str):
    page.goto(urljoin(base_url, reverse("accounts:home")))
    page.get_by_role("button", name="Abmelden").click()
    expect(page).to_have_url(urljoin(base_url, reverse("accounts:login")))


def set_post_body_html(page: Page, html: str):
    page.locator('input[name="body_html"]').evaluate(
        """(element, value) => {
            element.value = value;
            element.dispatchEvent(new Event("input", { bubbles: true }));
            element.dispatchEvent(new Event("change", { bubbles: true }));
        }""",
        html,
    )


def test_simplified_post_browser_workflow(browser_page_factory, live_server):
    ensure_layout_presets()
    call_command("bootstrap_roles")
    web_x = User.objects.create_user(
        email="webx-posts@example.invalid",
        password="Secret1234!",
        first_name="Web",
        last_name="Posts",
    )
    web_x.groups.add(Group.objects.get(name="web_aktuar"))

    session = browser_page_factory("desktop")
    page = session.page
    try:
        login(page, live_server.url, email=web_x.email, password="Secret1234!")
        page.get_by_role("link", name="CMS oeffnen").click()
        expect(page).to_have_url(urljoin(live_server.url, reverse("cms:dashboard")))

        page.get_by_role("link", name="Neuen Beitrag erstellen").first.click()
        expect(
            page.get_by_role(
                "heading",
                name="Welche Art von Beitrag moechtest du erstellen?",
            )
        ).to_be_visible()

        page.locator('input[name="layout"][value="simple_classic"]').check(force=True)
        page.get_by_role("button", name="Weiter").click()
        expect(page).to_have_url(
            urljoin(live_server.url, reverse("cms:post_create") + "?layout=simple_classic")
        )

        page.locator('input[name="event_date"]').fill("2026-07-18")
        page.locator('input[name="title"]').fill("Browser CMS Beitrag")
        page.locator('textarea[name="teaser"]').fill(
            "Kurzbeschreibung fuer den vereinfachten Browser-Beitrag."
        )
        set_post_body_html(page, "<p>Erster Absatz</p><p>Zweiter Absatz</p>")
        page.locator('button[name="workflow_action"][value="save"]').click()
        expect(page.get_by_text("Entwurf gespeichert.")).to_be_visible()

        page.locator('button[name="workflow_action"][value="preview"]').click()
        expect(page.get_by_text("Diese Vorschau ist nur intern sichtbar.")).to_be_visible()
        expect(page.get_by_role("heading", name="Browser CMS Beitrag")).to_be_visible()
        page.go_back()

        page.locator('button[name="workflow_action"][value="publish"]').click()
        expect(page.get_by_text("Beitrag ist jetzt veroefentlicht.")).to_be_visible()

        page.get_by_role("link", name="Layout aendern").click()
        page.locator('input[name="layout_key"][value="simple_magazine"]').check(force=True)
        page.get_by_role("button", name="Layout uebernehmen").click()
        expect(page.get_by_text("Layout auf Magazin umgestellt.")).to_be_visible()

        page.get_by_role("link", name="Gespeicherte Vorschau").click()
        expect(page.locator(".post-layout-magazine")).to_be_visible()
        page.go_back()

        page.get_by_role("link", name="Zur Beitragsuebersicht").click()
        row = page.locator("article.cms-post-row", has_text="Browser CMS Beitrag")
        row.get_by_role("button", name="Diesen Beitrag auf der Startseite hervorheben").click()
        expect(
            page.get_by_text('"Browser CMS Beitrag" wird jetzt auf der Startseite hervorgehoben.')
        ).to_be_visible()

        page.goto(urljoin(live_server.url, reverse("core:home")))
        expect(page.get_by_role("heading", name="Aktuell auf der Startseite")).to_be_visible()
        expect(page.get_by_text("Browser CMS Beitrag")).to_be_visible()

        page.goto(
            urljoin(
                live_server.url,
                reverse("core:news_detail", args=["browser-cms-beitrag"]),
            )
        )
        expect(page.locator(".post-layout-magazine")).to_be_visible()
        expect(page.get_by_role("heading", name="Browser CMS Beitrag")).to_be_visible()
    finally:
        session.close()


def test_scheduled_post_and_cms_permissions_browser_workflow(browser_page_factory, live_server):
    ensure_layout_presets()
    call_command("bootstrap_roles")
    web_x = User.objects.create_user(
        email="webx-schedule@example.invalid",
        password="Secret1234!",
        first_name="Web",
        last_name="Schedule",
    )
    web_x.groups.add(Group.objects.get(name="web_aktuar"))
    member = User.objects.create_user(
        email="member-no-cms@example.invalid",
        password="Secret1234!",
        first_name="Normales",
        last_name="Mitglied",
    )
    member.groups.add(Group.objects.get(name="member"))

    session = browser_page_factory("mobile")
    page = session.page
    try:
        login(page, live_server.url, email=web_x.email, password="Secret1234!")
        page.get_by_role("link", name="CMS oeffnen").click()
        page.get_by_role("link", name="Neuen Beitrag erstellen").first.click()
        page.locator('input[name="layout"][value="simple_focus"]').check(force=True)
        page.get_by_role("button", name="Weiter").click()

        page.locator('input[name="event_date"]').fill("2026-08-20")
        page.locator('input[name="title"]').fill("Geplanter Browser Beitrag")
        page.locator('textarea[name="teaser"]').fill("Diese Meldung soll spaeter erscheinen.")
        set_post_body_html(page, "<p>Der Beitrag ist vorbereitet.</p>")
        page.get_by_text("Veroeffentlichung planen").first.click()
        page.locator('input[name="schedule_date"]').fill("2026-08-20")
        page.locator('input[name="schedule_time"]').fill("18:00")
        page.locator('button[name="workflow_action"][value="schedule"]').click()
        expect(page.get_by_text("Beitrag ist geplant fuer 20.08.2026 18:00 Uhr.")).to_be_visible()

        page.get_by_role("link", name="Zur Beitragsuebersicht").click()
        row = page.locator("article.cms-post-row", has_text="Geplanter Browser Beitrag")
        expect(row.get_by_text("Geplant", exact=True)).to_be_visible()

        page.goto(urljoin(live_server.url, reverse("core:news")))
        expect(page.get_by_role("link", name="Geplanter Browser Beitrag")).to_have_count(0)

        logout(page, live_server.url)
        login(page, live_server.url, email=member.email, password="Secret1234!")
        expect(page.get_by_role("link", name="CMS oeffnen")).to_have_count(0)
        response = session.context.request.get(urljoin(live_server.url, reverse("cms:dashboard")))
        assert response is not None
        assert response.status == 403
    finally:
        session.close()


def test_event_browser_workflow(browser_page_factory, live_server):
    call_command("bootstrap_roles")
    web_x = User.objects.create_user(
        email="webx-events@example.invalid",
        password="Secret1234!",
        first_name="Web",
        last_name="X",
    )
    web_x.groups.add(Group.objects.get(name="web_aktuar"))
    member = User.objects.create_user(
        email="member-events@example.invalid",
        password="Secret1234!",
        first_name="Member",
        last_name="Portal",
    )

    session = browser_page_factory("desktop")
    page = session.page
    try:
        login(page, live_server.url, email=web_x.email, password="Secret1234!")
        expect(page.get_by_text("Web-X CMS")).to_be_visible()
        page.get_by_role("link", name="CMS oeffnen").click()
        expect(page).to_have_url(urljoin(live_server.url, reverse("cms:dashboard")))

        page.get_by_role("link", name="Veranstaltungen", exact=True).click()
        page.get_by_role("link", name="Neue Veranstaltung").click()
        expect(page).to_have_url(urljoin(live_server.url, reverse("cms:event_create")))

        page.locator('input[name="title"]').fill("Browser Anlass")
        page.locator('input[name="slug"]').fill("browser-anlass")
        page.locator('textarea[name="short_description"]').fill("Im Browser erstellter Anlass")
        page.locator('textarea[name="description"]').fill(
            "Detailbeschreibung fuer den Browser Anlass."
        )
        page.locator('input[name="start_at"]').fill("2026-10-10T19:00")
        page.locator('input[name="end_at"]').fill("2026-10-10T22:00")
        page.locator('input[name="timezone_name"]').fill("Europe/Zurich")
        page.locator('input[name="location_name"]').fill("Basel")
        page.locator('textarea[name="location_address"]').fill("Petersplatz 1\n4051 Basel")
        page.locator('select[name="visibility"]').select_option("public")
        page.locator('button[name="workflow_action"][value="save"]').click()
        expect(
            page.get_by_text("Veranstaltung gespeichert. Revision 1 wurde erstellt.")
        ).to_be_visible()

        page.get_by_role("link", name="Gespeicherte Vorschau").click()
        expect(page.get_by_text("Diese Vorschau ist nur intern sichtbar.")).to_be_visible()
        expect(page.get_by_role("heading", name="Browser Anlass")).to_be_visible()
        page.go_back()

        page.locator('button[name="workflow_action"][value="publish"]').click()
        expect(
            page.get_by_text("Veranstaltung gespeichert. Revision 2 wurde erstellt.")
        ).to_be_visible()

        page.goto(urljoin(live_server.url, reverse("cms:event_create")))
        page.locator('input[name="title"]').fill("Interner Browser Anlass")
        page.locator('input[name="slug"]').fill("interner-browser-anlass")
        page.locator('textarea[name="short_description"]').fill("Nur fuer Mitglieder")
        page.locator('textarea[name="description"]').fill("Interne Veranstaltungsdetails.")
        page.locator('input[name="start_at"]').fill("2026-10-12T20:00")
        page.locator('input[name="end_at"]').fill("2026-10-12T22:30")
        page.locator('input[name="timezone_name"]').fill("Europe/Zurich")
        page.locator('input[name="location_name"]').fill("Basel")
        page.locator('textarea[name="location_address"]').fill("Muensterplatz 1\n4051 Basel")
        page.locator('select[name="visibility"]').select_option("members")
        page.locator('button[name="workflow_action"][value="publish"]').click()
        expect(
            page.get_by_text("Veranstaltung gespeichert. Revision 1 wurde erstellt.")
        ).to_be_visible()

        logout(page, live_server.url)
        page.goto(urljoin(live_server.url, reverse("core:events")))
        expect(page.get_by_role("link", name="Browser Anlass")).to_be_visible()
        expect(page.get_by_role("link", name="Interner Browser Anlass")).to_have_count(0)
        page.get_by_role("link", name="Browser Anlass").click()
        with page.expect_download() as download_info:
            page.get_by_role("link", name="ICS-Datei").click()
        assert download_info.value.suggested_filename == "browser-anlass.ics"

        login(page, live_server.url, email=member.email, password="Secret1234!")
        page.goto(urljoin(live_server.url, reverse("members:events")))
        expect(page.get_by_role("heading", name="Interner Browser Anlass")).to_be_visible()
    finally:
        session.close()


def test_document_browser_workflow(browser_page_factory, live_server, settings, tmp_path):
    configure_test_media(settings, tmp_path)
    call_command("bootstrap_roles")
    web_x = User.objects.create_user(
        email="webx-docs@example.invalid",
        password="Secret1234!",
        first_name="Web",
        last_name="Docs",
    )
    web_x.groups.add(Group.objects.get(name="web_aktuar"))
    chargia = Group.objects.create(name="Chargia")
    allowed_user = User.objects.create_user(
        email="allowed-docs@example.invalid",
        password="Secret1234!",
        first_name="Allowed",
        last_name="Docs",
    )
    allowed_user.groups.add(chargia)
    denied_user = User.objects.create_user(
        email="denied-docs@example.invalid",
        password="Secret1234!",
        first_name="Denied",
        last_name="Docs",
    )
    pdf_path = create_pdf_file(tmp_path, "chargenunterlage.pdf")

    session = browser_page_factory("mobile")
    page = session.page
    try:
        login(page, live_server.url, email=web_x.email, password="Secret1234!")
        page.goto(urljoin(live_server.url, reverse("cms:document_create")))
        page.locator('input[name="title"]').fill("Chargenunterlage")
        page.locator('textarea[name="description"]').fill(
            "Nur fuer die Chargia bestimmte Unterlage."
        )
        page.locator('select[name="visibility"]').select_option("selected_groups")
        page.get_by_label("Chargia").check()
        page.locator('input[name="file_upload"]').set_input_files(str(pdf_path))
        page.locator('button[name="workflow_action"][value="publish"]').click()
        expect(page.get_by_text("Dokument gespeichert und veroeffentlicht.")).to_be_visible()

        logout(page, live_server.url)
        login(page, live_server.url, email=allowed_user.email, password="Secret1234!")
        page.goto(urljoin(live_server.url, reverse("members:documents")))
        expect(page.get_by_role("heading", name="Chargenunterlage")).to_be_visible()
        download_href = page.get_by_role("link", name="Herunterladen").first.get_attribute(
            "href"
        )
        assert download_href is not None
        with page.expect_download() as download_info:
            page.get_by_role("link", name="Herunterladen").click()
        assert download_info.value.suggested_filename == "chargenunterlage.pdf"

        logout(page, live_server.url)
        login(page, live_server.url, email=denied_user.email, password="Secret1234!")
        page.goto(urljoin(live_server.url, reverse("members:documents")))
        expect(page.get_by_role("heading", name="Chargenunterlage")).to_have_count(0)
        response = session.context.request.get(urljoin(live_server.url, download_href))
        assert response.status == 404
    finally:
        session.close()


def test_public_members_page_browser_workflow(browser_page_factory, live_server):
    ensure_layout_presets()
    call_command("bootstrap_roles")
    call_command("import_existing_members_page")
    web_x = User.objects.create_user(
        email="webx-members@example.invalid",
        password="Secret1234!",
        first_name="Web",
        last_name="Members",
    )
    web_x.groups.add(Group.objects.get(name="web_aktuar"))
    private_user = User.objects.create_user(
        email="private-member@example.invalid",
        password="Secret1234!",
        first_name="Private",
        last_name="Member",
    )
    private_user.member_profile.phone_number = "+41 79 555 44 33"
    private_user.member_profile.save(update_fields=["phone_number", "updated_at"])
    assert PublicMemberProfile.objects.public().count() > 0

    session = browser_page_factory("tablet")
    page = session.page
    try:
        login(page, live_server.url, email=web_x.email, password="Secret1234!")
        page.goto(urljoin(live_server.url, reverse("cms:page_list")))
        members_card = page.locator("article", has_text="Mitglieder").first
        members_card.get_by_role("link", name="Bearbeiten").click()
        page.locator('input[name="title"]').fill("Mitglieder Oeffentlich")
        page.locator('button[name="workflow_action"][value="save"]').click()
        expect(page.get_by_text("Seite gespeichert. Revision")).to_be_visible()
        page.get_by_role("link", name="Gespeicherte Vorschau").click()
        expect(page.get_by_text("Diese Vorschau ist nur intern sichtbar.")).to_be_visible()
        expect(page.get_by_role("heading", name="Mitglieder Oeffentlich")).to_be_visible()
        page.go_back()
        page.locator('button[name="workflow_action"][value="publish"]').click()
        expect(page.get_by_text("Seite gespeichert. Revision")).to_be_visible()

        page.goto(urljoin(live_server.url, reverse("core:members")))
        expect(page.get_by_role("heading", name="Mitglieder Oeffentlich")).to_be_visible()
        expect(page.get_by_text("Bacchus")).to_be_visible()
        expect(page.get_by_text("Parzival")).to_be_visible()
        expect(page.get_by_text("private-member@example.invalid")).to_have_count(0)
        expect(page.get_by_text("+41 79 555 44 33")).to_have_count(0)
    finally:
        session.close()
