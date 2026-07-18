from django.urls import path

from .views import (
    CarouselCreateView,
    CarouselListView,
    CarouselPreviewView,
    CarouselRevisionListView,
    CarouselRevisionPreviewView,
    CarouselRevisionRestoreView,
    CarouselUpdateView,
    CmsDashboardView,
    HomepageEditView,
    HomepagePreviewView,
    HomepageRevisionListView,
    HomepageRevisionPreviewView,
    HomepageRevisionRestoreView,
    MediaAssetCreateView,
    MediaAssetListView,
    MediaAssetUpdateView,
    PostArchiveView,
    PostCreateView,
    PostListView,
    PostPreviewView,
    PostPublishView,
    PostRevisionListView,
    PostRevisionPreviewView,
    PostRevisionRestoreView,
    PostUpdateView,
    PostWithdrawView,
)

app_name = "cms"

urlpatterns = [
    path("", CmsDashboardView.as_view(), name="dashboard"),
    path("beitraege/", PostListView.as_view(), name="post_list"),
    path("beitraege/neu/", PostCreateView.as_view(), name="post_create"),
    path("beitraege/<int:pk>/bearbeiten/", PostUpdateView.as_view(), name="post_edit"),
    path("beitraege/<int:pk>/vorschau/", PostPreviewView.as_view(), name="post_preview"),
    path(
        "beitraege/<int:pk>/veroeffentlichen/",
        PostPublishView.as_view(),
        name="post_publish",
    ),
    path(
        "beitraege/<int:pk>/zurueckziehen/",
        PostWithdrawView.as_view(),
        name="post_withdraw",
    ),
    path(
        "beitraege/<int:pk>/archivieren/",
        PostArchiveView.as_view(),
        name="post_archive",
    ),
    path(
        "beitraege/<int:pk>/versionen/",
        PostRevisionListView.as_view(),
        name="post_revisions",
    ),
    path(
        "beitraege/<int:pk>/versionen/<int:revision_id>/vorschau/",
        PostRevisionPreviewView.as_view(),
        name="post_revision_preview",
    ),
    path(
        "beitraege/<int:pk>/versionen/<int:revision_id>/wiederherstellen/",
        PostRevisionRestoreView.as_view(),
        name="post_revision_restore",
    ),
    path("medien/", MediaAssetListView.as_view(), name="media_list"),
    path("medien/hochladen/", MediaAssetCreateView.as_view(), name="media_create"),
    path("medien/<int:pk>/bearbeiten/", MediaAssetUpdateView.as_view(), name="media_edit"),
    path("karussells/", CarouselListView.as_view(), name="carousel_list"),
    path("karussells/neu/", CarouselCreateView.as_view(), name="carousel_create"),
    path(
        "karussells/<int:pk>/bearbeiten/",
        CarouselUpdateView.as_view(),
        name="carousel_edit",
    ),
    path(
        "karussells/<int:pk>/vorschau/",
        CarouselPreviewView.as_view(),
        name="carousel_preview",
    ),
    path(
        "karussells/<int:pk>/versionen/",
        CarouselRevisionListView.as_view(),
        name="carousel_revisions",
    ),
    path(
        "karussells/<int:pk>/versionen/<int:revision_id>/vorschau/",
        CarouselRevisionPreviewView.as_view(),
        name="carousel_revision_preview",
    ),
    path(
        "karussells/<int:pk>/versionen/<int:revision_id>/wiederherstellen/",
        CarouselRevisionRestoreView.as_view(),
        name="carousel_revision_restore",
    ),
    path("startseite/", HomepageEditView.as_view(), name="homepage_edit"),
    path("startseite/vorschau/", HomepagePreviewView.as_view(), name="homepage_preview"),
    path(
        "startseite/versionen/",
        HomepageRevisionListView.as_view(),
        name="homepage_revisions",
    ),
    path(
        "startseite/versionen/<int:revision_id>/vorschau/",
        HomepageRevisionPreviewView.as_view(),
        name="homepage_revision_preview",
    ),
    path(
        "startseite/versionen/<int:revision_id>/wiederherstellen/",
        HomepageRevisionRestoreView.as_view(),
        name="homepage_revision_restore",
    ),
]
