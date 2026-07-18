from django.contrib import admin

from .models import Document, DocumentCategory, DocumentVersion


@admin.register(DocumentCategory)
class DocumentCategoryAdmin(admin.ModelAdmin):
    list_display = ("name", "slug", "is_active")
    list_filter = ("is_active",)
    search_fields = ("name", "slug")
    prepopulated_fields = {"slug": ("name",)}


class DocumentVersionInline(admin.TabularInline):
    model = DocumentVersion
    extra = 0
    fields = (
        "version_number",
        "original_filename",
        "mime_type",
        "file_size",
        "checksum",
        "change_note",
        "uploaded_by",
    )
    readonly_fields = fields
    can_delete = False


@admin.register(Document)
class DocumentAdmin(admin.ModelAdmin):
    list_display = ("title", "visibility", "status", "category", "published_at", "valid_until")
    list_filter = ("visibility", "status", "category")
    search_fields = ("title", "description")
    filter_horizontal = ("allowed_groups", "allowed_users")
    readonly_fields = ("created_at", "updated_at", "version_counter")
    inlines = [DocumentVersionInline]
