from django.urls import path

from .views import (
    MemberProfileAdminListView,
    MemberProfileAdminUpdateView,
    OwnMemberProfileUpdateView,
    OwnMemberProfileView,
)

app_name = "members"

urlpatterns = [
    path("me/", OwnMemberProfileView.as_view(), name="me"),
    path("me/edit/", OwnMemberProfileUpdateView.as_view(), name="me_edit"),
    path("admin/", MemberProfileAdminListView.as_view(), name="admin_list"),
    path("admin/<int:pk>/edit/", MemberProfileAdminUpdateView.as_view(), name="admin_edit"),
]
