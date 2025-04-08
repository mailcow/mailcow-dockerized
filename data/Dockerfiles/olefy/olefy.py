#!/usr/bin/env python3
# -*- coding: utf-8 -*-

# Copyright (c) 2020, Dennis Kalbhen <d.kalbhen@heinlein-support.de>
# Copyright (c) 2020, Carsten Rosenberg <c.rosenberg@heinlein-support.de>
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

###
#
#  olefy is a little helper socket to use oletools with rspamd. (https://rspamd.com)
#  Please find updates and issues here: https://github.com/HeinleinSupport/olefy
#
###

from subprocess import Popen, PIPE
import sys
import os
import logging
import asyncio
import time
import magic
import re

# merge variables from /etc/olefy.conf and the defaults
olefy_listen_addr_string = os.getenv('OLEFY_BINDADDRESS', '127.0.0.1,::1')
olefy_listen_port = int(os.getenv('OLEFY_BINDPORT', '10050'))
olefy_tmp_dir = os.getenv('OLEFY_TMPDIR', '/tmp')
olefy_python_path = os.getenv('OLEFY_PYTHON_PATH', '/usr/bin/python3')
olefy_olevba_path = os.getenv('OLEFY_OLEVBA_PATH', '/usr/local/bin/olevba3')
# 10:DEBUG, 20:INFO, 30:WARNING, 40:ERROR, 50:CRITICAL
olefy_loglvl = int(os.getenv('OLEFY_LOGLVL', 20))
olefy_min_length = int(os.getenv('OLEFY_MINLENGTH', 500))
olefy_del_tmp = int(os.getenv('OLEFY_DEL_TMP', 1))
olefy_del_tmp_failed = int(os.getenv('OLEFY_DEL_TMP_FAILED', 1))

# internal used variables
request_time = '0000000000.000000'
olefy_protocol = 'OLEFY'
olefy_ping = 'PING'
olefy_protocol_sep = '\n\n'
olefy_headers = {}

# init logging
logger = logging.getLogger('olefy')
logging.basicConfig(stream=sys.stdout, level=olefy_loglvl, format='olefy %(levelname)s %(funcName)s %(message)s')

logger.debug('olefy listen address string: {} (type {})'.format(olefy_listen_addr_string, type(olefy_listen_addr_string)))

if not olefy_listen_addr_string:
    olefy_listen_addr = ""
else:
    addr_re = re.compile('[\[" \]]')
    olefy_listen_addr = addr_re.sub('', olefy_listen_addr_string.replace("'", "")).split(',')

# log runtime variables
logger.info('olefy listen address: {} (type: {})'.format(olefy_listen_addr, type(olefy_listen_addr)))
logger.info('olefy listen port: {}'.format(olefy_listen_port))
logger.info('olefy tmp dir: {}'.format(olefy_tmp_dir))
logger.info('olefy python path: {}'.format(olefy_python_path))
logger.info('olefy olvba path: {}'.format(olefy_olevba_path))
logger.info('olefy log level: {}'.format(olefy_loglvl))
logger.info('olefy min file length: {}'.format(olefy_min_length))
logger.info('olefy delete tmp file: {}'.format(olefy_del_tmp))
logger.info('olefy delete tmp file when failed: {}'.format(olefy_del_tmp_failed))

if not os.path.isfile(olefy_python_path):
    logger.critical('python path not found: {}'.format(olefy_python_path))
    exit(1)
if not os.path.isfile(olefy_olevba_path):
    logger.critical('olevba path not found: {}'.format(olefy_olevba_path))
    exit(1)

# olefy protocol function
def protocol_split( olefy_line ):
    header_lines = olefy_line.split('\n')
    for line in header_lines:
        if line == 'OLEFY/1.0':
            olefy_headers['olefy'] = line
        elif line != '':
            kv = line.split(': ')
            if kv[0] != '' and kv[1] != '':
                olefy_headers[kv[0]] = kv[1]
    logger.debug('olefy_headers: {}'.format(olefy_headers))

# calling oletools
def oletools( stream, tmp_file_name, lid ):
    if olefy_min_length > stream.__len__():
        logger.error('{} {} bytes (Not Scanning! File smaller than {!r})'.format(lid, stream.__len__(), olefy_min_length))
        out = b'[ { "error": "File too small" } ]'
    else:
        tmp_file = open(tmp_file_name, 'wb')
        tmp_file.write(stream)
        tmp_file.close()

        file_magic = magic.Magic(mime=True, uncompress=True)
        file_mime = file_magic.from_file(tmp_file_name)
        logger.info('{} {} (libmagic output)'.format(lid, file_mime))

        # do the olefy
        cmd_tmp = Popen([olefy_python_path, olefy_olevba_path, '-a', '-j' , '-l', 'error', tmp_file_name], stdout=PIPE, stderr=PIPE)
        out, err = cmd_tmp.communicate()
        out = bytes(out.decode('utf-8', 'ignore').replace('  ', ' ').replace('\t', '').replace('\n', '').replace('XLMMacroDeobfuscator: pywin32 is not installed (only is required if you want to use MS Excel)', ''), encoding="utf-8")
        failed = False
        if out.__len__() < 30:
            logger.error('{} olevba returned <30 chars - rc: {!r}, response: {!r}, error: {!r}'.format(lid,cmd_tmp.returncode, 
                out.decode('utf-8', 'ignore'), err.decode('utf-8', 'ignore')))
            out = b'[ { "error": "Unhandled error - too short olevba response" } ]'
            failed = True
        elif err.__len__() > 10 and cmd_tmp.returncode == 9:
            logger.error("{} olevba stderr >10 chars - rc: {!r}, response: {!r}".format(lid, cmd_tmp.returncode, err.decode("utf-8", "ignore")))
            out = b'[ { "error": "Decrypt failed" } ]'
            failed = True
        elif err.__len__() > 10 and cmd_tmp.returncode > 9:
            logger.error('{} olevba stderr >10 chars - rc: {!r}, response: {!r}'.format(lid, cmd_tmp.returncode, err.decode('utf-8', 'ignore')))
            out = b'[ { "error": "Unhandled oletools error" } ]'
            failed = True
        elif cmd_tmp.returncode != 0:
            logger.error('{} olevba exited with code {!r}; err: {!r}'.format(lid, cmd_tmp.returncode, err.decode('utf-8', 'ignore')))
            failed = True

        if failed and olefy_del_tmp_failed == 0:
            logger.debug('{} {} FAILED: not deleting tmp file'.format(lid, tmp_file_name))
        elif olefy_del_tmp == 1:
            logger.debug('{} {} deleting tmp file'.format(lid, tmp_file_name))
            os.remove(tmp_file_name)

    logger.debug('{} response: {}'.format(lid, out.decode('utf-8', 'ignore')))
    return out + b'\t\n\n\t'

# Asyncio data handling, default AIO-Functions
class AIO(asyncio.Protocol):
    def __init__(self):
        self.extra = bytearray()

    def connection_made(self, transport):
        global request_time
        peer = transport.get_extra_info('peername')
        logger.debug('{} new connection was made'.format(peer))
        self.transport = transport
        request_time = str(time.time())

    def data_received(self, request, msgid=1):
        peer = self.transport.get_extra_info('peername')
        logger.debug('{} data received from new connection'.format(peer))
        self.extra.extend(request)

    def eof_received(self):
        peer = self.transport.get_extra_info('peername')
        olefy_protocol_err = False
        proto_ck = self.extra[0:2000].decode('utf-8', 'ignore')

        headers = proto_ck[0:proto_ck.find(olefy_protocol_sep)]

        if olefy_protocol == headers[0:5]:
            self.extra = bytearray(self.extra[len(headers)+2:len(self.extra)])
            protocol_split(headers)
        else:
            olefy_protocol_err = True

        if olefy_ping == headers[0:4]:
            is_ping = True
        else:
            is_ping = False
            rspamd_id = olefy_headers['Rspamd-ID'][:6] or ''
            lid = 'Rspamd-ID' in olefy_headers and '<'+rspamd_id+'>'
            tmp_file_name = olefy_tmp_dir+'/'+request_time+'.'+str(peer[1])+'.'+rspamd_id
            logger.debug('{} {} choosen as tmp filename'.format(lid, tmp_file_name))

        if not is_ping or olefy_loglvl == 10:
            logger.info('{} {} bytes (stream size)'.format(lid, self.extra.__len__()))

        if olefy_ping == headers[0:4]:
            logger.debug('{} PING request'.format(peer))
            out = b'PONG'
        elif olefy_protocol_err == True or olefy_headers['olefy'] != 'OLEFY/1.0':
            logger.error('{} Protocol ERROR: no OLEFY/1.0 found'.format(lid))
            out = b'[ { "error": "Protocol error" } ]'
        elif 'Method' in olefy_headers:
            if olefy_headers['Method'] == 'oletools':
                out = oletools(self.extra, tmp_file_name, lid)
        else:
            logger.error('Protocol ERROR: Method header not found')
            out = b'[ { "error": "Protocol error: Method header not found" } ]'

        self.transport.write(out)
        if not is_ping or olefy_loglvl == 10:
            logger.info('{} {} response send: {!r}'.format(lid, peer, out))
        self.transport.close()


# start the listeners
loop = asyncio.get_event_loop()
# each client connection will create a new protocol instance
coro = loop.create_server(AIO, olefy_listen_addr, olefy_listen_port)
server = loop.run_until_complete(coro)
for sockets in server.sockets:
    logger.info('serving on {}'.format(sockets.getsockname()))

# XXX serve requests until KeyboardInterrupt, not needed for production
try:
    loop.run_forever()
except KeyboardInterrupt:
    pass

# graceful shutdown/reload
server.close()
loop.run_until_complete(server.wait_closed())
loop.close()
logger.info('stopped serving')
