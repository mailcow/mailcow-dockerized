"""
WSGI config for HyperKitty project.

It exposes the WSGI callable as a module-level variable named ``application``.

For more information on this file, see
https://docs.djangoproject.com/en/{{ docs_version }}/howto/deployment/wsgi/
"""

import os

# import sys
# import site

# For some unknown reason, sometimes mod_wsgi fails to set the python paths to
# the virtualenv, with the 'python-path' option. You can do it here too.
#
# # Remember original sys.path.
# prev_sys_path = list(sys.path)
# # Add here, for the settings module
# site.addsitedir(os.path.abspath(os.path.dirname(__file__)))
# # Add the virtualenv
# venv = os.path.join(os.path.abspath(os.path.dirname(__file__)),
#                     '..', 'lib', 'python2.6', 'site-packages')
# site.addsitedir(venv)
# # Reorder sys.path so new directories at the front.
# new_sys_path = []
# for item in list(sys.path):
#     if item not in prev_sys_path:
#         new_sys_path.append(item)
#         sys.path.remove(item)
#         sys.path[:0] = new_sys_path

from django.core.wsgi import get_wsgi_application

os.environ.setdefault("DJANGO_SETTINGS_MODULE", "settings")

application = get_wsgi_application()
