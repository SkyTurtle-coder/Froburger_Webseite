from __future__ import annotations

import mimetypes
from pathlib import PurePosixPath

from django.conf import settings
from django.http import FileResponse, HttpResponse

from .models import MemberProfile


def user_can_view_member_profile(user, profile: MemberProfile) -> bool:
    if not user or not getattr(user, "is_authenticated", False) or not user.is_active:
        return False
    if user.is_superuser or user.has_perm("members.manage_member_profiles"):
        return True
    if profile.user_id == user.pk:
        return True
    return profile.directory_visibility in {
        MemberProfile.DirectoryVisibility.MEMBERS,
        MemberProfile.DirectoryVisibility.PUBLIC,
    }


def user_can_view_member_profile_photo(user, profile: MemberProfile) -> bool:
    return bool(profile.profile_photo) and user_can_view_member_profile(user, profile)


def build_private_file_response(file_field):
    name = file_field.name
    storage = file_field.storage
    if not name or not storage.exists(name):
        raise FileNotFoundError(name or "<missing>")

    filename = PurePosixPath(name).name
    content_type, _ = mimetypes.guess_type(filename)
    content_type = content_type or "application/octet-stream"

    if getattr(settings, "PRIVATE_MEDIA_USE_X_ACCEL_REDIRECT", False):
        prefix = getattr(settings, "PRIVATE_MEDIA_ACCEL_REDIRECT_PREFIX", "/_protected")
        response = HttpResponse(content_type=content_type)
        response["Content-Disposition"] = f'inline; filename="{filename}"'
        response["X-Accel-Redirect"] = f"{prefix.rstrip('/')}/{name.replace(chr(92), '/')}"
        response["Cache-Control"] = "private, max-age=300"
        return response

    response = FileResponse(
        storage.open(name, "rb"),
        as_attachment=False,
        filename=filename,
        content_type=content_type,
    )
    response["Cache-Control"] = "private, max-age=300"
    return response
