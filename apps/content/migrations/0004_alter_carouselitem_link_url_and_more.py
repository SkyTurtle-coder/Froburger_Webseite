import apps.content.validators
from django.db import migrations, models


class Migration(migrations.Migration):
    dependencies = [
        ("content", "0003_allow_people_list_on_standard_page"),
    ]

    operations = [
        migrations.AlterField(
            model_name="carouselitem",
            name="link_url",
            field=models.CharField(
                blank=True,
                max_length=500,
                validators=[apps.content.validators.validate_internal_or_absolute_url],
                verbose_name="Link-URL",
            ),
        ),
        migrations.AlterField(
            model_name="pagesection",
            name="link_url",
            field=models.CharField(
                blank=True,
                max_length=500,
                validators=[apps.content.validators.validate_internal_or_absolute_url],
                verbose_name="Link-URL",
            ),
        ),
        migrations.AlterField(
            model_name="post",
            name="cta_url",
            field=models.CharField(
                blank=True,
                max_length=500,
                validators=[apps.content.validators.validate_internal_or_absolute_url],
                verbose_name="Call-to-Action URL",
            ),
        ),
        migrations.AlterField(
            model_name="postblock",
            name="link_url",
            field=models.CharField(
                blank=True,
                max_length=500,
                validators=[apps.content.validators.validate_internal_or_absolute_url],
                verbose_name="Link-URL",
            ),
        ),
    ]
