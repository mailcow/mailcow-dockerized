# -*- coding: utf-8 -*-
# Copyright (C) 1998-2016 by the Free Software Foundation, Inc.
#
# This file is part of Mailman Suite.
#
# Mailman Suite is free sofware: you can redistribute it and/or modify it
# under the terms of the GNU General Public License as published by the Free
# Software Foundation, either version 3 of the License, or (at your option)
# any later version.
#
# Mailman Suite is distributed in the hope that it will be useful, but
# WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
# or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License
# for more details.

# You should have received a copy of the GNU General Public License along
# with Mailman Suite.  If not, see <http://www.gnu.org/licenses/>.
"""
Django Settings for Mailman Suite (hyperkitty + postorius)

For more information on this file, see
https://docs.djangoproject.com/en/1.8/topics/settings/

For the full list of settings and their values, see
https://docs.djangoproject.com/en/1.8/ref/settings/
"""

# Build paths inside the project like this: os.path.join(BASE_DIR, ...)
import os
import socket
import dj_database_url
import string
import hashlib
import time
from random import *

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

# SECURITY WARNING: keep the secret key used in production secret!
with open('/opt/mailman/django_secret.key', 'r') as myfile:
    SECRET_KEY=myfile.read().replace('\n', '')

# SECURITY WARNING: don't run with debug turned on in production!
DEBUG = False

ADMINS = (
     ('Mailman Suite Admin', 'null@localhost'),
)

SITE_ID = 1


FORCE_SCRIPT_NAME = '/lists'

# Hosts/domain names that are valid for this site; required if DEBUG is False
# See https://docs.djangoproject.com/en/1.8/ref/settings/#allowed-hosts

ALLOWED_HOSTS = [
    "localhost",  # Archiving API from Mailman, keep it.
    "mm-web",
    "mm-core",
    os.environ.get('MAILCOW_HOSTNAME'),
]

#ALLOWED_HOSTS = ['*']

# Try to get the address of Mailman Core automatically.
MAILMAN_HOST_IP_AUTO = None
while MAILMAN_HOST_IP_AUTO is None:
  try:
    MAILMAN_HOST_IP_AUTO = socket.gethostbyname('mm-core')
  except:
    time.sleep(3)
    print("Retrying to resolve '%s' in 3s" % ('mm-core'))

# Mailman API credentials
MAILMAN_REST_API_URL = 'http://mm-core:8001'
MAILMAN_REST_API_USER = 'restadmin'
MRAP_RAW='ws_' + os.environ.get('DBPASS') + '_' + os.environ.get('MAILCOW_HOSTNAME')
MAILMAN_REST_API_PASS = hashlib.sha256(MRAP_RAW).hexdigest()[0:16]
MAK_RAW='hk_' + os.environ.get('DBPASS') + '_' + os.environ.get('MAILCOW_HOSTNAME')
MAILMAN_ARCHIVER_KEY = hashlib.sha256(MAK_RAW).hexdigest()[0:16]
MAILMAN_ARCHIVER_FROM = (MAILMAN_HOST_IP_AUTO)

# Application definition

INSTALLED_APPS = (
    'hyperkitty',
    'postorius',
    'django_mailman3',
    # Uncomment the next line to enable the admin:
    'django.contrib.admin',
    # Uncomment the next line to enable admin documentation:
    # 'django.contrib.admindocs',
    'django.contrib.auth',
    'django.contrib.contenttypes',
    'django.contrib.sessions',
    'django.contrib.sites',
    'django.contrib.messages',
    'django.contrib.staticfiles',
    'rest_framework',
    'django_gravatar',
    'paintstore',
    'compressor',
    'haystack',
    'django_extensions',
    'django_q',
    'allauth',
    'allauth.account',
    'allauth.socialaccount',
    'django_mailman3.lib.auth.fedora',
    'allauth.socialaccount.providers.openid',
    'allauth.socialaccount.providers.github',
    'allauth.socialaccount.providers.gitlab',
    'allauth.socialaccount.providers.google',
)

MIDDLEWARE_CLASSES = (
    'django.contrib.sessions.middleware.SessionMiddleware',
    'django.middleware.common.CommonMiddleware',
    'django.middleware.csrf.CsrfViewMiddleware',
    'django.middleware.locale.LocaleMiddleware',
    'django.contrib.auth.middleware.AuthenticationMiddleware',
    'django.contrib.auth.middleware.SessionAuthenticationMiddleware',
    'django.contrib.messages.middleware.MessageMiddleware',
    'django.middleware.clickjacking.XFrameOptionsMiddleware',
    'django.middleware.security.SecurityMiddleware',
    'django_mailman3.middleware.TimezoneMiddleware',
    'postorius.middleware.PostoriusMiddleware',
)

ROOT_URLCONF = 'urls'

TEMPLATES = [
    {
        'BACKEND': 'django.template.backends.django.DjangoTemplates',
        'DIRS': [],
        'APP_DIRS': True,
        'OPTIONS': {
            'context_processors': [
                'django.template.context_processors.debug',
                'django.template.context_processors.i18n',
                'django.template.context_processors.media',
                'django.template.context_processors.static',
                'django.template.context_processors.tz',
                'django.template.context_processors.csrf',
                'django.template.context_processors.request',
                'django.contrib.auth.context_processors.auth',
                'django.contrib.messages.context_processors.messages',
                'django_mailman3.context_processors.common',
                'hyperkitty.context_processors.common',
                'postorius.context_processors.postorius',
            ],
        },
    },
]

WSGI_APPLICATION = 'wsgi.application'


# Database
# https://docs.djangoproject.com/en/1.8/ref/settings/#databases


# This uses $DATABASE_URL from the environment variable to create a
# django-style-config-dict.
# https://github.com/kennethreitz/dj-database-url
DATABASES = {
    'default': dj_database_url.config(conn_max_age=600)
}

# If you're behind a proxy, use the X-Forwarded-Host header
# See https://docs.djangoproject.com/en/1.8/ref/settings/#use-x-forwarded-host
USE_X_FORWARDED_HOST = True

# Password validation
# https://docs.djangoproject.com/en/1.9/ref/settings/#auth-password-validators
AUTH_PASSWORD_VALIDATORS = [
    {
        'NAME':
'django.contrib.auth.password_validation.UserAttributeSimilarityValidator',
    },
    {
        'NAME':
'django.contrib.auth.password_validation.MinimumLengthValidator',
    },
    {
        'NAME':
'django.contrib.auth.password_validation.CommonPasswordValidator',
    },
    {
        'NAME':
'django.contrib.auth.password_validation.NumericPasswordValidator',
    },
]

# Internationalization
# https://docs.djangoproject.com/en/1.8/topics/i18n/

LANGUAGE_CODE = 'en-us'

TIME_ZONE = os.environ.get('TZ', 'Europe/Berlin')

USE_I18N = True

USE_L10N = True

USE_TZ = True

STATIC_ROOT = '/opt/mm_web-data/static'

STATIC_URL = '/mm_static/'

# List of finder classes that know how to find static files in
# various locations.
STATICFILES_FINDERS = (
    'django.contrib.staticfiles.finders.FileSystemFinder',
    'django.contrib.staticfiles.finders.AppDirectoriesFinder',
    'compressor.finders.CompressorFinder',
)

SESSION_SERIALIZER = 'django.contrib.sessions.serializers.PickleSerializer'

LOGIN_URL = 'account_login'
LOGIN_REDIRECT_URL = 'list_index'
LOGOUT_URL = 'account_logout'

# Use SERVE_FROM_DOMAIN as the default domain in the email.
hostname = os.environ.get('MAILCOW_HOSTNAME', 'localhost.local')
DEFAULT_FROM_EMAIL = 'postorius@{}'.format(hostname)
SERVER_EMAIL = 'lists-server@{}'.format(hostname)

# Change this when you have a real email backend
EMAIL_BACKEND = 'django.core.mail.backends.smtp.EmailBackend'
EMAIL_HOST = os.environ.get('SMTP_HOST', 'postfix')
EMAIL_PORT = os.environ.get('SMTP_PORT', 588)
EMAIL_HOST_USER = ''
EMAIL_HOST_PASSWORD = ''
EMAIL_USE_TLS = False

# Compatibility with Bootstrap 3
from django.contrib.messages import constants as messages  # flake8: noqa
MESSAGE_TAGS = {
    messages.ERROR: 'danger'
}


#
# Social auth
#
AUTHENTICATION_BACKENDS = (
    'django.contrib.auth.backends.ModelBackend',
    'allauth.account.auth_backends.AuthenticationBackend',
)

# Django Allauth
ACCOUNT_AUTHENTICATION_METHOD = "username_email"
ACCOUNT_EMAIL_REQUIRED = True
ACCOUNT_EMAIL_VERIFICATION = "mandatory"
# You probably want https in production, but this is a dev setup file
ACCOUNT_DEFAULT_HTTP_PROTOCOL = "https"
ACCOUNT_UNIQUE_EMAIL  = True

SOCIALACCOUNT_PROVIDERS = {
    'openid': {
        'SERVERS': [
            dict(id='yahoo',
                 name='Yahoo',
                 openid_url='http://me.yahoo.com'),
        ],
    },
    'google': {
        'SCOPE': ['profile', 'email'],
        'AUTH_PARAMS': {'access_type': 'online'},
    },
    'facebook': {
       'METHOD': 'oauth2',
       'SCOPE': ['email'],
       'FIELDS': [
           'email',
           'name',
           'first_name',
           'last_name',
           'locale',
           'timezone',
           ],
       'VERSION': 'v2.4',
    },
}


# django-compressor
# https://pypi.python.org/pypi/django_compressor
#
COMPRESS_PRECOMPILERS = (
   ('text/less', 'lessc {infile} {outfile}'),
   ('text/x-scss', 'sassc -t compressed {infile} {outfile}'),
   ('text/x-sass', 'sassc -t compressed {infile} {outfile}'),
)

# On a production setup, setting COMPRESS_OFFLINE to True will bring a
# significant performance improvement, as CSS files will not need to be
# recompiled on each requests. It means running an additional "compress"
# management command after each code upgrade.
# http://django-compressor.readthedocs.io/en/latest/usage/#offline-compression
# COMPRESS_OFFLINE = True

#
# Full-text search engine
#
HAYSTACK_CONNECTIONS = {
    'default': {
        'ENGINE': 'haystack.backends.whoosh_backend.WhooshEngine',
        'PATH': "/opt/mm_web-data/fulltext_index",
        # You can also use the Xapian engine, it's faster and more accurate,
        # but requires another library.
        # http://django-haystack.readthedocs.io/en/v2.4.1/installing_search_engines.html#xapian
        # Example configuration for Xapian:
        #'ENGINE': 'xapian_backend.XapianEngine'
    },
}

import sys
# A sample logging configuration. The only tangible logging
# performed by this configuration is to send an email to
# the site admins on every HTTP 500 error when DEBUG=False.
# See http://docs.djangoproject.com/en/dev/topics/logging for
# more details on how to customize your logging configuration.
LOGGING = {
    'version': 1,
    'disable_existing_loggers': False,
    'filters': {
        'require_debug_false': {
            '()': 'django.utils.log.RequireDebugFalse'
        }
    },
    'handlers': {
        'mail_admins': {
            'level': 'ERROR',
            'filters': ['require_debug_false'],
            'class': 'django.utils.log.AdminEmailHandler'
        },
        'file':{
            'level': 'INFO',
            'class': 'logging.handlers.RotatingFileHandler',
            #'class': 'logging.handlers.WatchedFileHandler',
            'filename': '/opt/mm_web/django.log',
            'formatter': 'verbose',
        },
        'console': {
            'class': 'logging.StreamHandler',
            'formatter': 'simple',
            'level': 'INFO',
            'stream': sys.stdout,
        },
    },
    'loggers': {
        'django.request': {
            'handlers': ['mail_admins', 'console'],
            'level': 'INFO',
            'propagate': True,
        },
        'django': {
            'handlers': ['console'],
            'level': 'INFO',
            'propagate': True,
        },
        'hyperkitty': {
            'handlers': ['console'],
            'level': 'INFO',
            'propagate': True,
        },
        'postorius': {
            'handlers': ['console'],
            'level': 'INFO',
            'propagate': True
        },
    },
    'formatters': {
        'verbose': {
            'format': '%(levelname)s %(asctime)s %(process)d %(name)s %(message)s'
        },
        'simple': {
            'format': '%(levelname)s %(message)s'
        },
    },
    #'root': {
    #    'handlers': ['file'],
    #    'level': 'INFO',
    #},
}


# HyperKitty-specific
#
# Only display mailing-lists from the same virtual host as the webserver
FILTER_VHOST = False

Q_CLUSTER = {
    'timeout': 300,
    'save_limit': 100,
    'orm': 'default',
}

try:
    from settings_local import *
except ImportError:
    pass
