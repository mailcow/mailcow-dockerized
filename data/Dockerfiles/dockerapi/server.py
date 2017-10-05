from flask import Flask
from flask_restful import Resource, Api
from docker import APIClient

dockercli = APIClient(base_url='unix://var/run/docker.sock')
app = Flask(__name__)
api = Api(app)

class Containers(Resource):
    def get(self):
        return dockercli.containers(all=True)

class ContainerInfo(Resource):
    def get(self, container_id):
        return dockercli.containers(all=True, filters={"id": container_id})

class ContainerStart(Resource):
    def post(self, container_id):
        try:
            dockercli.start(container_id);
        except:
            return 'Error'
        else:
            return 'OK'

class ContainerStop(Resource):
    def post(self, container_id):
        try:
            dockercli.stop(container_id);
        except: 
            return 'Error'
        else:
            return 'OK'

api.add_resource(Containers, '/info/container/all')
api.add_resource(ContainerInfo, '/info/container/<string:container_id>')
api.add_resource(ContainerStop, '/stop/container/<string:container_id>')
api.add_resource(ContainerStart, '/start/container/<string:container_id>')

if __name__ == '__main__':
    app.run(debug=False, host='0.0.0.0', port='8080')
