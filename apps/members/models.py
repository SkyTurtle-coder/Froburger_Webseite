from django.conf import settings
from django.core.exceptions import ValidationError
from django.db import models

from .storage import private_member_photo_storage


class MemberProfile(models.Model):
    class MembershipStatus(models.TextChoices):
        INVITED = "invited", "Invited"
        ACTIVE = "active", "Active"
        INACTIVE = "inactive", "Inactive"
        ALTHERR = "altherr", "Alt-Froburger"
        HONORARY = "honorary", "Honorary"

    class DirectoryVisibility(models.TextChoices):
        PRIVATE = "private", "Private"
        MEMBERS = "members", "Members only"
        PUBLIC = "public", "Public"

    class AssociationType(models.TextChoices):
        ACTIVE = "active", "Aktiv"
        AF = "af", "AF"

    class ChargeChoices(models.TextChoices):
        SENIOR = "senior", "Senior"
        CONSENIOR = "consenior", "Consenior"
        FUXMAJOR = "fuxmajor", "Fuxmajor"
        AKTUAR = "aktuar", "Aktuar"
        QUESTOR = "questor", "Questor"

    class MemberRole(models.TextChoices):
        BURSCH = "bursch", "Bursch"
        FUX = "fux", "Fux"
        WEB_X = "web_x", "Web-X"

    user = models.OneToOneField(
        settings.AUTH_USER_MODEL,
        on_delete=models.CASCADE,
        related_name="member_profile",
    )
    vulgar_name = models.CharField("Vulgo", max_length=150, blank=True)
    profile_photo = models.ImageField(
        "Profilfoto",
        upload_to="member_photos/",
        storage=private_member_photo_storage,
        blank=True,
    )
    phone_number = models.CharField("Telefonnummer", max_length=50, blank=True)
    joined_on = models.DateField("Eintrittsdatum", null=True, blank=True)
    left_on = models.DateField("Austrittsdatum", null=True, blank=True)
    membership_status = models.CharField(
        max_length=20,
        choices=MembershipStatus.choices,
        default=MembershipStatus.INVITED,
    )
    current_charge = models.CharField(
        "Charge",
        max_length=20,
        choices=ChargeChoices.choices,
        blank=True,
    )
    member_roles = models.JSONField("Rollen", default=list, blank=True)
    association_type = models.CharField(
        "Verein",
        max_length=20,
        choices=AssociationType.choices,
        blank=True,
    )
    directory_visibility = models.CharField(
        max_length=20,
        choices=DirectoryVisibility.choices,
        default=DirectoryVisibility.MEMBERS,
    )
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        ordering = ["user__last_name", "user__first_name", "user__email"]
        permissions = [
            ("manage_member_profiles", "Can manage member profiles"),
            ("view_sensitive_documents", "Can view sensitive internal documents"),
        ]

    def clean(self):
        if self.joined_on and self.left_on and self.left_on < self.joined_on:
            raise ValidationError(
                {"left_on": "Austrittsdatum darf nicht vor dem Eintrittsdatum liegen."}
            )
        valid_roles = {choice for choice, _ in self.MemberRole.choices}
        roles = self.member_roles or []
        invalid_roles = [role for role in roles if role not in valid_roles]
        if invalid_roles:
            raise ValidationError({"member_roles": "Ungueltige Rollen wurden uebermittelt."})

    @property
    def full_name(self):
        return self.user.get_full_name().strip()

    @property
    def display_name(self):
        if self.vulgar_name and self.full_name:
            return f"{self.vulgar_name} ({self.full_name})"
        if self.vulgar_name:
            return self.vulgar_name
        return self.full_name or self.user.email

    @property
    def initials(self):
        base = self.vulgar_name or self.full_name or self.user.email
        parts = [part for part in base.replace("(", " ").replace(")", " ").split() if part]
        if len(parts) >= 2:
            return f"{parts[0][0]}{parts[1][0]}".upper()
        return base[:2].upper()

    @property
    def member_roles_display(self):
        role_map = dict(self.MemberRole.choices)
        roles = self.member_roles or []
        return ", ".join(role_map[role] for role in roles if role in role_map)

    def has_member_role(self, role):
        return role in (self.member_roles or [])

    def __str__(self):
        return self.display_name
