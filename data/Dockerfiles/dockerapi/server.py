from flask import Flask
from flask_restful import Resource, Api
from flask import jsonify
from flask import request
from threading import Thread
from OpenSSL import crypto
import docker
import uuid
import signal
import time
import os
import re
import sys
import ssl
import socket

docker_client = docker.DockerClient(base_url='unix://var/run/docker.sock', version='auto')
app = Flask(__name__)
api = Api(app)

class containers_get(Resource):
  def get(self):
    containers = {}
    try:
      for container in docker_client.containers.list(all=True):
        containers.update({container.attrs['Id']: container.attrs})
      return containers
    except Exception as e:
      return jsonify(type='danger', msg=str(e))

class container_get(Resource):
  def get(self, container_id):
    if container_id and container_id.isalnum():
      try:
        for container in docker_client.containers.list(all=True, filters={"id": container_id}):
          return container.attrs
      except Exception as e:
          return jsonify(type='danger', msg=str(e))
    else:
      return jsonify(type='danger', msg='no or invalid id defined')

class container_post(Resource):
  def post(self, container_id, post_action):
    if container_id and container_id.isalnum() and post_action:
      if post_action == 'stop':
        try:
          for container in docker_client.containers.list(all=True, filters={"id": container_id}):
            container.stop()
          return jsonify(type='success', msg='command completed successfully')
        except Exception as e:
          return jsonify(type='danger', msg=str(e))

      elif post_action == 'start':
        try:
          for container in docker_client.containers.list(all=True, filters={"id": container_id}):
            container.start()
          return jsonify(type='success', msg='command completed successfully')
        except Exception as e:
          return jsonify(type='danger', msg=str(e))

      elif post_action == 'restart':
        try:
          for container in docker_client.containers.list(all=True, filters={"id": container_id}):
            container.restart()
          return jsonify(type='success', msg='command completed successfully')
        except Exception as e:
          return jsonify(type='danger', msg=str(e))

      elif post_action == 'exec':

        if not request.json or not 'cmd' in request.json:
          return jsonify(type='danger', msg='cmd is missing')

        if request.json['cmd'] == 'df' and request.json['dir']:
          try:
            for container in docker_client.containers.list(filters={"id": container_id}):
              # Should be changed to be able to validate a path
              directory = re.sub('[^0-9a-zA-Z/]+', '', request.json['dir'])
              df_return = container.exec_run(["/bin/bash", "-c", "/bin/df -H " + directory + " | /usr/bin/tail -n1 | /usr/bin/tr -s [:blank:] | /usr/bin/tr ' ' ','"], user='nobody')
              if df_return.exit_code == 0:
                return df_return.output.rstrip()
              else:
                return "0,0,0,0,0,0"
          except Exception as e:
            return jsonify(type='danger', msg=str(e))
        elif request.json['cmd'] == 'sieve_list' and request.json['username']:
          try:
            for container in docker_client.containers.list(filters={"id": container_id}):
              sieve_return = container.exec_run(["/bin/bash", "-c", "/usr/local/bin/doveadm sieve list -u '" + request.json['username'].replace("'", "'\\''") + "'"])
              return sieve_return.output
          except Exception as e:
            return jsonify(type='danger', msg=str(e))
        elif request.json['cmd'] == 'sieve_print' and request.json['script_name'] and request.json['username']:
          try:
            for container in docker_client.containers.list(filters={"id": container_id}):
              sieve_return = container.exec_run(["/bin/bash", "-c", "/usr/local/bin/doveadm sieve get -u '" + request.json['username'].replace("'", "'\\''") + "' '" + request.json['script_name'].replace("'", "'\\''") + "'"])
              return sieve_return.output
          except Exception as e:
            return jsonify(type='danger', msg=str(e))
        # not in use...
        elif request.json['cmd'] == 'mail_crypt_generate' and request.json['username'] and request.json['old_password'] and request.json['new_password']:
          try:
            for container in docker_client.containers.list(filters={"id": container_id}):
              # create if missing
              crypto_generate = container.exec_run(["/bin/bash", "-c", "/usr/local/bin/doveadm mailbox cryptokey generate -u '" + request.json['username'].replace("'", "'\\''") + "' -URf"], user='vmail')
              if crypto_generate.exit_code == 0:
                # open a shell, bind stdin and return socket
                cryptokey_shell = container.exec_run(["/bin/bash"], stdin=True, socket=True, user='vmail')
                # command to be piped to shell
                cryptokey_cmd = "/usr/local/bin/doveadm mailbox cryptokey password -u '" + request.json['username'].replace("'", "'\\''") + "' -n '" + request.json['new_password'].replace("'", "'\\''") + "' -o '" + request.json['old_password'].replace("'", "'\\''") + "'\n"
                # socket is .output
                cryptokey_socket = cryptokey_shell.output;
                try :
                  # send command utf-8 encoded
                  cryptokey_socket.sendall(cryptokey_cmd.encode('utf-8'))
                  # we won't send more data than this
                  cryptokey_socket.shutdown(socket.SHUT_WR)
                except socket.error:
                  # exit on socket error
                  return jsonify(type='danger', msg=str('socket error'))
                # read response
                cryptokey_response = recv_socket_data(cryptokey_socket)
                crypto_error = re.search('dcrypt_key_load_private.+failed.+error', cryptokey_response)
                if crypto_error is not None:
                  return jsonify(type='danger', msg=str("dcrypt_key_load_private error"))
                return jsonify(type='success', msg=str("key pair generated"))
              else:
                return jsonify(type='danger', msg=str(crypto_generate.output))
          except Exception as e:
            return jsonify(type='danger', msg=str(e))
        elif request.json['cmd'] == 'maildir_cleanup' and request.json['maildir']:
          try:
            for container in docker_client.containers.list(filters={"id": container_id}):
              sane_name = re.sub(r'\W+', '', request.json['maildir'])
              maildir_cleanup = container.exec_run(["/bin/bash", "-c", "if [[ -d '/var/vmail/" + request.json['maildir'].replace("'", "'\\''") + "' ]]; then /bin/mv '/var/vmail/" + request.json['maildir'].replace("'", "'\\''") + "' '/var/vmail/_garbage/" + str(int(time.time())) + "_" + sane_name + "'; fi"], user='vmail')
              if maildir_cleanup.exit_code == 0:
                return jsonify(type='success', msg=str("moved to garbage"))
              else:
                return jsonify(type='danger', msg=str(maildir_cleanup.output))
          except Exception as e:
            return jsonify(type='danger', msg=str(e))
        elif request.json['cmd'] == 'worker_password' and request.json['raw']:
          try:
            for container in docker_client.containers.list(filters={"id": container_id}):
              worker_shell = container.exec_run(["/bin/bash"], stdin=True, socket=True, user='_rspamd')
              worker_cmd = "/usr/bin/rspamadm pw -e -p '" + request.json['raw'].replace("'", "'\\''") + "' 2> /dev/null\n"
              worker_socket = worker_shell.output;
              try :
                worker_socket.sendall(worker_cmd.encode('utf-8'))
                worker_socket.shutdown(socket.SHUT_WR)
              except socket.error:
                return jsonify(type='danger', msg=str('socket error'))
              worker_response = recv_socket_data(worker_socket)
              matched = False
              for line in worker_response.split("\n"):
                if '$2$' in line:
                  matched = True
                  hash = line.strip()
                  hash_out = re.search('\$2\$.+$', hash).group(0)
                  f = open("/access.inc", "w")
                  f.write('enable_password = "' + re.sub('[^0-9a-zA-Z\$]+', '', hash_out.rstrip()) + '";\n')
                  f.close()
                  container.restart()
              if matched:
                return jsonify(type='success', msg='command completed successfully')
              else:
                return jsonify(type='danger', msg='command did not complete')
          except Exception as e:
            return jsonify(type='danger', msg=str(e))
        elif request.json['cmd'] == 'mailman_password' and request.json['email'] and request.json['passwd']:
          try:
            for container in docker_client.containers.list(filters={"id": container_id}):
              add_su = container.exec_run(["/bin/bash", "-c", "/opt/mm_web/add_su.py '" + request.json['passwd'].replace("'", "'\\''") + "' '" + request.json['email'].replace("'", "'\\''") + "'"], user='mailman')
              if add_su.exit_code == 0:
                return jsonify(type='success', msg='command completed successfully')
              else:
                return jsonify(type='danger', msg='command did not complete, exit code was ' + int(add_su.exit_code))
          except Exception as e:
            return jsonify(type='danger', msg=str(e))

        else:
          return jsonify(type='danger', msg='Unknown command')

      else:
        return jsonify(type='danger', msg='invalid action')

    else:
      return jsonify(type='danger', msg='invalid container id or missing action')

class GracefulKiller:
  kill_now = False
  def __init__(self):
    signal.signal(signal.SIGINT, self.exit_gracefully)
    signal.signal(signal.SIGTERM, self.exit_gracefully)

  def exit_gracefully(self, signum, frame):
    self.kill_now = True

def startFlaskAPI():
  create_self_signed_cert()
  try:
    ctx = ssl.create_default_context(ssl.Purpose.CLIENT_AUTH)
    ctx.check_hostname = False
    ctx.load_cert_chain(certfile='/cert.pem', keyfile='/key.pem')
  except:
    print "Cannot initialize TLS, retrying in 5s..."
    time.sleep(5)
  app.run(debug=False, host='0.0.0.0', port=443, threaded=True, ssl_context=ctx)

def recv_socket_data(c_socket, timeout=10):
  c_socket.setblocking(0)
  total_data=[];
  data='';
  begin=time.time()
  while True:
    if total_data and time.time()-begin > timeout:
      break
    elif time.time()-begin > timeout*2:
      break
    try:
      data = c_socket.recv(8192)
      if data:
        total_data.append(data)
        #change the beginning time for measurement
        begin=time.time()
      else:
        #sleep for sometime to indicate a gap
        time.sleep(0.1)
        break
    except:
      pass
  return ''.join(total_data)

def create_self_signed_cert():
  pkey = crypto.PKey()
  pkey.generate_key(crypto.TYPE_RSA, 2048)
  cert = crypto.X509()
  cert.get_subject().O = "mailcow"
  cert.get_subject().CN = "dockerapi"
  cert.set_serial_number(int(uuid.uuid4()))
  cert.gmtime_adj_notBefore(0)
  cert.gmtime_adj_notAfter(10*365*24*60*60)
  cert.set_issuer(cert.get_subject())
  cert.set_pubkey(pkey)
  cert.sign(pkey, 'sha512')
  cert = crypto.dump_certificate(crypto.FILETYPE_PEM, cert)
  pkey = crypto.dump_privatekey(crypto.FILETYPE_PEM, pkey)
  with os.fdopen(os.open('/cert.pem', os.O_WRONLY | os.O_CREAT, 0o644), 'w') as handle:
    handle.write(cert)
  with os.fdopen(os.open('/key.pem', os.O_WRONLY | os.O_CREAT, 0o600), 'w') as handle:
    handle.write(pkey)

api.add_resource(containers_get, '/containers/json')
api.add_resource(container_get, '/containers/<string:container_id>/json')
api.add_resource(container_post, '/containers/<string:container_id>/<string:post_action>')

if __name__ == '__main__':
  api_thread = Thread(target=startFlaskAPI)
  api_thread.daemon = True
  api_thread.start()
  killer = GracefulKiller()
  while True:
    time.sleep(1)
    if killer.kill_now:
      break
  print "Stopping dockerapi-mailcow"

