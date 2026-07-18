from django.urls import path

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
