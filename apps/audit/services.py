from .models import AuditLogEntry


def record_audit_event(
    *,
    action,
    actor=None,
    object_type,
    object_id="",
    result=AuditLogEntry.Result.SUCCESS,
    detail="",
):
    return AuditLogEntry.objects.create(
        action=action,
        actor=actor,
        object_type=object_type,
        object_id=object_id,
        result=result,
        detail=detail,
    )
