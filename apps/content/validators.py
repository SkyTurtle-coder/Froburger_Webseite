from __future__ import annotations

from urllib.parse import urlparse

from django.core.exceptions import ValidationError


def validate_internal_or_absolute_url(value):
    if not value:
        return
    if value.startswith(("/", "#")):
        return

    parsed = urlparse(value)
    if parsed.scheme in {"http", "https"} and parsed.netloc:
        return
    if parsed.scheme in {"mailto", "tel"} and parsed.path:
        return

    raise ValidationError(
        "Bitte eine gueltige absolute URL, einen relativen Pfad "
        "oder einen mailto:/tel:-Link eingeben."
    )
