from __future__ import annotations

from collections.abc import Iterable
from html import escape
from urllib.parse import urlparse

import nh3
from django.utils.text import slugify

from .validators import validate_internal_or_absolute_url

ALLOWED_POST_BODY_TAGS = {
    "a",
    "blockquote",
    "br",
    "em",
    "figcaption",
    "figure",
    "h2",
    "h3",
    "hr",
    "img",
    "li",
    "ol",
    "p",
    "strong",
    "ul",
}

ALLOWED_POST_BODY_ATTRIBUTES = {
    "a": {"href", "title"},
    "img": {"src", "alt"},
}

POST_BODY_DROP_CONTENT_TAGS = {"embed", "iframe", "object", "script", "style"}
POST_BODY_URL_SCHEMES = {"http", "https", "mailto", "tel"}


def _newline_text_to_html(value: str) -> str:
    paragraphs = []
    for paragraph in (chunk.strip() for chunk in value.split("\n\n")):
        if not paragraph:
            continue
        paragraphs.append(
            "<p>" + "<br>".join(escape(line.strip()) for line in paragraph.splitlines()) + "</p>"
        )
    return "".join(paragraphs)


def _is_allowed_link(value: str) -> bool:
    try:
        validate_internal_or_absolute_url(value)
    except Exception:
        return False
    if value.startswith("//"):
        return False
    return True


def _is_allowed_image_src(value: str) -> bool:
    if not value or value.startswith("//"):
        return False
    parsed = urlparse(value)
    if parsed.scheme:
        return False
    return value.startswith("/")


def _post_body_attribute_filter(tag: str, attribute: str, value: str) -> str | None:
    if tag == "a" and attribute == "href":
        return value if _is_allowed_link(value) else None
    if tag == "a" and attribute == "title":
        return value
    if tag == "img" and attribute == "src":
        return value if _is_allowed_image_src(value) else None
    if tag == "img" and attribute == "alt":
        return value
    return None


def sanitize_post_body_html(value: str) -> str:
    return nh3.clean(
        value or "",
        tags=ALLOWED_POST_BODY_TAGS,
        attributes=ALLOWED_POST_BODY_ATTRIBUTES,
        clean_content_tags=POST_BODY_DROP_CONTENT_TAGS,
        url_schemes=POST_BODY_URL_SCHEMES,
        attribute_filter=_post_body_attribute_filter,
        link_rel="noopener noreferrer",
        strip_comments=True,
    ).strip()


def generate_unique_post_slug(*, title: str, queryset, current_pk=None) -> str:
    base_slug = slugify(title) or "beitrag"
    slug = base_slug
    counter = 2
    while queryset.exclude(pk=current_pk).filter(slug=slug).exists():
        slug = f"{base_slug}-{counter}"
        counter += 1
    return slug


def build_legacy_post_body_html(blocks: Iterable) -> str:
    fragments: list[str] = []
    for block in blocks:
        block_type = getattr(block, "block_type", "")
        heading = escape(getattr(block, "heading", "") or "")
        body = getattr(block, "body", "") or ""
        body_html = _newline_text_to_html(body)
        link_url = getattr(block, "link_url", "") or ""
        link_label = escape(getattr(block, "link_label", "") or "")
        image = getattr(block, "image", None)
        carousel = getattr(block, "carousel", None)

        if block_type == "heading" and heading:
            fragments.append(f"<h2>{heading}</h2>")
            continue
        if block_type == "text" and body:
            fragments.append(body_html)
            continue
        if block_type in {"quote", "notice"} and body:
            fragments.append(f"<blockquote>{body_html}</blockquote>")
            continue
        if block_type == "divider":
            fragments.append("<hr>")
            continue
        if image and block_type in {"image", "hero_image"}:
            alt_text = escape(getattr(image, "alt_text", "") or heading or "Beitragsbild")
            src = escape(image.file.url)
            caption = escape(getattr(image, "caption", "") or "")
            figure = [f'<figure><img src="{src}" alt="{alt_text}">']
            if caption:
                figure.append(f"<figcaption>{caption}</figcaption>")
            figure.append("</figure>")
            fragments.append("".join(figure))
            continue
        if image and block_type == "image_text":
            alt_text = escape(getattr(image, "alt_text", "") or heading or "Beitragsbild")
            src = escape(image.file.url)
            figure = [f'<figure><img src="{src}" alt="{alt_text}">']
            if heading:
                figure.append(f"<figcaption>{heading}</figcaption>")
            figure.append("</figure>")
            fragments.append("".join(figure))
            if body:
                fragments.append(body_html)
            if link_url and link_label:
                fragments.append(f'<p><a href="{escape(link_url)}">{link_label}</a></p>')
            continue
        if block_type == "cta" and link_url and link_label:
            fragments.append(f'<p><a href="{escape(link_url)}">{link_label}</a></p>')
            continue
        if carousel and block_type in {"carousel", "gallery"}:
            for item in carousel.items.order_by("position", "pk"):
                if not getattr(item, "is_active", True) or not getattr(item, "image", None):
                    continue
                image_obj = item.image
                alt_text = escape(
                    getattr(image_obj, "alt_text", "")
                    or getattr(item, "heading", "")
                    or "Beitragsbild"
                )
                src = escape(image_obj.file.url)
                caption = escape(getattr(item, "heading", "") or "")
                figure = [f'<figure><img src="{src}" alt="{alt_text}">']
                if caption:
                    figure.append(f"<figcaption>{caption}</figcaption>")
                figure.append("</figure>")
                fragments.append("".join(figure))
                if getattr(item, "body", ""):
                    fragments.append(_newline_text_to_html(item.body))
                if getattr(item, "link_url", "") and getattr(item, "link_label", ""):
                    fragments.append(
                        f'<p><a href="{escape(item.link_url)}">{escape(item.link_label)}</a></p>'
                    )
            continue
    return "".join(fragments)
