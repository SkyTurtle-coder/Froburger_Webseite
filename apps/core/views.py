from dataclasses import dataclass
from pathlib import Path

from django.conf import settings
from django.http import HttpResponse, HttpResponseRedirect, JsonResponse
from django.shortcuts import get_object_or_404
from django.template.response import TemplateResponse
from django.templatetags.static import static
from django.urls import reverse
from django.utils import timezone
from django.views.generic import TemplateView

from apps.content.models import Page, Post, Visibility
from apps.members.models import PublicMemberProfile


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


def _published_posts_for_request(user):
    queryset = Post.objects.published().select_related("hero_image", "og_image", "layout_preset")
    if user and getattr(user, "is_authenticated", False):
        return queryset.filter(visibility__in=[Visibility.PUBLIC, Visibility.MEMBERS])
    return queryset.filter(visibility=Visibility.PUBLIC)


def _homepage_page_for_request(user):
    queryset = Page.objects.published().filter(page_key="homepage").select_related(
        "og_image", "layout_preset"
    )
    queryset = queryset.prefetch_related("sections__image", "sections__carousel__items__image")
    if user and getattr(user, "is_authenticated", False):
        return queryset.filter(visibility__in=[Visibility.PUBLIC, Visibility.MEMBERS]).first()
    return queryset.filter(visibility=Visibility.PUBLIC).first()


def _editorial_page_for_request(user, page_key: str):
    queryset = (
        Page.objects.published()
        .filter(page_key=page_key)
        .select_related("og_image", "layout_preset")
        .prefetch_related("sections__image", "sections__carousel__items__image")
    )
    if user and getattr(user, "is_authenticated", False):
        return queryset.filter(visibility__in=[Visibility.PUBLIC, Visibility.MEMBERS]).first()
    return queryset.filter(visibility=Visibility.PUBLIC).first()


def _page_sections(page):
    if not page:
        return []
    sections = getattr(page, "preview_sections", None)
    if sections is None:
        sections = page.sections.order_by("position", "pk")
    rendered_sections = [section for section in sections if getattr(section, "is_active", True)]
    return _attach_people_list_context(rendered_sections)


def _attach_people_list_context(sections):
    group_keys = {
        section.options.get("group_key", "")
        for section in sections
        if getattr(section, "block_type", "") == "people_list"
    }
    people_by_group = {
        group_key: list(PublicMemberProfile.objects.public().filter(group_key=group_key))
        for group_key in group_keys
        if group_key
    }
    people_variants = {
        PublicMemberProfile.GroupKey.AKTIVITAS_COMMITTEE: {
            "variant": "committee",
            "grid_class": "aktivitas-committee-grid",
            "card_class": "role-card",
        },
        PublicMemberProfile.GroupKey.SALON: {
            "variant": "members",
            "grid_class": "members-grid",
            "card_class": "member-card salon",
        },
        PublicMemberProfile.GroupKey.FUXENSTALL: {
            "variant": "members",
            "grid_class": "members-grid",
            "card_class": "member-card fux",
        },
        PublicMemberProfile.GroupKey.ALTHERRN_COMMITTEE: {
            "variant": "committee-dark",
            "grid_class": "committee-grid",
            "card_class": "committee-card",
        },
    }
    for section in sections:
        if getattr(section, "block_type", "") != "people_list":
            continue
        group_key = section.options.get("group_key", "")
        variant = people_variants.get(
            group_key,
            {"variant": "members", "grid_class": "members-grid", "card_class": "member-card"},
        )
        section.public_people = people_by_group.get(group_key, [])
        section.people_group_key = group_key
        section.people_variant = variant["variant"]
        section.people_grid_class = variant["grid_class"]
        section.people_card_class = variant["card_class"]
        section.people_count = len(section.public_people)
    return sections


def _post_blocks(post):
    blocks = getattr(post, "preview_blocks", None)
    if blocks is None:
        blocks = post.blocks.order_by("position", "pk")
    return [block for block in blocks if getattr(block, "is_active", True)]


def _carousel_items(carousel, *, preview_mode=False):
    if not carousel:
        return []
    items = getattr(carousel, "preview_items", None)
    if items is None:
        items = carousel.items.order_by("position", "pk")
    now = timezone.now()
    rendered_items = []
    for item in items:
        image = getattr(item, "image", None)
        if not getattr(item, "is_active", True) or not image:
            continue
        if not preview_mode:
            if item.starts_at and item.starts_at > now:
                continue
            if item.ends_at and item.ends_at < now:
                continue
            if image.status != image.PublicationStatus.PUBLISHED:
                continue
            if image.visibility != image.Visibility.PUBLIC:
                continue
        rendered_items.append(item)
    return rendered_items


def _media_absolute_url(request, asset, *, fallback_static_path=""):
    if asset and getattr(asset, "file", None):
        try:
            return request.build_absolute_uri(asset.file.url)
        except ValueError:
            pass
    if fallback_static_path:
        return request.build_absolute_uri(static(fallback_static_path))
    return ""


def build_homepage_render_context(
    *,
    request,
    preview_page=None,
    preview_mode=False,
    preview_title="",
):
    page_definition = PUBLIC_PAGES["home"]
    page = preview_page or _homepage_page_for_request(request.user)
    sections = _page_sections(page)
    sections_by_anchor = {section.anchor_id: section for section in sections if section.anchor_id}
    carousel_section = sections_by_anchor.get("homepage-carousel")
    if carousel_section is None:
        carousel_section = next(
            (
                section
                for section in sections
                if getattr(section, "block_type", "") == "carousel" and section.carousel_id
            ),
            None,
        )
    homepage_carousel_items = _carousel_items(
        getattr(carousel_section, "carousel", None),
        preview_mode=preview_mode,
    )
    pinned_limit = getattr(settings, "CMS_HOMEPAGE_PIN_LIMIT", 3)
    pinned_posts = list(
        Post.objects.public()
        .select_related("hero_image")
        .filter(is_homepage_pinned=True)
        .order_by("-pin_priority", "-published_at", "-created_at")[:pinned_limit]
    )
    og_image_url = _media_absolute_url(
        request,
        getattr(page, "og_image", None),
        fallback_static_path=page_definition.og_image_path or "",
    )
    return {
        "body_id": page_definition.body_id,
        "canonical_url": request.build_absolute_uri(request.path),
        "homepage_page": page,
        "homepage_sections": sections,
        "homepage_copy_section": sections_by_anchor.get("homepage-copy"),
        "homepage_recap_section": sections_by_anchor.get("homepage-recap"),
        "homepage_carousel_section": carousel_section,
        "homepage_carousel_items": homepage_carousel_items,
        "homepage_pinned_posts": pinned_posts,
        "meta_description": (
            page.meta_description if page and page.meta_description else page_definition.description
        ),
        "meta_title": page.meta_title if page and page.meta_title else page_definition.title,
        "noindex": preview_mode,
        "og_image_alt": page_definition.og_image_alt,
        "og_image_url": og_image_url,
        "page_slug": page_definition.slug,
        "preload_image_path": page_definition.preload_image_path,
        "preview_mode": preview_mode,
        "preview_title": preview_title,
        "show_lock_link": False if preview_mode else page_definition.show_lock_link,
    }


def build_news_listing_context(*, request, posts=None, preview_mode=False):
    page_definition = PUBLIC_PAGES["news"]
    news_posts = list(
        posts
        if posts is not None
        else _published_posts_for_request(request.user).order_by("-published_at", "-created_at")
    )
    return {
        "canonical_url": request.build_absolute_uri(request.path),
        "meta_description": page_definition.description,
        "meta_title": page_definition.title,
        "news_posts": news_posts,
        "noindex": preview_mode,
        "og_image_alt": page_definition.og_image_alt,
        "og_image_url": request.build_absolute_uri(static(page_definition.og_image_path)),
        "page_slug": page_definition.slug,
        "preview_mode": preview_mode,
        "show_lock_link": False if preview_mode else page_definition.show_lock_link,
    }


def build_news_detail_render_context(
    *,
    request,
    post,
    preview_mode=False,
    preview_title="",
):
    page_definition = PUBLIC_PAGES["news"]
    blocks = _post_blocks(post)
    og_image_url = _media_absolute_url(
        request,
        getattr(post, "og_image", None) or getattr(post, "hero_image", None),
        fallback_static_path=page_definition.og_image_path or "",
    )
    return {
        "canonical_url": request.build_absolute_uri(request.path)
        if preview_mode
        else request.build_absolute_uri(reverse("core:news_detail", kwargs={"slug": post.slug})),
        "meta_description": post.meta_description or post.teaser,
        "meta_title": post.meta_title or f"{post.title} - AV Froburger",
        "news_index_url": reverse("core:news"),
        "noindex": preview_mode,
        "og_image_alt": getattr(getattr(post, "hero_image", None), "alt_text", "")
        or page_definition.og_image_alt,
        "og_image_url": og_image_url,
        "page_slug": page_definition.slug,
        "post": post,
        "post_blocks": blocks,
        "preview_mode": preview_mode,
        "preview_title": preview_title,
        "show_lock_link": False if preview_mode else page_definition.show_lock_link,
    }


def build_editorial_page_render_context(
    *,
    request,
    page_key="",
    preview_page=None,
    preview_mode=False,
    preview_title="",
):
    page = preview_page or _editorial_page_for_request(request.user, page_key)
    resolved_key = page_key or getattr(page, "page_key", "")
    page_definition = PUBLIC_PAGES.get(
        resolved_key,
        PublicPageDefinition(
            slug=resolved_key or getattr(page, "slug", "seite"),
            template_name="public/pages/editorial_page.html",
            title=(
                (page.meta_title if page and page.meta_title else page.title)
                if page
                else "Seite"
            ),
            description=(
                (page.meta_description if page and page.meta_description else "")
                if page
                else ""
            ),
        ),
    )
    sections = _page_sections(page)
    og_image_url = _media_absolute_url(
        request,
        getattr(page, "og_image", None),
        fallback_static_path=page_definition.og_image_path or "",
    )
    return {
        "canonical_url": request.build_absolute_uri(request.path),
        "cms_page": page,
        "cms_page_definition": page_definition,
        "meta_description": (
            page.meta_description if page and page.meta_description else page_definition.description
        ),
        "meta_title": page.meta_title if page and page.meta_title else page_definition.title,
        "noindex": preview_mode,
        "og_image_alt": page_definition.og_image_alt,
        "og_image_url": og_image_url,
        "page_sections": sections,
        "page_sections_by_anchor": {
            section.anchor_id: section for section in sections if getattr(section, "anchor_id", "")
        },
        "page_slug": page_definition.slug,
        "preview_mode": preview_mode,
        "preview_title": preview_title,
        "show_lock_link": False if preview_mode else page_definition.show_lock_link,
    }


class HomePageView(PublicPageView):
    page = PUBLIC_PAGES["home"]

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        context.update(build_homepage_render_context(request=self.request))
        return context


class NewsPageView(PublicPageView):
    page = PUBLIC_PAGES["news"]

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        context.update(build_news_listing_context(request=self.request))
        return context


class NewsDetailView(TemplateView):
    template_name = "public/pages/news_detail.html"

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        post = get_object_or_404(
            _published_posts_for_request(self.request.user).prefetch_related(
                "blocks__image",
                "blocks__carousel__items__image",
            ),
            slug=self.kwargs["slug"],
        )
        context.update(build_news_detail_render_context(request=self.request, post=post))
        return context


class EventsPageView(PublicPageView):
    page = PUBLIC_PAGES["events"]


class MembersPageView(PublicPageView):
    page = PUBLIC_PAGES["members"]

    def get_template_names(self):
        if _editorial_page_for_request(self.request.user, "members"):
            return ["public/pages/editorial_page.html"]
        return super().get_template_names()

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        cms_page = _editorial_page_for_request(self.request.user, "members")
        if cms_page:
            context.update(
                build_editorial_page_render_context(
                    request=self.request,
                    page_key="members",
                )
            )
        return context


class JoinPageView(PublicPageView):
    page = PUBLIC_PAGES["join"]

    def get_template_names(self):
        if _editorial_page_for_request(self.request.user, "join"):
            return ["public/pages/editorial_page.html"]
        return super().get_template_names()

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        cms_page = _editorial_page_for_request(self.request.user, "join")
        if cms_page:
            context.update(
                build_editorial_page_render_context(
                    request=self.request,
                    page_key="join",
                )
            )
        return context


class AboutPageView(PublicPageView):
    page = PUBLIC_PAGES["about"]

    def get_template_names(self):
        if _editorial_page_for_request(self.request.user, "about"):
            return ["public/pages/editorial_page.html"]
        return super().get_template_names()

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        cms_page = _editorial_page_for_request(self.request.user, "about")
        if cms_page:
            context.update(
                build_editorial_page_render_context(
                    request=self.request,
                    page_key="about",
                )
            )
        return context


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
    urls.extend(
        request.build_absolute_uri(reverse("core:news_detail", kwargs={"slug": slug}))
        for slug in Post.objects.public().values_list("slug", flat=True)
    )
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
