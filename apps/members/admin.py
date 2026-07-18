from django.contrib import admin

from .models import MemberProfile


@admin.register(MemberProfile)
class MemberProfileAdmin(admin.ModelAdmin):
    list_display = (
        "display_name",
        "membership_status",
        "directory_visibility",
        "association_type",
        "current_charge",
        "joined_on",
        "left_on",
    )
    list_filter = ("membership_status", "directory_visibility", "association_type")
    readonly_fields = ("created_at", "updated_at")
    search_fields = (
        "user__email",
        "user__first_name",
        "user__last_name",
        "vulgar_name",
        "phone_number",
        "current_charge",
    )
    autocomplete_fields = ("user",)
