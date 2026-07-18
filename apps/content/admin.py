from django.contrib import admin

from .models import (
    Carousel,
    CarouselItem,
    CarouselRevision,
    LayoutPreset,
    Page,
    PageRevision,
    PageSection,
    Post,
    PostBlock,
    PostRevision,
)


class PageSectionInline(admin.StackedInline):
    model = PageSection
    extra = 0


class PostBlockInline(admin.StackedInline):
    model = PostBlock
    extra = 0


class CarouselItemInline(admin.StackedInline):
    model = CarouselItem
    extra = 0


class PageRevisionInline(admin.TabularInline):
    model = PageRevision
    extra = 0
    can_delete = False
    readonly_fields = ("revision_number", "created_by", "reason", "status", "created_at")


class PostRevisionInline(admin.TabularInline):
    model = PostRevision
    extra = 0
    can_delete = False
    readonly_fields = ("revision_number", "created_by", "reason", "status", "created_at")


class CarouselRevisionInline(admin.TabularInline):
    model = CarouselRevision
    extra = 0
    can_delete = False
    readonly_fields = ("revision_number", "created_by", "reason", "created_at")


@admin.register(LayoutPreset)
class LayoutPresetAdmin(admin.ModelAdmin):
    list_display = ("name", "scope", "key", "is_active")
    list_filter = ("scope", "is_active")
    search_fields = ("name", "key")


@admin.register(Page)
class PageAdmin(admin.ModelAdmin):
    list_display = ("title", "page_key", "status", "visibility", "updated_at")
    list_filter = ("status", "visibility", "page_type")
    prepopulated_fields = {"slug": ("title",), "page_key": ("title",)}
    search_fields = ("title", "page_key", "slug")
    inlines = [PageSectionInline, PageRevisionInline]


@admin.register(Post)
class PostAdmin(admin.ModelAdmin):
    list_display = (
        "title",
        "status",
        "visibility",
        "is_homepage_pinned",
        "pin_priority",
        "updated_at",
    )
    list_filter = ("status", "visibility", "is_homepage_pinned")
    prepopulated_fields = {"slug": ("title",)}
    search_fields = ("title", "slug", "teaser")
    inlines = [PostBlockInline, PostRevisionInline]


@admin.register(Carousel)
class CarouselAdmin(admin.ModelAdmin):
    list_display = ("name", "visibility", "is_active", "updated_at")
    list_filter = ("visibility", "is_active")
    search_fields = ("name", "title")
    inlines = [CarouselItemInline, CarouselRevisionInline]
