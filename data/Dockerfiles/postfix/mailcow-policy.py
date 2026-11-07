#!/usr/bin/python3
# Policy service of the mail policies (mailcow-policy in master.cf, spawned by Postfix): reads the policy requests
# of smtpd and asks the mailcow UI for the action (nginx:8081/mailpolicy.php). Without an answer the recipient is
# deferred; smtpd only asks while policies or internal aliases exist (mysql_mail_policy_maps.cf).

import http.client
import re
import socket
import sys
from urllib.parse import urlencode

# one line: DUNNO or a rejection with code and text
valid_action = re.compile(rb'DUNNO|[45]\d\d [45]\.\d{1,3}\.\d{1,3} [\x20-\x7e]{1,200}')
unavailable = b'DEFER_IF_PERMIT Mail policy check temporarily unavailable'
# address of nginx, resolved once per process (a lookup costs more than the check)
nginx = None

def check(request):
  global nginx
  body = urlencode(sorted(request.items())).encode()
  try:
    if nginx is None:
      nginx = socket.gethostbyname('nginx')
    # http.client sets TCP_NODELAY, so the body is not held back until the headers are acknowledged
    # below the timeout of Postfix (main.cf), so an answer always arrives
    conn = http.client.HTTPConnection(nginx, 8081, timeout=8)
    try:
      conn.request('POST', '/mailpolicy.php', body, {'Host': 'nginx', 'Content-Type': 'application/x-www-form-urlencoded'})
      response = conn.getresponse()
      action = response.read()
    finally:
      conn.close()
  except (OSError, http.client.HTTPException):
    # nginx may have a new address after a restart
    nginx = None
    return unavailable
  return action if response.status == 200 and valid_action.fullmatch(action) else unavailable

def main():
  request = {}
  # attributes as bytes: addresses may contain any 8-bit characters
  for line in sys.stdin.buffer:
    line = line.rstrip(b'\r\n')
    if line:
      name, separator, value = line.partition(b'=')
      if separator:
        request[name] = value
      continue
    sys.stdout.buffer.write(b'action=' + check(request) + b'\n\n')
    sys.stdout.buffer.flush()
    request = {}

if __name__ == '__main__':
  main()
