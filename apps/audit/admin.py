from django.contrib import admin

from .models import AuditLogEntry


@admin.register(AuditLogEntry)
class AuditLogEntryAdmin(admin.ModelAdmin):
    list_display = ("created_at", "action", "result", "actor", "object_type", "object_id")
    list_filter = ("result", "action", "object_type")
    search_fields = ("action", "object_type", "object_id", "detail", "actor__email")
    readonly_fields = (
        "created_at",
        "action",
        "result",
        "actor",
        "object_type",
        "object_id",
        "detail",
    )

    def has_add_permission(self, request):
        return False

    def has_change_permission(self, request, obj=None):
        return False
