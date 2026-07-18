from django.urls import path
from django.views.generic import RedirectView

from .views import (
    AboutPageView,
    EventsPageView,
    HomePageView,
    ImprintPageView,
    JoinPageView,
    MembersPageView,
    NewsDetailView,
    NewsPageView,
    PrivacyPageView,
    healthcheck,
    intern_entry,
    legacy_calendar_feed,
    robots_txt,
    sitemap_xml,
)

app_name = "core"

urlpatterns = [
    path("health/", healthcheck, name="healthcheck"),
    path("robots.txt", robots_txt, name="robots_txt"),
    path("sitemap.xml", sitemap_xml, name="sitemap_xml"),
    path("kalender.ics", legacy_calendar_feed, name="calendar_ics"),
    path("index.html", RedirectView.as_view(pattern_name="core:home", permanent=True)),
    path("aktuelles.html", RedirectView.as_view(pattern_name="core:news", permanent=True)),
    path("anlaesse.html", RedirectView.as_view(pattern_name="core:events", permanent=True)),
    path("mitglieder.html", RedirectView.as_view(pattern_name="core:members", permanent=True)),
    path(
        "mitglied-werden.html",
        RedirectView.as_view(pattern_name="core:join", permanent=True),
    ),
    path("ueber-uns.html", RedirectView.as_view(pattern_name="core:about", permanent=True)),
    path("intern.html", RedirectView.as_view(pattern_name="core:intern", permanent=True)),
    path(
        "impressum.html",
        RedirectView.as_view(pattern_name="core:imprint", permanent=True),
    ),
    path(
        "datenschutz.html",
        RedirectView.as_view(pattern_name="core:privacy", permanent=True),
    ),
    path("", HomePageView.as_view(), name="home"),
    path("aktuelles/", NewsPageView.as_view(), name="news"),
    path("aktuelles/<slug:slug>/", NewsDetailView.as_view(), name="news_detail"),
    path("anlaesse/", EventsPageView.as_view(), name="events"),
    path("mitglieder/", MembersPageView.as_view(), name="members"),
    path("mitglied-werden/", JoinPageView.as_view(), name="join"),
    path("ueber-uns/", AboutPageView.as_view(), name="about"),
    path("intern/", intern_entry, name="intern"),
    path("impressum/", ImprintPageView.as_view(), name="imprint"),
    path("datenschutz/", PrivacyPageView.as_view(), name="privacy"),
]
