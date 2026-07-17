import pytest
from django.urls import reverse


@pytest.mark.parametrize(
    ("url_name", "marker"),
    [
        ("core:home", "Verbunden in Tradition."),
        ("core:news", "Aktuelles"),
        ("core:events", "Kommende Termine"),
        ("core:members", "Komitee der Aktivitas"),
        ("core:join", "So kannst du uns erreichen"),
        ("core:about", "Eine Verbindung mit Geschichte und Zukunft"),
        ("core:imprint", "Kontaktangaben und rechtlicher Hinweis"),
        ("core:privacy", "Hinweise zu externen Diensten"),
    ],
)
@pytest.mark.django_db
def test_public_pages_are_accessible_without_login(client, url_name, marker):
    response = client.get(reverse(url_name))

    assert response.status_code == 200
    assert marker in response.content.decode()


@pytest.mark.django_db
def test_intern_entry_redirects_to_login_for_anonymous_users(client):
    response = client.get(reverse("core:intern"))

    assert response.status_code == 302
    assert response.headers["Location"] == "/accounts/login/?next=/accounts/"


@pytest.mark.parametrize(
    ("legacy_path", "canonical_path"),
    [
        ("/index.html", "/"),
        ("/aktuelles.html", "/aktuelles/"),
        ("/anlaesse.html", "/anlaesse/"),
        ("/mitglieder.html", "/mitglieder/"),
        ("/mitglied-werden.html", "/mitglied-werden/"),
        ("/ueber-uns.html", "/ueber-uns/"),
        ("/intern.html", "/intern/"),
        ("/impressum.html", "/impressum/"),
        ("/datenschutz.html", "/datenschutz/"),
    ],
)
@pytest.mark.django_db
def test_legacy_html_paths_redirect_permanently(client, legacy_path, canonical_path):
    response = client.get(legacy_path)

    assert response.status_code == 301
    assert response.headers["Location"] == canonical_path


@pytest.mark.django_db
def test_public_support_endpoints_are_available(client):
    robots = client.get(reverse("core:robots_txt"))
    sitemap = client.get(reverse("core:sitemap_xml"))
    calendar = client.get(reverse("core:calendar_ics"))

    assert robots.status_code == 200
    assert "Sitemap: http://testserver/sitemap.xml" in robots.content.decode()

    assert sitemap.status_code == 200
    sitemap_content = sitemap.content.decode()
    assert "<loc>http://testserver/aktuelles/</loc>" in sitemap_content
    assert "<loc>http://testserver/datenschutz/</loc>" in sitemap_content

    assert calendar.status_code == 200
    calendar_content = calendar.content.decode()
    assert "BEGIN:VCALENDAR" in calendar_content
    assert "PRODID:-//AV Froburger//Anlasskalender//DE" in calendar_content
