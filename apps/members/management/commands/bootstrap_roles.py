from django.contrib.auth.models import Group, Permission
from django.core.management.base import BaseCommand, CommandError

ROLE_PERMISSION_MAP = {
    "member": [],
    "web_aktuar": [],
    "member_admin": [
        "accounts.add_accountinvitation",
        "accounts.view_accountinvitation",
        "members.view_memberprofile",
        "members.change_memberprofile",
        "members.manage_member_profiles",
        "members.view_sensitive_documents",
    ],
    "president": [
        "accounts.add_accountinvitation",
        "accounts.view_accountinvitation",
        "members.view_memberprofile",
        "members.change_memberprofile",
        "members.manage_member_profiles",
        "members.view_sensitive_documents",
        "audit.view_auditlogentry",
    ],
    "system_admin": [
        "accounts.add_accountinvitation",
        "accounts.view_accountinvitation",
        "members.view_memberprofile",
        "members.change_memberprofile",
        "members.manage_member_profiles",
        "members.view_sensitive_documents",
        "audit.view_auditlogentry",
    ],
}


class Command(BaseCommand):
    help = "Create and synchronize the default role groups for the members area."

    def handle(self, *args, **options):
        permission_index = {
            f"{permission.content_type.app_label}.{permission.codename}": permission
            for permission in Permission.objects.select_related("content_type")
        }

        for group_name, permission_labels in ROLE_PERMISSION_MAP.items():
            group, created = Group.objects.get_or_create(name=group_name)
            permissions = []
            missing = []

            for label in permission_labels:
                permission = permission_index.get(label)
                if permission is None:
                    missing.append(label)
                else:
                    permissions.append(permission)

            if missing:
                raise CommandError(
                    f"Missing permissions for group {group_name}: {', '.join(missing)}"
                )

            group.permissions.set(permissions)
            action = "Created" if created else "Updated"
            self.stdout.write(
                self.style.SUCCESS(
                    f"{action} group {group_name} with {len(permissions)} permissions."
                )
            )
