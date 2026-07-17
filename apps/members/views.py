from django.contrib import messages
from django.contrib.auth.mixins import (
    LoginRequiredMixin,
    PermissionRequiredMixin,
)
from django.db.models import Q
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


class MemberDirectoryView(LoginRequiredMixin, ListView):
    template_name = "members/directory.html"
    context_object_name = "profiles"

    def get_queryset(self):
        queryset = MemberProfile.objects.select_related("user").exclude(
            membership_status__in=[
                MemberProfile.MembershipStatus.INVITED,
                MemberProfile.MembershipStatus.INACTIVE,
            ]
        )
        if self.request.user.has_perm("members.manage_member_profiles"):
            return queryset

        return queryset.filter(
            Q(
                directory_visibility__in=[
                    MemberProfile.DirectoryVisibility.MEMBERS,
                    MemberProfile.DirectoryVisibility.PUBLIC,
                ]
            )
            | Q(user=self.request.user)
        )


class InternalSectionView(LoginRequiredMixin, TemplateView):
    template_name = "members/section.html"
    page_title = ""
    heading = ""
    intro = ""
    body_copy = ""
    section_tag = ""
    cta_label = ""
    cta_url = ""

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        context.update(
            page_title=self.page_title,
            heading=self.heading,
            intro=self.intro,
            body_copy=self.body_copy,
            section_tag=self.section_tag,
            cta_label=self.cta_label,
            cta_url=self.cta_url,
        )
        return context


class MediaHubView(InternalSectionView):
    page_title = "Medien | AV Froburger"
    heading = "Medien"
    intro = "Interne Bilder, Alben und spaetere Medienablaeufe fuer den Mitgliederbereich."
    body_copy = (
        "Die geschuetzte Medienbibliothek folgt in Phase 6. Dieser Bereich ist bereits "
        "reserviert, damit die Navigation und die Zugriffslogik jetzt konsistent aufgebaut sind."
    )
    section_tag = "Interner Bereich"
    cta_label = "Zurueck zum Dashboard"
    cta_url = reverse_lazy("accounts:home")


class GeneralDocumentsView(InternalSectionView):
    page_title = "Allgemeine Dokumente | AV Froburger"
    heading = "Allgemeine Dokumente"
    intro = "Interne Unterlagen fuer Mitglieder, die nicht als besonders sensibel eingestuft sind."
    body_copy = (
        "Die eigentliche Dokumentenverwaltung folgt in Phase 5. Hier entsteht der Einstieg "
        "fuer allgemeine Protokolle, Semesterunterlagen und interne Informationen."
    )
    section_tag = "Dokumente"
    cta_label = "Zurueck zum Dashboard"
    cta_url = reverse_lazy("accounts:home")


class SensitiveDocumentsView(PermissionRequiredMixin, InternalSectionView):
    permission_required = "members.view_sensitive_documents"
    raise_exception = True
    page_title = "Sensible Dokumente | AV Froburger"
    heading = "Sensible Dokumente"
    intro = "Besonders geschuetzte Unterlagen mit erweitertem Zugriffsbedarf."
    body_copy = (
        "Dieser Bereich ist fuer sensible Dokumente reserviert und serverseitig geschuetzt. "
        "Die eigentliche Dateiablage und Download-Logik folgen in Phase 5."
    )
    section_tag = "Vertraulich"
    cta_label = "Zurueck zum Dashboard"
    cta_url = reverse_lazy("accounts:home")

    def has_permission(self):
        return super().has_permission()
