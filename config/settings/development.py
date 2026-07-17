from .base import *  # noqa: F403
from .base import env_bool

DEBUG = env_bool("DJANGO_DEBUG", True)
EMAIL_BACKEND = "django.core.mail.backends.console.EmailBackend"
