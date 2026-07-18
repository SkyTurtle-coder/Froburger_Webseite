from django.contrib import admin

from .models import MemberProfile, PublicMemberProfile


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


@admin.register(PublicMemberProfile)
class PublicMemberProfileAdmin(admin.ModelAdmin):
    list_display = (
        "display_name",
        "vulgar_name",
        "function_title",
        "group_key",
        "sort_order",
        "is_active",
        "is_publicly_approved",
    )
    list_filter = ("group_key", "is_active", "is_publicly_approved")
    search_fields = ("display_name", "vulgar_name", "function_title", "external_key")
    readonly_fields = ("created_at", "updated_at")
    autocomplete_fields = ("image",)
