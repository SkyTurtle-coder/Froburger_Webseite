CMS_ACCESS_PERMISSIONS = (
    "content.view_post",
    "events.view_event",
    "documents.view_document",
)


def can_access_cms(user) -> bool:
    return bool(
        getattr(user, "is_authenticated", False)
        and getattr(user, "is_active", False)
        and any(user.has_perm(permission) for permission in CMS_ACCESS_PERMISSIONS)
    )
