from __future__ import annotations

import hashlib
import io
import mimetypes
import zipfile
from pathlib import Path, PurePosixPath

from django.conf import settings
from django.core.exceptions import PermissionDenied, ValidationError
from django.db.models import Max
from django.http import FileResponse, Http404, HttpResponse
from django.utils import timezone

from apps.audit.models import AuditLogEntry
from apps.audit.services import record_audit_event

from .models import Document, DocumentVersion, _user_can_view_highly_sensitive_documents

ALLOWED_DOCUMENT_FORMATS = {
    ".pdf": "application/pdf",
    ".docx": "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    ".xlsx": "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    ".pptx": "application/vnd.openxmlformats-officedocument.presentationml.presentation",
    ".txt": "text/plain",
    ".csv": "text/csv",
}

OFFICE_SIGNATURES = {
    ".docx": "word/document.xml",
    ".xlsx": "xl/workbook.xml",
    ".pptx": "ppt/presentation.xml",
}

DISALLOWED_INTERMEDIATE_EXTENSIONS = {
    ".exe",
    ".bat",
    ".cmd",
    ".com",
    ".js",
    ".vbs",
    ".ps1",
    ".docm",
    ".xlsm",
    ".pptm",
    ".msi",
    ".zip",
    ".rar",
    ".7z",
}


def user_can_manage_publication_documents(user) -> bool:
    return bool(
        user
        and getattr(user, "is_authenticated", False)
        and user.is_active
        and (
            user.is_superuser
            or user.has_perm("documents.manage_publication_documents")
            or user.has_perm("documents.manage_sensitive_documents")
        )
    )


def user_can_manage_sensitive_documents(user) -> bool:
    return bool(
        user
        and getattr(user, "is_authenticated", False)
        and user.is_active
        and (user.is_superuser or user.has_perm("documents.manage_sensitive_documents"))
    )


def user_can_view_document(user, document: Document) -> bool:
    if document.is_visible_to(user):
        return True
    if not user or not getattr(user, "is_authenticated", False) or not user.is_active:
        return False
    return Document.objects.editable_by(user).filter(pk=document.pk).exists()


def user_can_view_sensitive_document_area(user) -> bool:
    return _user_can_view_highly_sensitive_documents(user)


def validate_document_upload(uploaded_file):
    max_upload_bytes = getattr(settings, "DOCUMENTS_MAX_UPLOAD_BYTES", 20 * 1024 * 1024)
    if uploaded_file.size > max_upload_bytes:
        raise ValidationError(
            "Die Datei ist zu gross. "
            f"Erlaubt sind hoechstens {max_upload_bytes // (1024 * 1024)} MB."
        )

    suffixes = [suffix.lower() for suffix in Path(uploaded_file.name).suffixes]
    if not suffixes:
        raise ValidationError("Die Datei braucht eine gueltige Dateiendung.")

    extension = suffixes[-1]
    if extension in DISALLOWED_INTERMEDIATE_EXTENSIONS:
        raise ValidationError("Mehrfache oder ausfuehrbare Dateiendungen sind nicht erlaubt.")
    if any(suffix in DISALLOWED_INTERMEDIATE_EXTENSIONS for suffix in suffixes[:-1]):
        raise ValidationError("Mehrfache oder ausfuehrbare Dateiendungen sind nicht erlaubt.")
    if extension not in ALLOWED_DOCUMENT_FORMATS:
        raise ValidationError("Dieses Dateiformat ist nicht freigegeben.")

    uploaded_file.seek(0)
    content = uploaded_file.read()
    uploaded_file.seek(0)

    if extension == ".pdf" and not content.startswith(b"%PDF"):
        raise ValidationError("Die Datei ist kein gueltiges PDF.")
    if extension in OFFICE_SIGNATURES:
        try:
            with zipfile.ZipFile(io.BytesIO(content), "r") as archive:
                names = set(archive.namelist())
        except zipfile.BadZipFile as exc:
            raise ValidationError("Die Office-Datei ist beschaedigt.") from exc
        required_member = OFFICE_SIGNATURES[extension]
        if required_member not in names:
            raise ValidationError("Die Office-Datei hat kein gueltiges internes Format.")
    if extension in {".txt", ".csv"}:
        try:
            content.decode("utf-8")
        except UnicodeDecodeError as exc:
            raise ValidationError("Textdateien muessen UTF-8-kodiert sein.") from exc

    mime_type = ALLOWED_DOCUMENT_FORMATS[extension]
    guessed_type, _ = mimetypes.guess_type(uploaded_file.name)
    return {
        "extension": extension,
        "mime_type": guessed_type or mime_type,
        "file_size": len(content),
        "checksum": hashlib.sha256(content).hexdigest(),
        "original_filename": PurePosixPath(uploaded_file.name).name,
    }


def build_private_download_response(file_field, *, filename="", as_attachment=True):
    name = file_field.name
    storage = file_field.storage
    if not name or not storage.exists(name):
        raise FileNotFoundError(name or "<missing>")

    download_name = filename or PurePosixPath(name).name
    content_type, _ = mimetypes.guess_type(download_name)
    content_type = content_type or "application/octet-stream"

    if getattr(settings, "PRIVATE_MEDIA_USE_X_ACCEL_REDIRECT", False):
        prefix = getattr(settings, "PRIVATE_MEDIA_ACCEL_REDIRECT_PREFIX", "/_protected")
        response = HttpResponse(content_type=content_type)
        disposition = "attachment" if as_attachment else "inline"
        response["Content-Disposition"] = f'{disposition}; filename="{download_name}"'
        response["X-Accel-Redirect"] = f"{prefix.rstrip('/')}/{name.replace(chr(92), '/')}"
        response["Cache-Control"] = "private, no-store"
        response["X-Content-Type-Options"] = "nosniff"
        return response

    response = FileResponse(
        storage.open(name, "rb"),
        as_attachment=as_attachment,
        filename=download_name,
        content_type=content_type,
    )
    response["Cache-Control"] = "private, no-store"
    response["X-Content-Type-Options"] = "nosniff"
    return response


def create_document_version(*, document: Document, uploaded_file, actor, change_note=""):
    metadata = validate_document_upload(uploaded_file)
    version_number = (
        document.versions.aggregate(max_value=Max("version_number"))["max_value"] or 0
    ) + 1
    version = DocumentVersion.objects.create(
        document=document,
        version_number=version_number,
        file=uploaded_file,
        original_filename=metadata["original_filename"],
        mime_type=metadata["mime_type"],
        file_size=metadata["file_size"],
        checksum=metadata["checksum"],
        change_note=change_note,
        uploaded_by=actor,
    )
    document.current_version = version
    document.version_counter = version_number
    document.save(update_fields=["current_version", "version_counter", "updated_at"])
    record_audit_event(
        action="documents.version.created",
        actor=actor,
        object_type="Document",
        object_id=str(document.pk),
        result=AuditLogEntry.Result.SUCCESS,
        detail=f"version={version_number}"[:255],
    )
    return version


def save_document_editor(*, form, actor, publish=False, archive=False):
    document = form.save(commit=False)
    is_new = document.pk is None
    document.last_edited_by = actor
    if is_new:
        document.created_by = actor
    if publish:
        document.status = Document.Status.PUBLISHED
        document.published_at = document.published_at or timezone.now()
    if archive:
        document.status = Document.Status.ARCHIVED
        document.archived_at = timezone.now()
    document.full_clean()
    document.save()
    form.save_m2m()
    if document.visibility != Document.Visibility.SELECTED_GROUPS:
        document.allowed_groups.clear()
    if document.visibility != Document.Visibility.SELECTED_USERS:
        document.allowed_users.clear()
    uploaded_file = form.cleaned_data.get("file_upload")
    if uploaded_file:
        create_document_version(
            document=document,
            uploaded_file=uploaded_file,
            actor=actor,
            change_note=form.cleaned_data.get("change_note", ""),
        )
    elif is_new and document.current_version_id is None:
        raise ValidationError("Neue Dokumente brauchen eine Datei.")
    record_audit_event(
        action="documents.document.created" if is_new else "documents.document.updated",
        actor=actor,
        object_type="Document",
        object_id=str(document.pk),
        result=AuditLogEntry.Result.SUCCESS,
        detail=f"status={document.status}"[:255],
    )
    return document


def activate_document_version(*, document: Document, version: DocumentVersion, actor):
    if version.document_id != document.pk:
        raise PermissionDenied
    document.current_version = version
    document.version_counter = version.version_number
    document.last_edited_by = actor
    document.save(
        update_fields=["current_version", "version_counter", "last_edited_by", "updated_at"]
    )
    record_audit_event(
        action="documents.version.activated",
        actor=actor,
        object_type="Document",
        object_id=str(document.pk),
        result=AuditLogEntry.Result.SUCCESS,
        detail=f"version={version.version_number}"[:255],
    )


def archive_document(*, document: Document, actor):
    document.status = Document.Status.ARCHIVED
    document.archived_at = timezone.now()
    document.last_edited_by = actor
    document.full_clean()
    document.save(update_fields=["status", "archived_at", "last_edited_by", "updated_at"])
    record_audit_event(
        action="documents.document.archived",
        actor=actor,
        object_type="Document",
        object_id=str(document.pk),
        result=AuditLogEntry.Result.SUCCESS,
        detail=document.visibility[:255],
    )
    return document


def document_download_response(*, user, document: Document):
    if not document.current_version_id:
        raise Http404
    actor = user if getattr(user, "is_authenticated", False) else None
    if not user_can_view_document(user, document):
        record_audit_event(
            action="documents.document.download.denied",
            actor=actor,
            object_type="Document",
            object_id=str(document.pk),
            result=AuditLogEntry.Result.FAILURE,
            detail=f"visibility={document.visibility}"[:255],
        )
        raise Http404
    record_audit_event(
        action="documents.document.downloaded",
        actor=actor,
        object_type="Document",
        object_id=str(document.pk),
        result=AuditLogEntry.Result.SUCCESS,
        detail=f"version={document.current_version.version_number}"[:255],
    )
    return build_private_download_response(
        document.current_version.file,
        filename=document.current_version.original_filename,
        as_attachment=True,
    )
