from django.contrib import admin

from .models import Event, EventCategory, EventRevision


@admin.register(EventCategory)
class EventCategoryAdmin(admin.ModelAdmin):
    list_display = ("name", "slug", "is_active")
    list_filter = ("is_active",)
    prepopulated_fields = {"slug": ("name",)}
    search_fields = ("name", "description")


@admin.register(Event)
class EventAdmin(admin.ModelAdmin):
    list_display = ("title", "start_at", "status", "visibility", "category")
    list_filter = ("status", "visibility", "category")
    prepopulated_fields = {"slug": ("title",)}
    search_fields = ("title", "short_description", "description", "location_name")


@admin.register(EventRevision)
class EventRevisionAdmin(admin.ModelAdmin):
    list_display = ("event", "revision_number", "status", "created_at")
    list_filter = ("status",)
    search_fields = ("event__title", "reason")
