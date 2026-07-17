from django.contrib.auth import get_user_model
from django.db.models.signals import post_save
from django.dispatch import receiver

from .models import MemberProfile

User = get_user_model()


@receiver(post_save, sender=User)
def ensure_member_profile(sender, instance, created, **kwargs):
    if not created:
        return

    status = (
        MemberProfile.MembershipStatus.ACTIVE
        if instance.is_active
        else MemberProfile.MembershipStatus.INVITED
    )
    MemberProfile.objects.get_or_create(
        user=instance,
        defaults={"membership_status": status},
    )
