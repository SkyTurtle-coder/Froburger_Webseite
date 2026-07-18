import io

import pytest
from django.core.files.uploadedfile import SimpleUploadedFile
from PIL import Image

from apps.accounts.models import User


@pytest.fixture
def user_factory():
    counter = 0

    def factory(**overrides):
        nonlocal counter
        counter += 1
        defaults = {
            "email": f"user{counter}@example.invalid",
            "password": "Secret1234!",
            "first_name": "Test",
            "last_name": f"User{counter}",
        }
        defaults.update(overrides)
        return User.objects.create_user(**defaults)

    return factory


@pytest.fixture
def image_upload_factory():
    def factory(
        filename="image.png",
        *,
        image_format="PNG",
        content_type="image/png",
        size=(40, 30),
        color=(30, 120, 90),
    ):
        buffer = io.BytesIO()
        Image.new("RGB", size, color).save(buffer, format=image_format)
        return SimpleUploadedFile(filename, buffer.getvalue(), content_type=content_type)

    return factory
