import dj_database_url

from .base import *  # noqa: F403
from .base import BASE_DIR, env

DEBUG = False
SECRET_KEY = env("DJANGO_SECRET_KEY", "test-secret-key")
if env("TEST_DATABASE_URL"):
    DATABASES = {
        "default": dj_database_url.parse(env("TEST_DATABASE_URL"), conn_max_age=0)
    }
else:
    DATABASES = {
        "default": {
            "ENGINE": "django.db.backends.sqlite3",
            "NAME": BASE_DIR / "test.sqlite3",
        }
    }
EMAIL_BACKEND = "django.core.mail.backends.locmem.EmailBackend"
PASSWORD_HASHERS = ["django.contrib.auth.hashers.MD5PasswordHasher"]
