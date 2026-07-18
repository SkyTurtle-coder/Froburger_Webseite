from datetime import timedelta

import pytest
from django.contrib.auth.models import Group
from django.core.files.uploadedfile import SimpleUploadedFile
from django.core.management import call_command
from django.urls import reverse
from django.utils import timezone

from apps.documents.models import Document, DocumentCategory

pytestmark = pytest.mark.django_db

PDF_BYTES = b"%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"


def configure_document_media(settings, tmp_path):
    settings.PRIVATE_MEDIA_ROOT = tmp_path / "private-media"
    settings.PUBLIC_MEDIA_ROOT = tmp_path / "public-media"
    settings.MEDIA_ROOT = settings.PUBLIC_MEDIA_ROOT


def pdf_upload(name="unterlage.pdf"):
    return SimpleUploadedFile(name, PDF_BYTES, content_type="application/pdf")


@pytest.fixture
def document_role_user_factory(user_factory):
    call_command("bootstrap_roles")

    def factory(role_name=None, **overrides):
        user = user_factory(**overrides)
        if role_name:
            user.groups.add(Group.objects.get(name=role_name))
        return user

    return factory


@pytest.fixture
def document_category():
    return DocumentCategory.objects.create(name="Protokolle", slug="protokolle")


def create_document(
    *,
    actor,
    title="Semesterprotokoll",
    visibility=Document.Visibility.ALL_MEMBERS,
    status=Document.Status.PUBLISHED,
    category=None,
    published_at=None,
):
    document = Document.objects.create(
        title=title,
        description=f"Beschreibung zu {title}",
        category=category,
        visibility=visibility,
        status=status,
        published_at=published_at or timezone.now() - timedelta(days=1),
        created_by=actor,
        last_edited_by=actor,
    )
    version = document.versions.create(
        version_number=1,
        file=pdf_upload(f"{title.lower().replace(' ', '-')}.pdf"),
        original_filename=f"{title.lower().replace(' ', '-')}.pdf",
        mime_type="application/pdf",
        file_size=len(PDF_BYTES),
        checksum="abc123",
        uploaded_by=actor,
    )
    document.current_version = version
    document.version_counter = 1
    document.save(update_fields=["current_version", "version_counter", "updated_at"])
    return document


def test_document_cms_routes_require_document_permissions(client, document_role_user_factory):
    dashboard_url = reverse("cms:dashboard")
    list_url = reverse("cms:document_list")

    assert client.get(list_url).status_code == 302

    member = document_role_user_factory("member")
    client.force_login(member)
    assert client.get(list_url).status_code == 403

    document_manager = document_role_user_factory("document_verantwortlich")
    client.force_login(document_manager)
    assert client.get(dashboard_url).status_code == 200
    assert client.get(list_url).status_code == 200

    web_aktuar = document_role_user_factory("web_aktuar")
    client.force_login(web_aktuar)
    assert client.get(list_url).status_code == 200


def test_web_x_can_create_document_but_not_highly_sensitive(
    client,
    settings,
    tmp_path,
    document_role_user_factory,
    document_category,
):
    configure_document_media(settings, tmp_path)
    editor = document_role_user_factory("web_aktuar")
    client.force_login(editor)

    publish_response = client.post(
        reverse("cms:document_create"),
        {
            "title": "Chargenreglement",
            "description": "Interne Wegleitung",
            "category": document_category.pk,
            "visibility": Document.Visibility.ALL_MEMBERS,
            "allowed_groups": [],
            "allowed_users": [],
            "status": Document.Status.DRAFT,
            "published_at": "",
            "valid_until": "",
            "version_counter": 0,
            "change_note": "Initial",
            "file_upload": pdf_upload("chargenreglement.pdf"),
            "workflow_action": "publish",
        },
    )

    created = Document.objects.get(title="Chargenreglement")

    assert publish_response.status_code == 302
    assert publish_response.url == reverse("cms:document_edit", args=[created.pk])
    assert created.status == Document.Status.PUBLISHED
    assert created.current_version.original_filename == "chargenreglement.pdf"

    rejected_response = client.post(
        reverse("cms:document_create"),
        {
            "title": "Nur fuer Burschen",
            "description": "Vertraulich",
            "category": "",
            "visibility": Document.Visibility.HIGHLY_SENSITIVE,
            "allowed_groups": [],
            "allowed_users": [],
            "status": Document.Status.DRAFT,
            "published_at": "",
            "valid_until": "",
            "version_counter": 0,
            "change_note": "Initial",
            "file_upload": pdf_upload("vertraulich.pdf"),
            "workflow_action": "save",
        },
    )

    assert rejected_response.status_code == 400
    assert "Besonders sensible Dokumente duerfen nur separat berechtigt verwaltet werden." in (
        rejected_response.content.decode()
    )


def test_document_member_list_and_download_respect_group_visibility(
    client,
    settings,
    tmp_path,
    document_role_user_factory,
    user_factory,
    document_category,
):
    configure_document_media(settings, tmp_path)
    actor = document_role_user_factory("document_verantwortlich")
    privileged_group = Group.objects.create(name="Chargia")
    document = create_document(
        actor=actor,
        title="Chargenprotokoll",
        visibility=Document.Visibility.SELECTED_GROUPS,
        category=document_category,
    )
    document.allowed_groups.add(privileged_group)

    allowed_user = user_factory()
    allowed_user.groups.add(privileged_group)
    denied_user = user_factory()

    client.force_login(allowed_user)
    allowed_list = client.get(reverse("members:documents"))
    allowed_download = client.get(reverse("members:document_download", args=[document.pk]))

    client.force_login(denied_user)
    denied_list = client.get(reverse("members:documents"))
    denied_download = client.get(reverse("members:document_download", args=[document.pk]))

    assert "Chargenprotokoll" in allowed_list.content.decode()
    assert allowed_download.status_code == 200
    assert "no-store" in allowed_download["Cache-Control"]
    with pytest.raises(ValueError):
        _ = document.current_version.file.url

    denied_content = denied_list.content.decode()
    assert "Chargenprotokoll" not in denied_content
    assert denied_download.status_code == 404


def test_document_download_is_available_for_publication_manager(
    client,
    settings,
    tmp_path,
    document_role_user_factory,
):
    configure_document_media(settings, tmp_path)
    actor = document_role_user_factory("document_verantwortlich")
    document = create_document(
        actor=actor,
        title="Interne Vorlage",
        visibility=Document.Visibility.SELECTED_USERS,
    )

    other_manager = document_role_user_factory("document_verantwortlich")
    client.force_login(other_manager)
    response = client.get(reverse("members:document_download", args=[document.pk]))

    assert response.status_code == 200


def test_sensitive_documents_area_and_download_reject_unauthorized_users(
    client,
    settings,
    tmp_path,
    user_factory,
    document_role_user_factory,
):
    configure_document_media(settings, tmp_path)
    actor = document_role_user_factory("system_admin")
    document = create_document(
        actor=actor,
        title="Burschenprotokoll",
        visibility=Document.Visibility.HIGHLY_SENSITIVE,
    )

    regular_user = user_factory()
    client.force_login(regular_user)

    sensitive_page = client.get(reverse("members:documents_sensitive"))
    direct_download = client.get(reverse("members:document_download", args=[document.pk]))

    assert sensitive_page.status_code == 403
    assert direct_download.status_code == 404


def test_document_upload_rejects_double_extension(
    client,
    settings,
    tmp_path,
    document_role_user_factory,
):
    configure_document_media(settings, tmp_path)
    editor = document_role_user_factory("web_aktuar")
    client.force_login(editor)

    response = client.post(
        reverse("cms:document_create"),
        {
            "title": "Unsicher",
            "description": "Datei mit doppelter Endung",
            "category": "",
            "visibility": Document.Visibility.ALL_MEMBERS,
            "allowed_groups": [],
            "allowed_users": [],
            "status": Document.Status.DRAFT,
            "published_at": "",
            "valid_until": "",
            "version_counter": 0,
            "change_note": "",
            "file_upload": SimpleUploadedFile(
                "reglement.pdf.exe",
                PDF_BYTES,
                content_type="application/octet-stream",
            ),
            "workflow_action": "save",
        },
    )

    assert response.status_code == 400
    assert "Mehrfache oder ausfuehrbare Dateiendungen sind nicht erlaubt." in (
        response.content.decode()
    )


def test_document_version_activation_switches_current_version(
    client,
    settings,
    tmp_path,
    document_role_user_factory,
):
    configure_document_media(settings, tmp_path)
    editor = document_role_user_factory("document_verantwortlich")
    document = create_document(actor=editor, title="Versionierte Unterlage")
    second_version = document.versions.create(
        version_number=2,
        file=pdf_upload("version-2.pdf"),
        original_filename="version-2.pdf",
        mime_type="application/pdf",
        file_size=len(PDF_BYTES),
        checksum="def456",
        change_note="Aktualisierung",
        uploaded_by=editor,
    )
    document.current_version = second_version
    document.version_counter = 2
    document.save(update_fields=["current_version", "version_counter", "updated_at"])

    client.force_login(editor)
    response = client.post(
        reverse(
            "cms:document_version_activate",
            args=[document.pk, document.versions.get(version_number=1).pk],
        )
    )
    document.refresh_from_db()

    assert response.status_code == 302
    assert document.current_version.version_number == 1


def test_bootstrap_roles_assign_document_permissions():
    call_command("bootstrap_roles")

    document_manager = Group.objects.get(name="document_verantwortlich")
    web_aktuar = Group.objects.get(name="web_aktuar")
    system_admin = Group.objects.get(name="system_admin")

    assert document_manager.permissions.filter(
        content_type__app_label="documents",
        codename="manage_publication_documents",
    ).exists()
    assert web_aktuar.permissions.filter(
        content_type__app_label="documents",
        codename="view_document",
    ).exists()
    assert not web_aktuar.permissions.filter(
        content_type__app_label="documents",
        codename="manage_sensitive_documents",
    ).exists()
    assert system_admin.permissions.filter(
        content_type__app_label="documents",
        codename="manage_sensitive_documents",
    ).exists()
