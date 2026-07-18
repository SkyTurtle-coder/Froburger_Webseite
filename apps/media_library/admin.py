from django.contrib import admin

from .models import MediaAsset


@admin.register(MediaAsset)
class MediaAssetAdmin(admin.ModelAdmin):
    list_display = ("title", "visibility", "status", "mime_type", "updated_at")
    list_filter = ("visibility", "status", "mime_type")
    search_fields = ("title", "alt_text", "credit")
