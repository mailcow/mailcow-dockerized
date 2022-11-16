from fastapi import FastAPI, Response, Request
import aiodocker
import psutil
import sys
import re
import time
import os
import json
import asyncio
import redis
from datetime import datetime


containerIds_to_update = []
host_stats_isUpdating = False
app = FastAPI()


@app.get("/host/stats")
async def get_host_update_stats():
  global host_stats_isUpdating

  if host_stats_isUpdating == False:
    print("start host stats task")
    asyncio.create_task(get_host_stats())
    host_stats_isUpdating = True

  while True:
    if redis_client.exists('host_stats'):
      break
    print("wait for host_stats results")
    await asyncio.sleep(1.5)


  print("host stats pulled")
  stats = json.loads(redis_client.get('host_stats'))
  return Response(content=json.dumps(stats, indent=4), media_type="application/json")

@app.get("/containers/{container_id}/json")
async def get_container(container_id : str):
  if container_id and container_id.isalnum():
    try:
      for container in (await async_docker_client.containers.list()):
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
  containers = {}
  try:
    for container in (await async_docker_client.containers.list()):
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
  try : 
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

      docker_utils = DockerUtils(async_docker_client)
      api_call_method = getattr(docker_utils, api_call_method_name, lambda container_id: Response(content=json.dumps({'type': 'danger', 'msg':'container_post - unknown api call' }, indent=4), media_type="application/json"))


      print("api call: %s, container_id: %s" % (api_call_method_name, container_id))
      return await api_call_method(container_id, request_json)
    except Exception as e:
      print("error - container_post: %s" % str(e))
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
  global containerIds_to_update

  # start update task for container if no task is running
  if container_id not in containerIds_to_update:
    asyncio.create_task(get_container_stats(container_id))
    containerIds_to_update.append(container_id)

  while True:
    if redis_client.exists(container_id + '_stats'):
      break
    await asyncio.sleep(1.5)

  stats = json.loads(redis_client.get(container_id + '_stats'))
  return Response(content=json.dumps(stats, indent=4), media_type="application/json")




class DockerUtils:
  def __init__(self, docker_client):
    self.docker_client = docker_client

  # api call: container_post - post_action: stop
  async def container_post__stop(self, container_id, request_json):
    for container in (await self.docker_client.containers.list()):
      if container._id == container_id:
        await container.stop()
    res = {
      'type': 'success', 
      'msg': 'command completed successfully'
    }
    return Response(content=json.dumps(res, indent=4), media_type="application/json")

  # api call: container_post - post_action: start
  async def container_post__start(self, container_id, request_json):
    for container in (await self.docker_client.containers.list()):
      if container._id == container_id:
        await container.start()
    res = {
      'type': 'success', 
      'msg': 'command completed successfully'
    }
    return Response(content=json.dumps(res, indent=4), media_type="application/json")


  # api call: container_post - post_action: restart
  async def container_post__restart(self, container_id, request_json):
    for container in (await self.docker_client.containers.list()):
      if container._id == container_id:
        await container.restart()
    res = {
      'type': 'success', 
      'msg': 'command completed successfully'
    }
    return Response(content=json.dumps(res, indent=4), media_type="application/json")


  # api call: container_post - post_action: top
  async def container_post__top(self, container_id, request_json):
    for container in (await self.docker_client.containers.list()):
      if container._id == container_id:
        ps_exec = await container.exec("ps")      
        async with ps_exec.start(detach=False) as stream:
          ps_return = await stream.read_out()

        exec_details = await ps_exec.inspect()
        if exec_details["ExitCode"] == None or exec_details["ExitCode"] == 0:
          res = {
            'type': 'success', 
            'msg': ps_return.data.decode('utf-8')
          }
          return Response(content=json.dumps(res, indent=4), media_type="application/json")
        else:
          res = {
            'type': 'danger', 
            'msg': ''
          }
          return Response(content=json.dumps(res, indent=4), media_type="application/json")


  # api call: container_post - post_action: exec - cmd: mailq - task: delete
  async def container_post__exec__mailq__delete(self, container_id, request_json):
    if 'items' in request_json:
      r = re.compile("^[0-9a-fA-F]+$")
      filtered_qids = filter(r.match, request_json['items'])
      if filtered_qids:
        flagged_qids = ['-d %s' % i for i in filtered_qids]
        sanitized_string = str(' '.join(flagged_qids))

        for container in (await self.docker_client.containers.list()):
          if container._id == container_id:
            postsuper_r_exec = await container.exec(["/bin/bash", "-c", "/usr/sbin/postsuper " + sanitized_string])
            return await exec_run_handler('generic', postsuper_r_exec)

  # api call: container_post - post_action: exec - cmd: mailq - task: hold
  async def container_post__exec__mailq__hold(self, container_id, request_json):
    if 'items' in request_json:
      r = re.compile("^[0-9a-fA-F]+$")
      filtered_qids = filter(r.match, request_json['items'])
      if filtered_qids:
        flagged_qids = ['-h %s' % i for i in filtered_qids]
        sanitized_string = str(' '.join(flagged_qids))

        for container in (await self.docker_client.containers.list()):
          if container._id == container_id:
            postsuper_r_exec = await container.exec(["/bin/bash", "-c", "/usr/sbin/postsuper " + sanitized_string])
            return await exec_run_handler('generic', postsuper_r_exec)

  # api call: container_post - post_action: exec - cmd: mailq - task: cat
  async def container_post__exec__mailq__cat(self, container_id, request_json):
    if 'items' in request_json:
      r = re.compile("^[0-9a-fA-F]+$")
      filtered_qids = filter(r.match, request_json['items'])
      if filtered_qids:
        sanitized_string = str(' '.join(filtered_qids))

        for container in (await self.docker_client.containers.list()):
          if container._id == container_id:
            postcat_exec = await container.exec(["/bin/bash", "-c", "/usr/sbin/postcat -q " + sanitized_string], user='postfix')
            return await exec_run_handler('utf8_text_only', postcat_exec)

   # api call: container_post - post_action: exec - cmd: mailq - task: unhold
  async def container_post__exec__mailq__unhold(self, container_id, request_json):
    if 'items' in request_json:
      r = re.compile("^[0-9a-fA-F]+$")
      filtered_qids = filter(r.match, request_json['items'])
      if filtered_qids:
        flagged_qids = ['-H %s' % i for i in filtered_qids]
        sanitized_string = str(' '.join(flagged_qids))

        for container in (await self.docker_client.containers.list()):
          if container._id == container_id:
            postsuper_r_exec = await container.exec(["/bin/bash", "-c", "/usr/sbin/postsuper " + sanitized_string])
            return await exec_run_handler('generic', postsuper_r_exec)


  # api call: container_post - post_action: exec - cmd: mailq - task: deliver
  async def container_post__exec__mailq__deliver(self, container_id, request_json):
    if 'items' in request_json:
      r = re.compile("^[0-9a-fA-F]+$")
      filtered_qids = filter(r.match, request_json['items'])
      if filtered_qids:
        flagged_qids = ['-i %s' % i for i in filtered_qids]

        for container in (await self.docker_client.containers.list()):
          if container._id == container_id:
            for i in flagged_qids:
              postsuper_r_exec = await container.exec(["/bin/bash", "-c", "/usr/sbin/postqueue " + i], user='postfix')      
              async with postsuper_r_exec.start(detach=False) as stream:
                postsuper_r_return = await stream.read_out()
              # todo: check each exit code
            res = {
              'type': 'success', 
              'msg': 'Scheduled immediate delivery'
            }
            return Response(content=json.dumps(res, indent=4), media_type="application/json")


  # api call: container_post - post_action: exec - cmd: mailq - task: list
  async def container_post__exec__mailq__list(self, container_id, request_json):
    for container in (await self.docker_client.containers.list()):
      if container._id == container_id:
        mailq_exec = await container.exec(["/usr/sbin/postqueue", "-j"], user='postfix')
        return await exec_run_handler('utf8_text_only', mailq_exec)


  # api call: container_post - post_action: exec - cmd: mailq - task: flush
  async def container_post__exec__mailq__flush(self, container_id, request_json):
    for container in (await self.docker_client.containers.list()):
      if container._id == container_id:
        postsuper_r_exec = await container.exec(["/usr/sbin/postqueue", "-f"], user='postfix')
        return await exec_run_handler('generic', postsuper_r_exec)


  # api call: container_post - post_action: exec - cmd: mailq - task: super_delete
  async def container_post__exec__mailq__super_delete(self, container_id, request_json):
    for container in (await self.docker_client.containers.list()):
      if container._id == container_id:
        postsuper_r_exec = await container.exec(["/usr/sbin/postsuper", "-d", "ALL"])
        return await exec_run_handler('generic', postsuper_r_exec)


  # api call: container_post - post_action: exec - cmd: system - task: fts_rescan
  async def container_post__exec__system__fts_rescan(self, container_id, request_json):
    if 'username' in request_json:
      for container in (await self.docker_client.containers.list()):
        if container._id == container_id:
          rescan_exec = await container.exec(["/bin/bash", "-c", "/usr/bin/doveadm fts rescan -u '" + request_json['username'].replace("'", "'\\''") + "'"], user='vmail')         
          async with rescan_exec.start(detach=False) as stream:
            rescan_return = await stream.read_out()

          exec_details = await rescan_exec.inspect()
          if exec_details["ExitCode"] == None or exec_details["ExitCode"] == 0:
            res = {
              'type': 'success', 
              'msg': 'fts_rescan: rescan triggered'
            }
            return Response(content=json.dumps(res, indent=4), media_type="application/json")
          else:
            res = {
              'type': 'warning', 
              'msg': 'fts_rescan error'
            }
            return Response(content=json.dumps(res, indent=4), media_type="application/json")

    if 'all' in request_json:
      for container in (await self.docker_client.containers.list()):
        if container._id == container_id:
          rescan_exec = await container.exec(["/bin/bash", "-c", "/usr/bin/doveadm fts rescan -A"], user='vmail')          
          async with rescan_exec.start(detach=False) as stream:
            rescan_return = await stream.read_out()

          exec_details = await rescan_exec.inspect()
          if exec_details["ExitCode"] == None or exec_details["ExitCode"] == 0:
            res = {
              'type': 'success', 
              'msg': 'fts_rescan: rescan triggered'
            }
            return Response(content=json.dumps(res, indent=4), media_type="application/json")
          else:
            res = {
              'type': 'warning', 
              'msg': 'fts_rescan error'
            }
            return Response(content=json.dumps(res, indent=4), media_type="application/json")


  # api call: container_post - post_action: exec - cmd: system - task: df
  async def container_post__exec__system__df(self, container_id, request_json):
    if 'dir' in request_json:
      for container in (await self.docker_client.containers.list()):
        if container._id == container_id:
          df_exec = await container.exec(["/bin/bash", "-c", "/bin/df -H '" + request_json['dir'].replace("'", "'\\''") + "' | /usr/bin/tail -n1 | /usr/bin/tr -s [:blank:] | /usr/bin/tr ' ' ','"], user='nobody')
          async with df_exec.start(detach=False) as stream:
            df_return = await stream.read_out()

          print(df_return)
          print(await df_exec.inspect())
          exec_details = await df_exec.inspect()
          if exec_details["ExitCode"] == None or exec_details["ExitCode"] == 0:
            return df_return.data.decode('utf-8').rstrip()
          else:
            return "0,0,0,0,0,0"


  # api call: container_post - post_action: exec - cmd: system - task: mysql_upgrade
  async def container_post__exec__system__mysql_upgrade(self, container_id, request_json):
    for container in (await self.docker_client.containers.list()):
      if container._id == container_id:
        sql_exec = await container.exec(["/bin/bash", "-c", "/usr/bin/mysql_upgrade -uroot -p'" + os.environ['DBROOT'].replace("'", "'\\''") + "'\n"], user='mysql')
        async with sql_exec.start(detach=False) as stream:
          sql_return = await stream.read_out()

        exec_details = await sql_exec.inspect()
        if exec_details["ExitCode"] == None or exec_details["ExitCode"] == 0:
          matched = False
          for line in sql_return.data.decode('utf-8').split("\n"):
            if 'is already upgraded to' in line:
              matched = True
          if matched:
            res = {
              'type': 'success', 
              'msg': 'mysql_upgrade: already upgraded',
              'text': sql_return.data.decode('utf-8')
            }
            return Response(content=json.dumps(res, indent=4), media_type="application/json")
          else:
            await container.restart()
            res = {
              'type': 'warning', 
              'msg': 'mysql_upgrade: upgrade was applied',
              'text': sql_return.data.decode('utf-8')
            }
            return Response(content=json.dumps(res, indent=4), media_type="application/json")
        else:
          res = {
            'type': 'error', 
            'msg': 'mysql_upgrade: error running command',
            'text': sql_return.data.decode('utf-8')
          }
          return Response(content=json.dumps(res, indent=4), media_type="application/json")

  # api call: container_post - post_action: exec - cmd: system - task: mysql_tzinfo_to_sql
  async def container_post__exec__system__mysql_tzinfo_to_sql(self, container_id, request_json):
    for container in (await self.docker_client.containers.list()):
      if container._id == container_id:
        sql_exec = await container.exec(["/bin/bash", "-c", "/usr/bin/mysql_tzinfo_to_sql /usr/share/zoneinfo | /bin/sed 's/Local time zone must be set--see zic manual page/FCTY/' | /usr/bin/mysql -uroot -p'" + os.environ['DBROOT'].replace("'", "'\\''") + "' mysql \n"], user='mysql')
        async with sql_exec.start(detach=False) as stream:
          sql_return = await stream.read_out()

        exec_details = await sql_exec.inspect()
        if exec_details["ExitCode"] == None or exec_details["ExitCode"] == 0:
          res = {
            'type': 'info', 
            'msg': 'mysql_tzinfo_to_sql: command completed successfully',
            'text': sql_return.data.decode('utf-8')
          }
          return Response(content=json.dumps(res, indent=4), media_type="application/json")
        else:
          res = {
            'type': 'error', 
            'msg': 'mysql_tzinfo_to_sql: error running command',
            'text': sql_return.data.decode('utf-8')
          }
          return Response(content=json.dumps(res, indent=4), media_type="application/json")

  # api call: container_post - post_action: exec - cmd: reload - task: dovecot
  async def container_post__exec__reload__dovecot(self, container_id, request_json):
    for container in (await self.docker_client.containers.list()):
      if container._id == container_id:
        reload_exec = await container.exec(["/bin/bash", "-c", "/usr/sbin/dovecot reload"])
        return await exec_run_handler('generic', reload_exec)


  # api call: container_post - post_action: exec - cmd: reload - task: postfix
  async def container_post__exec__reload__postfix(self, container_id, request_json):
    for container in (await self.docker_client.containers.list()):
      if container._id == container_id:
        reload_exec = await container.exec(["/bin/bash", "-c", "/usr/sbin/postfix reload"])
        return await exec_run_handler('generic', reload_exec)


  # api call: container_post - post_action: exec - cmd: reload - task: nginx
  async def container_post__exec__reload__nginx(self, container_id, request_json):
    for container in (await self.docker_client.containers.list()):
      if container._id == container_id:
        reload_exec = await container.exec(["/bin/sh", "-c", "/usr/sbin/nginx -s reload"])
        return await exec_run_handler('generic', reload_exec)


  # api call: container_post - post_action: exec - cmd: sieve - task: list
  async def container_post__exec__sieve__list(self, container_id, request_json):
    if 'username' in request_json:
      for container in (await self.docker_client.containers.list()):
        if container._id == container_id:
          sieve_exec = await container.exec(["/bin/bash", "-c", "/usr/bin/doveadm sieve list -u '" + request_json['username'].replace("'", "'\\''") + "'"])
          return await exec_run_handler('utf8_text_only', sieve_exec)


  # api call: container_post - post_action: exec - cmd: sieve - task: print
  async def container_post__exec__sieve__print(self, container_id, request_json):
    if 'username' in request_json and 'script_name' in request_json:
      for container in (await self.docker_client.containers.list()):
        if container._id == container_id:
          cmd = ["/bin/bash", "-c", "/usr/bin/doveadm sieve get -u '" + request_json['username'].replace("'", "'\\''") + "' '" + request_json['script_name'].replace("'", "'\\''") + "'"]  
          sieve_exec = await container.exec(cmd)
          return await exec_run_handler('utf8_text_only', sieve_exec)


  # api call: container_post - post_action: exec - cmd: maildir - task: cleanup
  async def container_post__exec__maildir__cleanup(self, container_id, request_json):
    if 'maildir' in request_json:
      for container in (await self.docker_client.containers.list()):
        if container._id == container_id:
          sane_name = re.sub(r'\W+', '', request_json['maildir'])
          cmd = ["/bin/bash", "-c", "if [[ -d '/var/vmail/" + request_json['maildir'].replace("'", "'\\''") + "' ]]; then /bin/mv '/var/vmail/" + request_json['maildir'].replace("'", "'\\''") + "' '/var/vmail/_garbage/" + str(int(time.time())) + "_" + sane_name + "'; fi"]
          maildir_cleanup_exec = await container.exec(cmd, user='vmail')
          return await exec_run_handler('generic', maildir_cleanup_exec)

  # api call: container_post - post_action: exec - cmd: rspamd - task: worker_password
  async def container_post__exec__rspamd__worker_password(self, container_id, request_json):
    if 'raw' in request_json:
      for container in (await self.docker_client.containers.list()):
        if container._id == container_id:
          
          cmd = "./set_worker_password.sh '" + request_json['raw'].replace("'", "'\\''") + "' 2> /dev/null"
          rspamd_password_exec = await container.exec(cmd, user='_rspamd')  
          async with rspamd_password_exec.start(detach=False) as stream:
            rspamd_password_return = await stream.read_out()

          matched = False
          if "OK" in rspamd_password_return.data.decode('utf-8'):
            matched = True
            await container.restart()

          if matched:
            res = {
              'type': 'success', 
              'msg': 'command completed successfully'
            }
            return Response(content=json.dumps(res, indent=4), media_type="application/json")
          else:
            res = {
              'type': 'danger', 
              'msg': 'command did not complete'
            }
            return Response(content=json.dumps(res, indent=4), media_type="application/json")



async def exec_run_handler(type, exec_obj):
  async with exec_obj.start(detach=False) as stream:
    exec_return = await stream.read_out()

  if exec_return == None:
    exec_return = ""
  else:
    exec_return = exec_return.data.decode('utf-8')

  if type == 'generic':       
    exec_details = await exec_obj.inspect()
    if exec_details["ExitCode"] == None or exec_details["ExitCode"] == 0:
      res = {
        "type": "success",
        "msg": "command completed successfully"
      }
      return Response(content=json.dumps(res, indent=4), media_type="application/json")
    else:
      res = {
        "type": "success",
        "msg": "'command failed: " + exec_return
      }
      return Response(content=json.dumps(res, indent=4), media_type="application/json")
  if type == 'utf8_text_only':
    return Response(content=exec_return, media_type="text/plain")

async def get_host_stats(wait=5):
  global host_stats_isUpdating

  try:
    system_time = datetime.now()
    host_stats = {
      "cpu": {
        "cores": psutil.cpu_count(),
        "usage": psutil.cpu_percent()
      },
      "memory": {
        "total": psutil.virtual_memory().total,
        "usage": psutil.virtual_memory().percent,
        "swap": psutil.swap_memory()
      },
      "uptime": time.time() - psutil.boot_time(),
      "system_time": system_time.strftime("%d.%m.%Y %H:%M:%S")
    }

    redis_client.set('host_stats', json.dumps(host_stats), ex=10)
  except Exception as e:
    res = {
      "type": "danger",
      "msg": str(e)
    }
    print(json.dumps(res, indent=4))

  await asyncio.sleep(wait)
  host_stats_isUpdating = False
  

async def get_container_stats(container_id, wait=5, stop=False):
  global containerIds_to_update

  if container_id and container_id.isalnum():
    try:
      for container in (await async_docker_client.containers.list()):
        if container._id == container_id:
          res = await container.stats(stream=False)

          if redis_client.exists(container_id + '_stats'):
            stats = json.loads(redis_client.get(container_id + '_stats'))
          else:
            stats = []
          stats.append(res[0])
          if len(stats) > 3:
            del stats[0]
          redis_client.set(container_id + '_stats', json.dumps(stats), ex=60)
    except Exception as e:
      res = {
        "type": "danger",
        "msg": str(e)
      }
      print(json.dumps(res, indent=4))
  else:
    res = {
      "type": "danger",
      "msg": "no or invalid id defined"
    }
    print(json.dumps(res, indent=4))

  await asyncio.sleep(wait)
  if stop == True:
    # update task was called second time, stop
    containerIds_to_update.remove(container_id)
  else:
    # call update task a second time
    await get_container_stats(container_id, wait=0, stop=True)


if os.environ['REDIS_SLAVEOF_IP'] != "":
  redis_client = redis.Redis(host=os.environ['REDIS_SLAVEOF_IP'], port=os.environ['REDIS_SLAVEOF_PORT'], db=0)
else:
  redis_client = redis.Redis(host='redis-mailcow', port=6379, db=0)

async_docker_client = aiodocker.Docker(url='unix:///var/run/docker.sock')
