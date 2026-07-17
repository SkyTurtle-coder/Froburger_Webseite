from django.conf import settings
from django.core.exceptions import ValidationError
from django.db import models


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

    user = models.OneToOneField(
        settings.AUTH_USER_MODEL,
        on_delete=models.CASCADE,
        related_name="member_profile",
    )
    membership_number = models.CharField(max_length=32, blank=True)
    vulgar_name = models.CharField("Vulgo", max_length=150, blank=True)
    joined_on = models.DateField("Eintrittsdatum", null=True, blank=True)
    left_on = models.DateField("Austrittsdatum", null=True, blank=True)
    membership_status = models.CharField(
        max_length=20,
        choices=MembershipStatus.choices,
        default=MembershipStatus.INVITED,
    )
    current_charge = models.CharField(max_length=150, blank=True)
    directory_visibility = models.CharField(
        max_length=20,
        choices=DirectoryVisibility.choices,
        default=DirectoryVisibility.MEMBERS,
    )
    short_bio = models.TextField(blank=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        ordering = ["user__last_name", "user__first_name", "user__email"]
        permissions = [
            ("manage_member_profiles", "Can manage member profiles"),
        ]

    def clean(self):
        if self.joined_on and self.left_on and self.left_on < self.joined_on:
            raise ValidationError(
                {"left_on": "Austrittsdatum darf nicht vor dem Eintrittsdatum liegen."}
            )

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

    def __str__(self):
        return self.display_name
