#!/usr/bin/env python
import os
import getpass
os.environ.setdefault("DJANGO_SETTINGS_MODULE", "settings")

import django
django.setup()

from django.contrib.auth.models import User

user = User.objects.get(username='normaluser')

try:
  admin_user=str(raw_input('Username: '))
  admin_pass=str(getpass.getpass('Password: '))
  admin_mail=str(raw_input('Valid email address: '))
except ValueError:
    print "Invalid input"

User.objects.filter(username=admin_user).delete()
User.objects.create_superuser(admin_user, admin_mail, admin_pass)
