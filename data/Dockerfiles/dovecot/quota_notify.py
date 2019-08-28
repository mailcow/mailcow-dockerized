#!/usr/bin/python

import smtplib
import os
from email.MIMEMultipart import MIMEMultipart
from email.MIMEText import MIMEText
from email.Utils import COMMASPACE, formatdate
import jinja2
from jinja2 import Template
import redis
import time
import sys
import html2text
from subprocess import Popen, PIPE, STDOUT

if len(sys.argv) > 2:
  percent = int(sys.argv[1])
  username = str(sys.argv[2])
else:
  print "Args missing"
  sys.exit(1)

while True:
  try:
    r = redis.StrictRedis(host='redis', decode_responses=True, port=6379, db=0)
    r.ping()
  except Exception as ex:
    print '%s - trying again...'  % (ex)
    time.sleep(3)
  else:
    break

if r.get('QW_HTML'):
  try:
    template = Template(r.get('QW_HTML'))
  except:
    print "Error: Cannot parse quarantine template, falling back to default template."
    with open('/templates/quota.tpl') as file_:
      template = Template(file_.read())
else:
  with open('/templates/quota.tpl') as file_:
    template = Template(file_.read())

html = template.render(username=username, percent=percent)
text = html2text.html2text(html)

try:
  msg = MIMEMultipart('alternative')
  msg['From'] = r.get('QW_SENDER') or "quota-warning@localhost"
  msg['Subject'] = r.get('QW_SUBJ') or "Quota warning"
  msg['Date'] = formatdate(localtime = True)
  text_part = MIMEText(text, 'plain', 'utf-8')
  html_part = MIMEText(html, 'html', 'utf-8')
  msg.attach(text_part)
  msg.attach(html_part)
  msg['To'] = username
  p = Popen(['/usr/lib/dovecot/dovecot-lda', '-d', username, '-o', '"plugin/quota=maildir:User quota:noenforcing"'], stdout=PIPE, stdin=PIPE, stderr=STDOUT)
  p.communicate(input=msg.as_string())

except Exception as ex:
  print 'Failed to send quota notification: %s' % (ex)
  sys.exit(1)

try:
  sys.stdout.close()
except:
  pass

try:
  sys.stderr.close()
except:
  pass
