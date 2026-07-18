from django.conf import settings
from django.contrib import messages
from django.contrib.auth import get_user_model
from django.contrib.auth.mixins import LoginRequiredMixin, UserPassesTestMixin
from django.core.mail import send_mail
from django.http import Http404
from django.shortcuts import redirect
from django.urls import reverse, reverse_lazy
from django.views.generic import FormView, TemplateView

from apps.audit.models import AuditLogEntry
from apps.audit.services import record_audit_event
from apps.content.permissions import can_access_cms
from apps.documents.services import user_can_view_sensitive_document_area
from apps.members.models import MemberProfile
from apps.members.views import get_or_create_member_profile

from .forms import InvitationAcceptForm, InvitationCreateForm
from .models import AccountInvitation

User = get_user_model()


class AccountHomeView(LoginRequiredMixin, TemplateView):
    template_name = "accounts/home.html"

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        profile = get_or_create_member_profile(self.request.user)
        context["profile"] = profile
        context["can_invite"] = self.request.user.has_perm("accounts.add_accountinvitation")
        context["can_manage_members"] = self.request.user.has_perm("members.view_memberprofile")
        context["can_access_cms"] = can_access_cms(self.request.user)
        context["can_view_sensitive_documents"] = user_can_view_sensitive_document_area(
            self.request.user
        )
        return context


class InvitationCreateView(LoginRequiredMixin, UserPassesTestMixin, FormView):
    form_class = InvitationCreateForm
    template_name = "accounts/invitation_form.html"
    success_url = reverse_lazy("accounts:invitation_create")

    def test_func(self):
        return self.request.user.has_perm("accounts.add_accountinvitation")

    def form_valid(self, form):
        invitation, token = form.save(actor=self.request.user)
        accept_url = self.request.build_absolute_uri(
            reverse(
                "accounts:invitation_accept",
                kwargs={"invitation_id": invitation.pk, "token": token},
            )
        )

        send_mail(
            subject="Einladung AV Froburger Mitgliederbereich",
            message=(
                "Sie wurden zum Mitgliederbereich eingeladen.\n\n"
                f"Einladung annehmen: {accept_url}\n\n"
                f"Gueltig bis: {invitation.expires_at:%d.%m.%Y %H:%M}"
            ),
            from_email=settings.DEFAULT_FROM_EMAIL,
            recipient_list=[invitation.invited_user.email],
        )

        record_audit_event(
            action="accounts.invitation.created",
            actor=self.request.user,
            object_type="AccountInvitation",
            object_id=str(invitation.pk),
            result=AuditLogEntry.Result.SUCCESS,
        )

        messages.success(self.request, "Die Einladung wurde erstellt und per E-Mail versendet.")
        return super().form_valid(form)


class InvitationAcceptView(FormView):
    form_class = InvitationAcceptForm
    template_name = "accounts/invitation_accept.html"
    success_url = reverse_lazy("accounts:login")

    invitation = None

    def dispatch(self, request, *args, **kwargs):
        try:
            self.invitation = AccountInvitation.objects.select_related(
                "invited_user", "invited_by"
            ).get(pk=kwargs["invitation_id"])
        except AccountInvitation.DoesNotExist as exc:
            raise Http404 from exc

        if not self.invitation.token_is_valid(kwargs["token"]):
            return self.render_to_response({"invalid_invitation": True}, status=400)

        return super().dispatch(request, *args, **kwargs)

    def get_form_kwargs(self):
        kwargs = super().get_form_kwargs()
        kwargs["user"] = self.invitation.invited_user
        return kwargs

    def form_valid(self, form):
        user = form.save(commit=False)
        user.is_active = True
        user.save()
        profile = get_or_create_member_profile(user)
        profile.membership_status = MemberProfile.MembershipStatus.ACTIVE
        profile.save(update_fields=["membership_status", "updated_at"])
        self.invitation.mark_used()

        record_audit_event(
            action="accounts.invitation.accepted",
            actor=user,
            object_type="AccountInvitation",
            object_id=str(self.invitation.pk),
            result=AuditLogEntry.Result.SUCCESS,
        )

        messages.success(
            self.request,
            "Ihr Konto wurde aktiviert. Sie koennen sich jetzt anmelden.",
        )
        return redirect(self.get_success_url())
