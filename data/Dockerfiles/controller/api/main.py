import os
import sys
import uvicorn
import json
import uuid
import async_timeout
import asyncio
import aiodocker
import docker
import logging
from logging.config import dictConfig
from fastapi import FastAPI, Response, Request
from modules.DockerApi import DockerApi
from redis import asyncio as aioredis
from contextlib import asynccontextmanager

dockerapi = None

@asynccontextmanager
async def lifespan(app: FastAPI):
  global dockerapi

  # Initialize a custom logger
  logger = logging.getLogger("dockerapi")
  logger.setLevel(logging.INFO)
  # Configure the logger to output logs to the terminal
  handler = logging.StreamHandler()
  handler.setLevel(logging.INFO)
  formatter = logging.Formatter("%(levelname)s:     %(message)s")
  handler.setFormatter(formatter)
  logger.addHandler(handler)

  logger.info("Init APP")

  # Init redis client
  if os.environ['REDIS_SLAVEOF_IP'] != "":
    redis_client = redis = await aioredis.from_url(f"redis://{os.environ['REDIS_SLAVEOF_IP']}:{os.environ['REDIS_SLAVEOF_PORT']}/0", password=os.environ['REDISPASS'])
  else:
    redis_client = redis = await aioredis.from_url("redis://redis-mailcow:6379/0", password=os.environ['REDISPASS'])

  # Init docker clients
  sync_docker_client = docker.DockerClient(base_url='unix://var/run/docker.sock', version='auto')
  async_docker_client = aiodocker.Docker(url='unix:///var/run/docker.sock')

  dockerapi = DockerApi(redis_client, sync_docker_client, async_docker_client, logger)

  logger.info("Subscribe to redis channel")
  # Subscribe to redis channel
  dockerapi.pubsub = redis.pubsub()
  await dockerapi.pubsub.subscribe("MC_CHANNEL")
  asyncio.create_task(handle_pubsub_messages(dockerapi.pubsub))


  yield

  # Close docker connections
  dockerapi.sync_docker_client.close()
  await dockerapi.async_docker_client.close()

  # Close redis
  await dockerapi.pubsub.unsubscribe("MC_CHANNEL")
  await dockerapi.redis_client.close()

app = FastAPI(lifespan=lifespan)

# Define Routes
@app.get("/host/stats")
async def get_host_update_stats():
  global dockerapi

  if dockerapi.host_stats_isUpdating == False:
    asyncio.create_task(dockerapi.get_host_stats())
    dockerapi.host_stats_isUpdating = True

  while True:
    if await dockerapi.redis_client.exists('host_stats'):
      break
    await asyncio.sleep(1.5)

  stats = json.loads(await dockerapi.redis_client.get('host_stats'))
  return Response(content=json.dumps(stats, indent=4), media_type="application/json")

@app.get("/containers/{container_id}/json")
async def get_container(container_id : str):
  global dockerapi

  if container_id and container_id.isalnum():
    try:
      for container in (await dockerapi.async_docker_client.containers.list()):
        if container._id == container_id:
          container_info = await container.show()
          return Response(content=json.dumps(container_info, indent=4), media_type="application/json")

      res = {
        "type": "danger",
        "msg": "no container found"
      }
      return Response(content=json.dumps(res, indent=4), media_type="application/json")
    except Exception as e:
      res = {
        "type": "danger",
        "msg": str(e)
      }
      return Response(content=json.dumps(res, indent=4), media_type="application/json")
  else:
    res = {
      "type": "danger",
      "msg": "no or invalid id defined"
    }
    return Response(content=json.dumps(res, indent=4), media_type="application/json")

@app.get("/containers/json")
async def get_containers():
  global dockerapi

  containers = {}
  try:
    for container in (await dockerapi.async_docker_client.containers.list()):
      container_info = await container.show()
      containers.update({container_info['Id']: container_info})
    return Response(content=json.dumps(containers, indent=4), media_type="application/json")
  except Exception as e:
    res = {
      "type": "danger",
      "msg": str(e)
    }
    return Response(content=json.dumps(res, indent=4), media_type="application/json")

@app.post("/containers/{container_id}/{post_action}")
async def post_containers(container_id : str, post_action : str, request: Request):
  global dockerapi

  try:
    request_json = await request.json()
  except Exception as err:
    request_json = {}

  if container_id and container_id.isalnum() and post_action:
    try:
      """Dispatch container_post api call"""
      if post_action == 'exec':
        if not request_json or not 'cmd' in request_json:
          res = {
            "type": "danger",
            "msg": "cmd is missing"
          }
          return Response(content=json.dumps(res, indent=4), media_type="application/json")
        if not request_json or not 'task' in request_json:
          res = {
            "type": "danger",
            "msg": "task is missing"
          }
          return Response(content=json.dumps(res, indent=4), media_type="application/json")

        api_call_method_name = '__'.join(['container_post', str(post_action), str(request_json['cmd']), str(request_json['task']) ])
      else:
        api_call_method_name = '__'.join(['container_post', str(post_action) ])

      api_call_method = getattr(dockerapi, api_call_method_name, lambda container_id: Response(content=json.dumps({'type': 'danger', 'msg':'container_post - unknown api call' }, indent=4), media_type="application/json"))

      dockerapi.logger.info("api call: %s, container_id: %s" % (api_call_method_name, container_id))
      return api_call_method(request_json, container_id=container_id)
    except Exception as e:
      dockerapi.logger.error("error - container_post: %s" % str(e))
      res = {
        "type": "danger",
        "msg": str(e)
      }
      return Response(content=json.dumps(res, indent=4), media_type="application/json")

  else:
    res = {
      "type": "danger",
      "msg": "invalid container id or missing action"
    }
    return Response(content=json.dumps(res, indent=4), media_type="application/json")

@app.post("/container/{container_id}/stats/update")
async def post_container_update_stats(container_id : str):
  global dockerapi

  # start update task for container if no task is running
  if container_id not in dockerapi.containerIds_to_update:
    asyncio.create_task(dockerapi.get_container_stats(container_id))
    dockerapi.containerIds_to_update.append(container_id)

  while True:
    if await dockerapi.redis_client.exists(container_id + '_stats'):
      break
    await asyncio.sleep(1.5)

  stats = json.loads(await dockerapi.redis_client.get(container_id + '_stats'))
  return Response(content=json.dumps(stats, indent=4), media_type="application/json")


# PubSub Handler
async def handle_pubsub_messages(channel: aioredis.client.PubSub):
  global dockerapi

  while True:
    try:
      async with async_timeout.timeout(60):
        message = await channel.get_message(ignore_subscribe_messages=True, timeout=30)
        if message is not None:
          # Parse message
          data_json = json.loads(message['data'].decode('utf-8'))
          dockerapi.logger.info(f"PubSub Received - {json.dumps(data_json)}")

          # Handle api_call
          if 'api_call' in data_json:
            # api_call: container_post
            if data_json['api_call'] == "container_post":
              if 'post_action' in data_json and 'container_name' in data_json:
                try:
                  """Dispatch container_post api call"""
                  request_json = {}
                  if data_json['post_action'] == 'exec':
                    if 'request' in data_json:
                      request_json = data_json['request']
                      if 'cmd' in request_json:
                        if 'task' in request_json:
                          api_call_method_name = '__'.join(['container_post', str(data_json['post_action']), str(request_json['cmd']), str(request_json['task']) ])
                        else:
                          dockerapi.logger.error("api call: task missing")
                      else:
                        dockerapi.logger.error("api call: cmd missing")
                    else:
                      dockerapi.logger.error("api call: request missing")
                  else:
                    api_call_method_name = '__'.join(['container_post', str(data_json['post_action'])])

                  if api_call_method_name:
                    api_call_method = getattr(dockerapi, api_call_method_name)
                    if api_call_method:
                      dockerapi.logger.info("api call: %s, container_name: %s" % (api_call_method_name, data_json['container_name']))
                      api_call_method(request_json, container_name=data_json['container_name'])
                    else:
                      dockerapi.logger.error("api call not found: %s, container_name: %s" % (api_call_method_name, data_json['container_name']))
                except Exception as e:
                  dockerapi.logger.error("container_post: %s" % str(e))
              else:
                dockerapi.logger.error("api call: missing container_name, post_action or request")
            else:
              dockerapi.logger.error("Unknown PubSub received - %s" % json.dumps(data_json))
          else:
            dockerapi.logger.error("Unknown PubSub received - %s" % json.dumps(data_json))

        await asyncio.sleep(0.0)
    except asyncio.TimeoutError:
      pass

if __name__ == '__main__':
  uvicorn.run(
    app,
    host="0.0.0.0",
    port=443,
    ssl_certfile="/app/controller_cert.pem",
    ssl_keyfile="/app/controller_key.pem",
    log_level="info",
    loop="none"
  )
