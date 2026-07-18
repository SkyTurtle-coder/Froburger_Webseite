import pytest
from django.urls import reverse
from django.utils import timezone

from apps.content.models import LayoutPreset, Post, PublishableStatus, Visibility
from apps.media_library.models import MediaAsset


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


@pytest.fixture
def published_post(settings, tmp_path, user_factory, image_upload_factory):
    settings.MEDIA_ROOT = tmp_path / "test-media"
    author = user_factory()
    hero = MediaAsset(
        title="Hero",
        file=image_upload_factory(filename="hero.png"),
        alt_text="Titelbild",
        uploaded_by=author,
        status=MediaAsset.PublicationStatus.PUBLISHED,
        visibility=MediaAsset.Visibility.PUBLIC,
    )
    hero.save()
    post_layout = LayoutPreset.objects.get(scope=LayoutPreset.Scope.POST, key="standard_article")
    return Post.objects.create(
        title="Oeffentlicher CMS-Beitrag",
        slug="oeffentlicher-cms-beitrag",
        teaser="Dieser Beitrag kommt aus dem Web-X CMS.",
        layout_preset=post_layout,
        hero_image=hero,
        status=PublishableStatus.PUBLISHED,
        visibility=Visibility.PUBLIC,
        published_at=timezone.now(),
        created_by=author,
        last_edited_by=author,
    )


@pytest.mark.django_db
def test_news_list_and_detail_render_published_cms_posts(client, published_post):
    list_response = client.get(reverse("core:news"))
    detail_response = client.get(reverse("core:news_detail", args=[published_post.slug]))

    assert list_response.status_code == 200
    assert published_post.title in list_response.content.decode()
    assert detail_response.status_code == 200
    assert published_post.teaser in detail_response.content.decode()


@pytest.mark.django_db
def test_news_detail_returns_404_for_unknown_slug(client):
    response = client.get(reverse("core:news_detail", args=["unbekannt"]))

    assert response.status_code == 404


@pytest.mark.django_db
def test_homepage_falls_back_without_cms_page(client):
    response = client.get(reverse("core:home"))
    content = response.content.decode()

    assert response.status_code == 200
    assert "Verbunden in Tradition." in content
    assert "Spargelfahrt 2026" in content


@pytest.mark.django_db
def test_sitemap_includes_public_post_detail_urls(client, published_post):
    response = client.get(reverse("core:sitemap_xml"))
    content = response.content.decode()

    assert response.status_code == 200
    assert f"<loc>http://testserver/aktuelles/{published_post.slug}/</loc>" in content
