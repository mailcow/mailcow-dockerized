from flask import Flask
from flask_restful import Resource, Api
from flask import jsonify
import docker

docker_client = docker.DockerClient(base_url='unix://var/run/docker.sock')
app = Flask(__name__)
api = Api(app)

class containers_get(Resource):
    def get(self):
        containers = {}
        for container in docker_client.containers.list(all=True):
            containers.update({container.attrs['Id']: container.attrs})
        return containers

class container_get(Resource):
    def get(self, container_id):
        if container_id and container_id.isalnum():
            for container in docker_client.containers.list(all=True, filters={"id": container_id}):
                return container.attrs            
        else:
		    return jsonify(message='No or invalid id defined')

class container_post(Resource):
    def post(self, container_id, post_action):
        if container_id and container_id.isalnum() and post_action:
            if post_action == 'stop':
                try:
                    for container in docker_client.containers.list(all=True, filters={"id": container_id}):
                        container.stop()
                except:
                    return 'Error'
                else:
                    return 'OK'
            elif post_action == 'start':
                try:
                    for container in docker_client.containers.list(all=True, filters={"id": container_id}):
                        container.start()
                except:
                    return 'Error'
                else:
                    return 'OK'
            else:
                return jsonify(message='Invalid action')
        else:
            return jsonify(message='Invalid container id or missing action')

api.add_resource(containers_get, '/containers/json')
api.add_resource(container_get, '/containers/<string:container_id>/json')
api.add_resource(container_post, '/containers/<string:container_id>/<string:post_action>')

if __name__ == '__main__':
    app.run(debug=False, host='0.0.0.0', port='8080')
