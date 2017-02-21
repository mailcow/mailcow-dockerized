#!/usr/bin/env python

import sys
import pwd
import subprocess

BLACKLIST = (
    # add blacklisted users here
    #'user1',
)

try:
    username, password = sys.stdin.readline().split(':', 1)
except ValueError, e:
    sys.exit('Malformed input')

try:
    user = pwd.getpwnam(username)
except KeyError, e:
    sys.exit('No such user: %s' % username)

if user.pw_uid < 1000:
    sys.exit('Changing the password for user id < 1000 is forbidden')

if username in BLACKLIST:
    sys.exit('Changing password for user %s is forbidden (user blacklisted)' %
             username)

handle = subprocess.Popen('/usr/sbin/chpasswd', stdin = subprocess.PIPE)
handle.communicate('%s:%s' % (username, password))

sys.exit(handle.returncode)
