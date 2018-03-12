#!/usr/bin/env python
import os
import sys
import getpass
os.environ.setdefault("DJANGO_SETTINGS_MODULE", "settings")

import django
django.setup()

from django.contrib.auth.models import User
from django.db import connection

if len(sys.argv) > 2:
  admin_pass=str(sys.argv[1])
  admin_mail=str(sys.argv[2])
else:
  try:
    admin_pass=str(getpass.getpass('Password: '))
    admin_mail=str(raw_input('Email: '))
  except ValueError:
    print "Invalid input"

User.objects.filter(username='admin').delete()
User.objects.create_superuser('admin', admin_mail, admin_pass)

with connection.cursor() as cursor:
  cursor.execute("REPLACE INTO `account_emailaddress` (`email`, `verified`, `primary`, `user_id`) SELECT `email`, 1, 1, `id` FROM auth_user WHERE username = 'admin'")
