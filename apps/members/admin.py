from django.contrib import admin

from .models import MemberProfile


@admin.register(MemberProfile)
class MemberProfileAdmin(admin.ModelAdmin):
    list_display = (
        "display_name",
        "membership_status",
        "directory_visibility",
        "current_charge",
        "joined_on",
        "left_on",
    )
    list_filter = ("membership_status", "directory_visibility")
    readonly_fields = ("created_at", "updated_at")
    search_fields = (
        "user__email",
        "user__first_name",
        "user__last_name",
        "membership_number",
        "vulgar_name",
        "current_charge",
    )
    autocomplete_fields = ("user",)
