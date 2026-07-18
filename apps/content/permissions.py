CMS_ACCESS_PERMISSION = "content.view_post"


def can_access_cms(user) -> bool:
    return bool(
        getattr(user, "is_authenticated", False)
        and getattr(user, "is_active", False)
        and user.has_perm(CMS_ACCESS_PERMISSION)
    )
