#!/usr/bin/env python2

import re
import time
import atexit
import signal
import ipaddress
import subprocess
from threading import Thread
import docker

RULES = {
	'mailcowdockerized_postfix-mailcow_1': 'warning: .*\[([0-9a-f\.:]+)\]: SASL .* authentication failed',
	'mailcowdockerized_dovecot-mailcow_1': '-login: Disconnected \(auth failed, .*\): user=.*, method=.*, rip=([0-9a-f\.:]+),',
	'mailcowdockerized_sogo-mailcow_1': 'SOGo.* Login from \'([0-9a-f\.:]+)\' for user .* might not have worked',
	'mailcowdockerized_php-fpm-mailcow_1': 'Mailcow UI: Invalid password for .* by ([0-9a-f\.:]+)',
}
BAN_TIME = 1800
MAX_ATTEMPTS = 10

bans = {}
quit_now = False

def ban(address):
	ip = ipaddress.ip_address(address.decode('ascii'))
	if type(ip) is ipaddress.IPv6Address and ip.ipv4_mapped:
		ip = ip.ipv4_mapped
		address = str(ip)
	if ip.is_private or ip.is_loopback:
		return
	
	net = ipaddress.ip_network((address + ('/24' if type(ip) is ipaddress.IPv4Address else '/64')).decode('ascii'), strict=False)
	net = str(net)
	
	if not net in bans or time.time() - bans[net]['last_attempt'] > BAN_TIME:
		bans[net] = { 'attempts': 0 }
	
	bans[net]['attempts'] += 1
	bans[net]['last_attempt'] = time.time()
	
	if bans[net]['attempts'] >= MAX_ATTEMPTS:
		print "Banning %s" % net
		if type(ip) is ipaddress.IPv4Address:
			subprocess.call(["iptables", "-I", "INPUT", "-s", net, "-j", "REJECT"])
			subprocess.call(["iptables", "-I", "FORWARD", "-s", net, "-j", "REJECT"])
		else:
			subprocess.call(["ip6tables", "-I", "INPUT", "-s", net, "-j", "REJECT"])
			subprocess.call(["ip6tables", "-I", "FORWARD", "-s", net, "-j", "REJECT"])
	else:
		print "%d more attempts until %s is banned" % (MAX_ATTEMPTS - bans[net]['attempts'], net)

def unban(net):
	print "Unbanning %s" % net
	if type(ipaddress.ip_network(net.decode('ascii'))) is ipaddress.IPv4Network:
		subprocess.call(["iptables", "-D", "INPUT", "-s", net, "-j", "REJECT"])
		subprocess.call(["iptables", "-D", "FORWARD", "-s", net, "-j", "REJECT"])
	else:
		subprocess.call(["ip6tables", "-D", "INPUT", "-s", net, "-j", "REJECT"])
		subprocess.call(["ip6tables", "-D", "FORWARD", "-s", net, "-j", "REJECT"])
	del bans[net]

def quit(signum, frame):
	global quit_now
	quit_now = True

def clear():
	print "Clearing all bans"
	for net in bans.copy():
		unban(net)

def watch(container):
	print "Watching", container
	client = docker.from_env()
	for msg in client.containers.get(container).attach(stream=True, logs=False):
		result = re.search(RULES[container], msg)
		if result:
			addr = result.group(1)
			ban(addr)

def autopurge():
	while not quit_now:
		for net in bans.copy():
			if time.time() - bans[net]['last_attempt'] > BAN_TIME:
				unban(net)
		time.sleep(60)

if __name__ == '__main__':
	threads = []
	for container in RULES:
		threads.append(Thread(target=watch, args=(container,)))
		threads[-1].daemon = True
		threads[-1].start()

	autopurge_thread = Thread(target=autopurge)
	autopurge_thread.daemon = True
	autopurge_thread.start()

	signal.signal(signal.SIGTERM, quit)
	atexit.register(clear)

	while not quit_now:
		for thread in threads:
			if not thread.isAlive():
				break
		time.sleep(0.1)
	
	clear()
