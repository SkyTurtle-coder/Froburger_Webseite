from django.db import migrations


def allow_people_list_on_standard_page(apps, schema_editor):
    LayoutPreset = apps.get_model("content", "LayoutPreset")
    preset = LayoutPreset.objects.filter(scope="page", key="standard_page").first()
    if preset is None:
        return
    allowed_block_types = list(preset.allowed_block_types or [])
    if "people_list" not in allowed_block_types:
        allowed_block_types.append("people_list")
        preset.allowed_block_types = allowed_block_types
        preset.save(update_fields=["allowed_block_types"])


def remove_people_list_from_standard_page(apps, schema_editor):
    LayoutPreset = apps.get_model("content", "LayoutPreset")
    preset = LayoutPreset.objects.filter(scope="page", key="standard_page").first()
    if preset is None:
        return
    allowed_block_types = [
        value for value in (preset.allowed_block_types or []) if value != "people_list"
    ]
    preset.allowed_block_types = allowed_block_types
    preset.save(update_fields=["allowed_block_types"])


class Migration(migrations.Migration):
    dependencies = [
        ("content", "0002_seed_layout_presets"),
    ]

    operations = [
        migrations.RunPython(
            allow_people_list_on_standard_page,
            reverse_code=remove_people_list_from_standard_page,
        ),
    ]
