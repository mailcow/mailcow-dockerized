import psutil
import sys
import os
import re
import time
import json
import asyncio
import platform
from datetime import datetime
from fastapi import FastAPI, Response, Request

class DockerApi:
  def __init__(self, redis_client, sync_docker_client, async_docker_client, logger):
    self.redis_client = redis_client
    self.sync_docker_client = sync_docker_client
    self.async_docker_client = async_docker_client
    self.logger = logger

    self.host_stats_isUpdating = False
    self.containerIds_to_update = []

  # api call: container_post - post_action: stop
  def container_post__stop(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    for container in self.sync_docker_client.containers.list(all=True, filters=filters):
      container.stop()

    res = { 'type': 'success', 'msg': 'command completed successfully'}
    return Response(content=json.dumps(res, indent=4), media_type="application/json")
  # api call: container_post - post_action: start
  def container_post__start(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    for container in self.sync_docker_client.containers.list(all=True, filters=filters):
      container.start()

    res = { 'type': 'success', 'msg': 'command completed successfully'}
    return Response(content=json.dumps(res, indent=4), media_type="application/json")
  # api call: container_post - post_action: restart
  def container_post__restart(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    for container in self.sync_docker_client.containers.list(all=True, filters=filters):
      container.restart()

    res = { 'type': 'success', 'msg': 'command completed successfully'}
    return Response(content=json.dumps(res, indent=4), media_type="application/json")
  # api call: container_post - post_action: top
  def container_post__top(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    for container in self.sync_docker_client.containers.list(all=True, filters=filters):
      res = { 'type': 'success', 'msg': container.top()}
      return Response(content=json.dumps(res, indent=4), media_type="application/json")
  # api call: container_post - post_action: stats
  def container_post__stats(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    for container in self.sync_docker_client.containers.list(all=True, filters=filters):
      for stat in container.stats(decode=True, stream=True):
        res = { 'type': 'success', 'msg': stat}
        return Response(content=json.dumps(res, indent=4), media_type="application/json")
  # api call: container_post - post_action: exec - cmd: mailq - task: delete
  def container_post__exec__mailq__delete(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    if 'items' in request_json:
      r = re.compile("^[0-9a-fA-F]+$")
      filtered_qids = filter(r.match, request_json['items'])
      if filtered_qids:
        flagged_qids = ['-d %s' % i for i in filtered_qids]
        sanitized_string = str(' '.join(flagged_qids))
        for container in self.sync_docker_client.containers.list(filters=filters):
          postsuper_r = container.exec_run(["/bin/bash", "-c", "/usr/sbin/postsuper " + sanitized_string])
          return self.exec_run_handler('generic', postsuper_r)
  # api call: container_post - post_action: exec - cmd: mailq - task: hold
  def container_post__exec__mailq__hold(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    if 'items' in request_json:
      r = re.compile("^[0-9a-fA-F]+$")
      filtered_qids = filter(r.match, request_json['items'])
      if filtered_qids:
        flagged_qids = ['-h %s' % i for i in filtered_qids]
        sanitized_string = str(' '.join(flagged_qids))
        for container in self.sync_docker_client.containers.list(filters=filters):
          postsuper_r = container.exec_run(["/bin/bash", "-c", "/usr/sbin/postsuper " + sanitized_string])
          return self.exec_run_handler('generic', postsuper_r)
  # api call: container_post - post_action: exec - cmd: mailq - task: cat
  def container_post__exec__mailq__cat(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    if 'items' in request_json:
      r = re.compile("^[0-9a-fA-F]+$")
      filtered_qids = filter(r.match, request_json['items'])
      if filtered_qids:
        sanitized_string = str(' '.join(filtered_qids))

        for container in self.sync_docker_client.containers.list(filters=filters):
          postcat_return = container.exec_run(["/bin/bash", "-c", "/usr/sbin/postcat -q " + sanitized_string], user='postfix')
        if not postcat_return:
          postcat_return = 'err: invalid'
        return self.exec_run_handler('utf8_text_only', postcat_return)
  # api call: container_post - post_action: exec - cmd: mailq - task: unhold
  def container_post__exec__mailq__unhold(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    if 'items' in request_json:
      r = re.compile("^[0-9a-fA-F]+$")
      filtered_qids = filter(r.match, request_json['items'])
      if filtered_qids:
        flagged_qids = ['-H %s' % i for i in filtered_qids]
        sanitized_string = str(' '.join(flagged_qids))
        for container in self.sync_docker_client.containers.list(filters=filters):
          postsuper_r = container.exec_run(["/bin/bash", "-c", "/usr/sbin/postsuper " + sanitized_string])
          return self.exec_run_handler('generic', postsuper_r)
  # api call: container_post - post_action: exec - cmd: mailq - task: deliver
  def container_post__exec__mailq__deliver(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    if 'items' in request_json:
      r = re.compile("^[0-9a-fA-F]+$")
      filtered_qids = filter(r.match, request_json['items'])
      if filtered_qids:
        flagged_qids = ['-i %s' % i for i in filtered_qids]
        for container in self.sync_docker_client.containers.list(filters=filters):
          for i in flagged_qids:
            postqueue_r = container.exec_run(["/bin/bash", "-c", "/usr/sbin/postqueue " + i], user='postfix')
            # todo: check each exit code
          res = { 'type': 'success', 'msg': 'Scheduled immediate delivery'}
          return Response(content=json.dumps(res, indent=4), media_type="application/json")        
  # api call: container_post - post_action: exec - cmd: mailq - task: list
  def container_post__exec__mailq__list(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    for container in self.sync_docker_client.containers.list(filters=filters):
      mailq_return = container.exec_run(["/usr/sbin/postqueue", "-j"], user='postfix')
      return self.exec_run_handler('utf8_text_only', mailq_return)
  # api call: container_post - post_action: exec - cmd: mailq - task: flush
  def container_post__exec__mailq__flush(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    for container in self.sync_docker_client.containers.list(filters=filters):
      postqueue_r = container.exec_run(["/usr/sbin/postqueue", "-f"], user='postfix')
      return self.exec_run_handler('generic', postqueue_r)
  # api call: container_post - post_action: exec - cmd: mailq - task: super_delete
  def container_post__exec__mailq__super_delete(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    for container in self.sync_docker_client.containers.list(filters=filters):
      postsuper_r = container.exec_run(["/usr/sbin/postsuper", "-d", "ALL"])
      return self.exec_run_handler('generic', postsuper_r)
  # api call: container_post - post_action: exec - cmd: system - task: fts_rescan
  def container_post__exec__system__fts_rescan(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    if 'username' in request_json:
      for container in self.sync_docker_client.containers.list(filters=filters):
        rescan_return = container.exec_run(["/bin/bash", "-c", "/usr/bin/doveadm fts rescan -u '" + request_json['username'].replace("'", "'\\''") + "'"], user='vmail')
        if rescan_return.exit_code == 0:
          res = { 'type': 'success', 'msg': 'fts_rescan: rescan triggered'}
          return Response(content=json.dumps(res, indent=4), media_type="application/json")
        else:
          res = { 'type': 'warning', 'msg': 'fts_rescan error'}
          return Response(content=json.dumps(res, indent=4), media_type="application/json")
    if 'all' in request_json:
      for container in self.sync_docker_client.containers.list(filters=filters):
        rescan_return = container.exec_run(["/bin/bash", "-c", "/usr/bin/doveadm fts rescan -A"], user='vmail')
        if rescan_return.exit_code == 0:
          res = { 'type': 'success', 'msg': 'fts_rescan: rescan triggered'}
          return Response(content=json.dumps(res, indent=4), media_type="application/json")
        else:
          res = { 'type': 'warning', 'msg': 'fts_rescan error'}
          return Response(content=json.dumps(res, indent=4), media_type="application/json")
  # api call: container_post - post_action: exec - cmd: system - task: df
  def container_post__exec__system__df(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    if 'dir' in request_json:
      for container in self.sync_docker_client.containers.list(filters=filters):
        df_return = container.exec_run(["/bin/bash", "-c", "/bin/df -H '" + request_json['dir'].replace("'", "'\\''") + "' | /usr/bin/tail -n1 | /usr/bin/tr -s [:blank:] | /usr/bin/tr ' ' ','"], user='nobody')
        if df_return.exit_code == 0:
          return df_return.output.decode('utf-8').rstrip()
        else:
          return "0,0,0,0,0,0"
  # api call: container_post - post_action: exec - cmd: system - task: mysql_upgrade
  def container_post__exec__system__mysql_upgrade(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    for container in self.sync_docker_client.containers.list(filters=filters):
      sql_return = container.exec_run(["/bin/bash", "-c", "/usr/bin/mysql_upgrade -uroot -p'" + os.environ['DBROOT'].replace("'", "'\\''") + "'\n"], user='mysql')
      if sql_return.exit_code == 0:
        matched = False
        for line in sql_return.output.decode('utf-8').split("\n"):
          if 'is already upgraded to' in line:
            matched = True
        if matched:
          res = { 'type': 'success', 'msg':'mysql_upgrade: already upgraded', 'text': sql_return.output.decode('utf-8')}
          return Response(content=json.dumps(res, indent=4), media_type="application/json")
        else:
          container.restart()
          res = { 'type': 'warning', 'msg':'mysql_upgrade: upgrade was applied', 'text': sql_return.output.decode('utf-8')}
          return Response(content=json.dumps(res, indent=4), media_type="application/json")
      else:
        res = { 'type': 'error', 'msg': 'mysql_upgrade: error running command', 'text': sql_return.output.decode('utf-8')}
        return Response(content=json.dumps(res, indent=4), media_type="application/json")
  # api call: container_post - post_action: exec - cmd: system - task: mysql_tzinfo_to_sql
  def container_post__exec__system__mysql_tzinfo_to_sql(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    for container in self.sync_docker_client.containers.list(filters=filters):
      sql_return = container.exec_run(["/bin/bash", "-c", "/usr/bin/mysql_tzinfo_to_sql /usr/share/zoneinfo | /bin/sed 's/Local time zone must be set--see zic manual page/FCTY/' | /usr/bin/mysql -uroot -p'" + os.environ['DBROOT'].replace("'", "'\\''") + "' mysql \n"], user='mysql')
      if sql_return.exit_code == 0:
        res = { 'type': 'info', 'msg': 'mysql_tzinfo_to_sql: command completed successfully', 'text': sql_return.output.decode('utf-8')}
        return Response(content=json.dumps(res, indent=4), media_type="application/json")
      else:
        res = { 'type': 'error', 'msg': 'mysql_tzinfo_to_sql: error running command', 'text': sql_return.output.decode('utf-8')}
        return Response(content=json.dumps(res, indent=4), media_type="application/json")
  # api call: container_post - post_action: exec - cmd: reload - task: dovecot
  def container_post__exec__reload__dovecot(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    for container in self.sync_docker_client.containers.list(filters=filters):
      reload_return = container.exec_run(["/bin/bash", "-c", "/usr/sbin/dovecot reload"])
      return self.exec_run_handler('generic', reload_return)
  # api call: container_post - post_action: exec - cmd: reload - task: postfix
  def container_post__exec__reload__postfix(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    for container in self.sync_docker_client.containers.list(filters=filters):
      reload_return = container.exec_run(["/bin/bash", "-c", "/usr/sbin/postfix reload"])
      return self.exec_run_handler('generic', reload_return)
  # api call: container_post - post_action: exec - cmd: reload - task: nginx
  def container_post__exec__reload__nginx(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    for container in self.sync_docker_client.containers.list(filters=filters):
      reload_return = container.exec_run(["/bin/sh", "-c", "/usr/sbin/nginx -s reload"])
      return self.exec_run_handler('generic', reload_return)
  # api call: container_post - post_action: exec - cmd: sieve - task: list
  def container_post__exec__sieve__list(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    if 'username' in request_json:
      for container in self.sync_docker_client.containers.list(filters=filters):
        sieve_return = container.exec_run(["/bin/bash", "-c", "/usr/bin/doveadm sieve list -u '" + request_json['username'].replace("'", "'\\''") + "'"])
        return self.exec_run_handler('utf8_text_only', sieve_return)
  # api call: container_post - post_action: exec - cmd: sieve - task: print
  def container_post__exec__sieve__print(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    if 'username' in request_json and 'script_name' in request_json:
      for container in self.sync_docker_client.containers.list(filters=filters):
        cmd = ["/bin/bash", "-c", "/usr/bin/doveadm sieve get -u '" + request_json['username'].replace("'", "'\\''") + "' '" + request_json['script_name'].replace("'", "'\\''") + "'"]  
        sieve_return = container.exec_run(cmd)
        return self.exec_run_handler('utf8_text_only', sieve_return)
  # api call: container_post - post_action: exec - cmd: maildir - task: cleanup
  def container_post__exec__maildir__cleanup(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    if 'maildir' in request_json:
      for container in self.sync_docker_client.containers.list(filters=filters):
        sane_name = re.sub(r'\W+', '', request_json['maildir'])
        vmail_name = request_json['maildir'].replace("'", "'\\''")
        cmd_vmail = "if [[ -d '/var/vmail/" + vmail_name + "' ]]; then /bin/mv '/var/vmail/" + vmail_name + "' '/var/vmail/_garbage/" + str(int(time.time())) + "_" + sane_name + "'; fi"
        index_name = request_json['maildir'].split("/")
        if len(index_name) > 1:
          index_name = index_name[1].replace("'", "'\\''") + "@" + index_name[0].replace("'", "'\\''")
          cmd_vmail_index = "if [[ -d '/var/vmail_index/" + index_name + "' ]]; then /bin/mv '/var/vmail_index/" + index_name + "' '/var/vmail/_garbage/" + str(int(time.time())) + "_" + sane_name + "_index'; fi"
          cmd = ["/bin/bash", "-c", cmd_vmail + " && " + cmd_vmail_index]
        else:
          cmd = ["/bin/bash", "-c", cmd_vmail]
        maildir_cleanup = container.exec_run(cmd, user='vmail')
        return self.exec_run_handler('generic', maildir_cleanup)
  # api call: container_post - post_action: exec - cmd: rspamd - task: worker_password
  def container_post__exec__rspamd__worker_password(self, request_json, **kwargs):
    if 'container_id' in kwargs:
      filters = {"id": kwargs['container_id']}
    elif 'container_name' in kwargs:
      filters = {"name": kwargs['container_name']}

    if 'raw' in request_json:
      for container in self.sync_docker_client.containers.list(filters=filters):
        cmd = "/usr/bin/rspamadm pw -e -p '" + request_json['raw'].replace("'", "'\\''") + "' 2> /dev/null"
        cmd_response = self.exec_cmd_container(container, cmd, user="_rspamd")

        matched = False
        for line in cmd_response.split("\n"):
          if '$2$' in line:
            hash = line.strip()
            hash_out = re.search(r'\$2\$.+$', hash).group(0)
            rspamd_passphrase_hash = re.sub(r'[^0-9a-zA-Z\$]+', '', hash_out.rstrip())
            rspamd_password_filename = "/etc/rspamd/override.d/worker-controller-password.inc"
            cmd = '''/bin/echo 'enable_password = "%s";' > %s && cat %s''' % (rspamd_passphrase_hash, rspamd_password_filename, rspamd_password_filename)
            cmd_response = self.exec_cmd_container(container, cmd, user="_rspamd")
            if rspamd_passphrase_hash.startswith("$2$") and rspamd_passphrase_hash in cmd_response:
              container.restart()
              matched = True
        if matched:
          res = { 'type': 'success', 'msg': 'command completed successfully' }
          self.logger.info('success changing Rspamd password')
          return Response(content=json.dumps(res, indent=4), media_type="application/json")
        else:
          self.logger.error('failed changing Rspamd password')
          res = { 'type': 'danger', 'msg': 'command did not complete' }
          return Response(content=json.dumps(res, indent=4), media_type="application/json")

  # Collect host stats
  async def get_host_stats(self, wait=5):
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
        "system_time": system_time.strftime("%d.%m.%Y %H:%M:%S"),
        "architecture": platform.machine()
      }

      await self.redis_client.set('host_stats', json.dumps(host_stats), ex=10)
    except Exception as e:
      res = {
        "type": "danger",
        "msg": str(e)
      }

    await asyncio.sleep(wait)
    self.host_stats_isUpdating = False
  # Collect container stats
  async def get_container_stats(self, container_id, wait=5, stop=False):
    if container_id and container_id.isalnum():
      try:
        for container in (await self.async_docker_client.containers.list()):
          if container._id == container_id:
            res = await container.stats(stream=False)

            if await self.redis_client.exists(container_id + '_stats'):
              stats = json.loads(await self.redis_client.get(container_id + '_stats'))
            else:
              stats = []
            stats.append(res[0])
            if len(stats) > 3:
              del stats[0]
            await self.redis_client.set(container_id + '_stats', json.dumps(stats), ex=60)
      except Exception as e:
        res = {
          "type": "danger",
          "msg": str(e)
        }
    else:
      res = {
        "type": "danger",
        "msg": "no or invalid id defined"
      }

    await asyncio.sleep(wait)
    if stop == True:
      # update task was called second time, stop
      self.containerIds_to_update.remove(container_id)
    else:
      # call update task a second time
      await self.get_container_stats(container_id, wait=0, stop=True)

  def exec_cmd_container(self, container, cmd, user, timeout=2, shell_cmd="/bin/bash"):
    def recv_socket_data(c_socket, timeout):
      c_socket.setblocking(0)
      total_data=[]
      data=''
      begin=time.time()
      while True:
        if total_data and time.time()-begin > timeout:
          break
        elif time.time()-begin > timeout*2:
          break
        try:
          data = c_socket.recv(8192)
          if data:
            total_data.append(data.decode('utf-8'))
            #change the beginning time for measurement
            begin=time.time()
          else:
            #sleep for sometime to indicate a gap
            time.sleep(0.1)
            break
        except:
          pass
      return ''.join(total_data)
      
    try :
      socket = container.exec_run([shell_cmd], stdin=True, socket=True, user=user).output._sock
      if not cmd.endswith("\n"):
        cmd = cmd + "\n"
      socket.send(cmd.encode('utf-8'))
      data = recv_socket_data(socket, timeout)
      socket.close()
      return data
    except Exception as e:
      self.logger.error("error - exec_cmd_container: %s" % str(e))
      traceback.print_exc(file=sys.stdout)

  def exec_run_handler(self, type, output):
    if type == 'generic':
      if output.exit_code == 0:
        res = { 'type': 'success', 'msg': 'command completed successfully' }
        return Response(content=json.dumps(res, indent=4), media_type="application/json")
      else:
        res = { 'type': 'danger', 'msg': 'command failed: ' + output.output.decode('utf-8') }
        return Response(content=json.dumps(res, indent=4), media_type="application/json")
    if type == 'utf8_text_only':
      return Response(content=output.output.decode('utf-8'), media_type="text/plain")
