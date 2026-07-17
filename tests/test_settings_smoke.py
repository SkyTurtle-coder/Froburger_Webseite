from django.conf import settings


def test_language_and_timezone_defaults():
    assert settings.LANGUAGE_CODE == "de-ch"
    assert settings.TIME_ZONE == "Europe/Zurich"


def test_core_apps_are_registered():
    assert "apps.core.apps.CoreConfig" in settings.INSTALLED_APPS
    assert "apps.accounts.apps.AccountsConfig" in settings.INSTALLED_APPS
