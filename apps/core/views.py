from dataclasses import dataclass
from pathlib import Path

from django.http import HttpResponse, HttpResponseRedirect, JsonResponse
from django.template.response import TemplateResponse
from django.templatetags.static import static
from django.urls import reverse
from django.views.generic import TemplateView


@dataclass(frozen=True)
class PublicPageDefinition:
    slug: str
    template_name: str
    title: str
    description: str
    og_image_path: str | None = None
    og_image_alt: str = ""
    noindex: bool = False
    body_id: str = ""
    preload_image_path: str | None = None
    show_lock_link: bool = True


PUBLIC_PAGES = {
    "home": PublicPageDefinition(
        slug="home",
        template_name="public/pages/home.html",
        title="AV Froburger - Akademische Verbindung Uni Basel",
        description=(
            "AV Froburger: akademische Verbindung an der Universität Basel mit "
            "Tradition, Freundschaft und aktuellem Verbindungsleben."
        ),
        og_image_path="Bilder/DSC02521-hero.jpg",
        og_image_alt="AV Froburger vor historischer Kulisse",
        preload_image_path="Bilder/DSC02521-hero.jpg",
    ),
    "news": PublicPageDefinition(
        slug="news",
        template_name="public/pages/news.html",
        title="Aktuelles - AV Froburger",
        description=(
            "Berichte, Rückblicke und Neuigkeiten aus dem Verbindungsleben "
            "der AV Froburger."
        ),
        og_image_path="Bilder/DSC01621-web.jpg",
        og_image_alt="Wanderwochenende der AV Froburger im Wallis",
    ),
    "events": PublicPageDefinition(
        slug="events",
        template_name="public/pages/events.html",
        title="Anlässe - AV Froburger",
        description=(
            "Semesterprogramm der AV Froburger sowie regelmässige "
            "Alt-Froburger-Stämme in Basel, Bern und Luzern."
        ),
        og_image_path="Bilder/DSC02521-hero.jpg",
        og_image_alt="Semesterprogramm der AV Froburger",
    ),
    "members": PublicPageDefinition(
        slug="members",
        template_name="public/pages/members.html",
        title="Mitglieder - AV Froburger",
        description=(
            "Komitee, Salon und Fuxenstall der AV Froburger sowie das "
            "Komitee der Alt-Froburger."
        ),
        og_image_path="Bilder/DSC01282-web.jpg",
        og_image_alt="Mitglieder der AV Froburger in Couleur",
    ),
    "join": PublicPageDefinition(
        slug="join",
        template_name="public/pages/join.html",
        title="Mitglied werden - AV Froburger",
        description=(
            "Mitglied werden bei der AV Froburger: unkompliziert Kontakt "
            "aufnehmen per Mail, Instagram oder WhatsApp-Kontaktanfrage."
        ),
        og_image_path="Bilder/DSC02521-hero.jpg",
        og_image_alt="Mitglied werden bei der AV Froburger",
    ),
    "about": PublicPageDefinition(
        slug="about",
        template_name="public/pages/about.html",
        title="Über uns - AV Froburger",
        description=(
            "Geschichte, Couleur und Hochschulumfeld der AV Froburger an der "
            "Universität Basel und der FHNW."
        ),
        og_image_path="Bilder/Schild.svg",
        og_image_alt="Wappen der AV Froburger",
        body_id="top",
    ),
    "imprint": PublicPageDefinition(
        slug="imprint",
        template_name="public/pages/imprint.html",
        title="Impressum - AV Froburger",
        description="Kontakt- und Impressumsangaben der AV Froburger.",
    ),
    "privacy": PublicPageDefinition(
        slug="privacy",
        template_name="public/pages/privacy.html",
        title="Datenschutz - AV Froburger",
        description=(
            "Hinweise zum Datenschutz und zu externen Diensten auf der "
            "Website der AV Froburger."
        ),
    ),
}


class PublicPageView(TemplateView):
    page: PublicPageDefinition

    def get_template_names(self):
        return [self.page.template_name]

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        request = self.request
        page = self.page
        og_image_url = ""
        if page.og_image_path:
            og_image_url = request.build_absolute_uri(static(page.og_image_path))

        context.update(
            body_id=page.body_id,
            canonical_url=request.build_absolute_uri(request.path),
            meta_description=page.description,
            meta_title=page.title,
            noindex=page.noindex,
            og_image_alt=page.og_image_alt,
            og_image_url=og_image_url,
            page_slug=page.slug,
            preload_image_path=page.preload_image_path,
            show_lock_link=page.show_lock_link,
        )

        if page.slug == "events":
            context["calendar_webcal_url"] = f"webcal://{request.get_host()}{reverse('core:calendar_ics')}"

        return context


class HomePageView(PublicPageView):
    page = PUBLIC_PAGES["home"]


class NewsPageView(PublicPageView):
    page = PUBLIC_PAGES["news"]


class EventsPageView(PublicPageView):
    page = PUBLIC_PAGES["events"]


class MembersPageView(PublicPageView):
    page = PUBLIC_PAGES["members"]


class JoinPageView(PublicPageView):
    page = PUBLIC_PAGES["join"]


class AboutPageView(PublicPageView):
    page = PUBLIC_PAGES["about"]


class ImprintPageView(PublicPageView):
    page = PUBLIC_PAGES["imprint"]


class PrivacyPageView(PublicPageView):
    page = PUBLIC_PAGES["privacy"]


def healthcheck(request):
    return JsonResponse({"status": "ok"})


def legacy_calendar_feed(request):
    calendar_path = Path(__file__).resolve().parent / "data" / "kalender.ics"
    return HttpResponse(
        calendar_path.read_text(encoding="utf-8"),
        content_type="text/calendar; charset=utf-8",
    )


def robots_txt(request):
    return TemplateResponse(
        request,
        "public/robots.txt",
        {
            "sitemap_url": request.build_absolute_uri(reverse("core:sitemap_xml")),
        },
        content_type="text/plain; charset=utf-8",
    )


def sitemap_xml(request):
    url_names = [
        "core:home",
        "core:news",
        "core:events",
        "core:members",
        "core:join",
        "core:about",
        "core:imprint",
        "core:privacy",
    ]
    urls = [request.build_absolute_uri(reverse(url_name)) for url_name in url_names]
    return TemplateResponse(
        request,
        "public/sitemap.xml",
        {"urls": urls},
        content_type="application/xml; charset=utf-8",
    )


def intern_entry(request):
    if request.user.is_authenticated:
        return HttpResponseRedirect(reverse("accounts:home"))

    login_url = reverse("accounts:login")
    return HttpResponseRedirect(f"{login_url}?next={reverse('accounts:home')}")
