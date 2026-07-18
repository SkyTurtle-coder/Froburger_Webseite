from django.urls import path

from apps.events.views import (
    MemberEventDetailView,
    MemberEventFeedView,
    MemberEventIcsView,
    MemberEventListView,
)

from .views import (
    GeneralDocumentsView,
    MediaHubView,
    MemberDirectoryView,
    MemberProfileAdminListView,
    MemberProfileAdminUpdateView,
    MemberProfilePhotoView,
    OwnMemberProfileUpdateView,
    OwnMemberProfileView,
    SensitiveDocumentsView,
)

app_name = "members"

urlpatterns = [
    path("directory/", MemberDirectoryView.as_view(), name="directory"),
    path("events/", MemberEventListView.as_view(), name="events"),
    path("events/feed.ics", MemberEventFeedView.as_view(), name="calendar_ics"),
    path("events/<slug:slug>/", MemberEventDetailView.as_view(), name="event_detail"),
    path("events/<slug:slug>.ics", MemberEventIcsView.as_view(), name="event_ics"),
    path("media/", MediaHubView.as_view(), name="media"),
    path("profile-images/<int:pk>/", MemberProfilePhotoView.as_view(), name="profile_photo"),
    path("documents/", GeneralDocumentsView.as_view(), name="documents"),
    path(
        "documents/sensitive/",
        SensitiveDocumentsView.as_view(),
        name="documents_sensitive",
    ),
    path("me/", OwnMemberProfileView.as_view(), name="me"),
    path("me/edit/", OwnMemberProfileUpdateView.as_view(), name="me_edit"),
    path("admin/", MemberProfileAdminListView.as_view(), name="admin_list"),
    path("admin/<int:pk>/edit/", MemberProfileAdminUpdateView.as_view(), name="admin_edit"),
]
