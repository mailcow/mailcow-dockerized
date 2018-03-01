from flask import Flask
from flask_restful import Resource, Api
from flask import jsonify
from flask import request
from threading import Thread
import docker
import signal
import time
import os
import re
import sys

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
              sieve_return = container.exec_run(["/bin/bash", "-c", "/usr/local/bin/doveadm sieve list -u '" + request.json['username'].replace("'", "'\\''") + "'"], user='vmail')
              return sieve_return.output
          except Exception as e:
            return jsonify(type='danger', msg=str(e))
        elif request.json['cmd'] == 'sieve_print' and request.json['script_name'] and request.json['username']:
          try:
            for container in docker_client.containers.list(filters={"id": container_id}):
              return container.exec_run(["/bin/bash", "-c", "/usr/local/bin/doveadm sieve get -u '" + request.json['username'].replace("'", "'\\''") + "' '" + request.json['script_name'].replace("'", "'\\''") + "'"], user='vmail')
          except Exception as e:
            return jsonify(type='danger', msg=str(e))
        elif request.json['cmd'] == 'worker_password' and request.json['raw']:
          try:
            for container in docker_client.containers.list(filters={"id": container_id}):
              hash = container.exec_run(["/bin/bash", "-c", "/usr/bin/rspamadm pw -e -p '" + request.json['raw'].replace("'", "'\\''") + "' 2> /dev/null"], user='_rspamd')
              if hash.exit_code == 0:
                hash = str(hash.output)
                f = open("/access.inc", "w")
                f.write('enable_password = "' + re.sub('[^0-9a-zA-Z\$]+', '', hash.rstrip()) + '";\n')
                f.close()
                container.restart()
                return jsonify(type='success', msg='command completed successfully')
              else:
                return jsonify(type='danger', msg='command did not complete, exit code was ' + int(hash.exit_code))
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

  def exit_gracefully(self,signum, frame):
    self.kill_now = True

def startFlaskAPI():
  app.run(debug=False, host='0.0.0.0', port=8080, threaded=True)

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

