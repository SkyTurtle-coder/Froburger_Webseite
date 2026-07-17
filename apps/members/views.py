from django.contrib import messages
from django.contrib.auth.mixins import LoginRequiredMixin, PermissionRequiredMixin
from django.urls import reverse_lazy
from django.views.generic import ListView, TemplateView, UpdateView

from .forms import MemberProfileAdminForm, MemberProfileSelfForm
from .models import MemberProfile


def get_or_create_member_profile(user):
    defaults = {
        "membership_status": (
            MemberProfile.MembershipStatus.ACTIVE
            if user.is_active
            else MemberProfile.MembershipStatus.INVITED
        )
    }
    profile, _ = MemberProfile.objects.get_or_create(user=user, defaults=defaults)
    return profile


class OwnMemberProfileView(LoginRequiredMixin, TemplateView):
    template_name = "members/me_detail.html"

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        context["profile"] = get_or_create_member_profile(self.request.user)
        return context


class OwnMemberProfileUpdateView(LoginRequiredMixin, UpdateView):
    form_class = MemberProfileSelfForm
    template_name = "members/me_form.html"
    success_url = reverse_lazy("members:me")

    def get_object(self, queryset=None):
        return get_or_create_member_profile(self.request.user)

    def form_valid(self, form):
        response = super().form_valid(form)
        messages.success(self.request, "Ihr Profil wurde aktualisiert.")
        return response


class MemberProfileAdminListView(LoginRequiredMixin, PermissionRequiredMixin, ListView):
    permission_required = "members.view_memberprofile"
    raise_exception = True
    template_name = "members/admin_list.html"
    context_object_name = "profiles"

    def get_queryset(self):
        return MemberProfile.objects.select_related("user")


class MemberProfileAdminUpdateView(LoginRequiredMixin, PermissionRequiredMixin, UpdateView):
    permission_required = "members.change_memberprofile"
    raise_exception = True
    form_class = MemberProfileAdminForm
    template_name = "members/admin_form.html"
    success_url = reverse_lazy("members:admin_list")

    def get_queryset(self):
        return MemberProfile.objects.select_related("user")

    def form_valid(self, form):
        response = super().form_valid(form)
        messages.success(self.request, "Mitgliederprofil wurde gespeichert.")
        return response
