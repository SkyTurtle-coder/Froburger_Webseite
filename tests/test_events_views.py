from datetime import timedelta

import pytest
from django.contrib.auth.models import Group
from django.core.management import call_command
from django.urls import reverse
from django.utils import timezone

from apps.events.models import Event, EventCategory

pytestmark = pytest.mark.django_db


@pytest.fixture
def event_role_user_factory(user_factory):
    call_command("bootstrap_roles")

    def factory(role_name=None):
        user = user_factory()
        if role_name:
            user.groups.add(Group.objects.get(name=role_name))
        return user

    return factory


@pytest.fixture
def event_category():
    return EventCategory.objects.create(name="Vortrag", slug="vortrag")


def create_event(
    *,
    title="Oeffentlicher Anlass",
    slug="oeffentlicher-anlass",
    visibility=Event.Visibility.PUBLIC,
    status=Event.Status.PUBLISHED,
    start_offset_days=7,
    end_offset_days=7,
    category=None,
):
    start_at = timezone.now() + timedelta(days=start_offset_days)
    end_at = start_at + timedelta(hours=3 + max(end_offset_days - start_offset_days, 0) * 24)
    event = Event.objects.create(
        title=title,
        slug=slug,
        short_description=f"Kurztext zu {title}",
        description=f"Ausfuehrliche Beschreibung zu {title}.",
        start_at=start_at,
        end_at=end_at,
        timezone_name="Europe/Zurich",
        location_name="Basel",
        location_address="Muensterplatz 1\n4051 Basel",
        visibility=visibility,
        status=status,
        published_at=timezone.now() - timedelta(days=1)
        if status in {Event.Status.PUBLISHED, Event.Status.CANCELLED, Event.Status.COMPLETED}
        else None,
        scheduled_for=timezone.now() - timedelta(hours=1)
        if status == Event.Status.SCHEDULED
        else None,
        category=category,
    )
    return event


def test_public_events_page_shows_public_events_only(client, event_category):
    create_event(category=event_category)
    create_event(
        title="Interner Stamm",
        slug="interner-stamm",
        visibility=Event.Visibility.MEMBERS,
    )

    response = client.get(reverse("core:events"))
    content = response.content.decode()

    assert response.status_code == 200
    assert "Oeffentlicher Anlass" in content
    assert "Interner Stamm" not in content


def test_public_event_detail_returns_404_for_internal_event(client):
    create_event(
        title="Interner Stamm",
        slug="interner-stamm",
        visibility=Event.Visibility.MEMBERS,
    )

    response = client.get(reverse("core:event_detail", args=["interner-stamm"]))

    assert response.status_code == 404


def test_member_events_page_shows_members_event_to_authenticated_user(client, user_factory):
    user = user_factory()
    create_event(
        title="Interner Stamm",
        slug="interner-stamm",
        visibility=Event.Visibility.MEMBERS,
    )
    client.force_login(user)

    response = client.get(reverse("members:events"))

    assert response.status_code == 200
    assert "Interner Stamm" in response.content.decode()


def test_selected_group_event_is_only_visible_for_matching_member(client, user_factory):
    privileged_group = Group.objects.create(name="Chargia")
    event = create_event(
        title="Chargensitzung",
        slug="chargensitzung",
        visibility=Event.Visibility.SELECTED_GROUPS,
    )
    event.allowed_groups.add(privileged_group)
    allowed_user = user_factory()
    allowed_user.groups.add(privileged_group)
    other_user = user_factory()

    client.force_login(allowed_user)
    allowed_response = client.get(reverse("members:event_detail", args=[event.slug]))

    client.force_login(other_user)
    denied_response = client.get(reverse("members:event_detail", args=[event.slug]))

    assert allowed_response.status_code == 200
    assert denied_response.status_code == 404


def test_public_and_internal_calendar_feeds_respect_visibility(client, user_factory):
    create_event(title="Oeffentlicher Anlass", slug="oeffentlicher-anlass")
    create_event(
        title="Interner Stamm",
        slug="interner-stamm",
        visibility=Event.Visibility.MEMBERS,
    )

    public_feed = client.get(reverse("core:calendar_ics"))
    anonymous_internal = client.get(reverse("members:calendar_ics"))

    user = user_factory()
    client.force_login(user)
    member_feed = client.get(reverse("members:calendar_ics"))

    assert public_feed.status_code == 200
    assert "Oeffentlicher Anlass" in public_feed.content.decode()
    assert "Interner Stamm" not in public_feed.content.decode()
    assert anonymous_internal.status_code == 302
    assert member_feed.status_code == 200
    assert "Interner Stamm" in member_feed.content.decode()


def test_cancelled_event_ics_marks_status_cancelled(client):
    create_event(
        title="Abgesagter Anlass",
        slug="abgesagter-anlass",
        status=Event.Status.CANCELLED,
    )

    response = client.get(reverse("core:event_ics", args=["abgesagter-anlass"]))

    assert response.status_code == 200
    assert "STATUS:CANCELLED" in response.content.decode()


def test_event_cms_routes_require_event_permissions(client, event_role_user_factory):
    url = reverse("cms:event_list")

    anonymous_response = client.get(url)

    member = event_role_user_factory("member")
    client.force_login(member)
    member_response = client.get(url)

    event_manager = event_role_user_factory("event_verantwortlich")
    client.force_login(event_manager)
    manager_response = client.get(url)

    web_aktuar = event_role_user_factory("web_aktuar")
    client.force_login(web_aktuar)
    web_aktuar_response = client.get(url)

    assert anonymous_response.status_code == 302
    assert member_response.status_code == 403
    assert manager_response.status_code == 200
    assert web_aktuar_response.status_code == 200


def test_web_x_can_create_event_in_cms(client, event_role_user_factory, event_category):
    user = event_role_user_factory("web_aktuar")
    client.force_login(user)
    start_at = timezone.localtime(timezone.now() + timedelta(days=14)).strftime("%Y-%m-%dT%H:%M")
    end_at = timezone.localtime(timezone.now() + timedelta(days=14, hours=3)).strftime(
        "%Y-%m-%dT%H:%M"
    )

    response = client.post(
        reverse("cms:event_create"),
        {
            "title": "Cantusabend",
            "slug": "cantusabend",
            "short_description": "Interner Cantusabend",
            "description": "Ein Abend mit Cantus und Diskussion.",
            "start_at": start_at,
            "end_at": end_at,
            "timezone_name": "Europe/Zurich",
            "location_name": "Basel",
            "location_address": "Petersplatz 1",
            "map_url": "",
            "category": event_category.pk,
            "visibility": Event.Visibility.PUBLIC,
            "allowed_groups": [],
            "allowed_users": [],
            "status": Event.Status.DRAFT,
            "published_at": "",
            "scheduled_for": "",
            "signup_url": "",
            "signup_deadline": "",
            "max_participants": "",
            "version_number": 1,
            "change_reason": "Initial",
            "workflow_action": "publish",
        },
    )

    created_event = Event.objects.get(slug="cantusabend")

    assert response.status_code == 302
    assert response.url == reverse("cms:event_edit", args=[created_event.pk])
    assert created_event.status == Event.Status.PUBLISHED
