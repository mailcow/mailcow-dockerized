---
name: Bug report
about: Report a bug for this project

---

**README and remove me**
For community support and other discussion, you are welcome to visit and stay with us @ Freenode, #mailcow
Answering can take a few seconds up to many hours, please be patient.
Commercial support, including a ticket system, can be found @ https://www.servercow.de/mailcow#support - we are also available via Telegram. \o/

**Describe the bug, try to make it reproducible**
A clear and concise description of what the bug is. How can it be reproduced? 
If applicable, add screenshots to help explain your problem. Very useful for bugs in mailcow UI.

**System information and quick debugging**
General logs:
- Please take a look at the [documentation](https://mailcow.github.io/mailcow-dockerized-docs/debug-logs/).

Further information (where applicable):
 - Your OS (is Apparmor or SELinux active?)
 - Your virtualization technology (KVM/QEMU, Xen, VMware, VirtualBox etc.)
 - Your server/VM specifications (Memory, CPU Cores)
 - Don't try to run mailcow on a Synology or QNAP NAS, do you?
 - Docker and Docker Compose versions
 - Output of `git diff origin/master`, any other changes to the code?
 - All third-party firewalls and custom iptables rules are unsupported. Please check the Docker docs about how to use Docker with your own ruleset. Nevertheless, iptabels output can help _us_ to help _you_: `iptables -L -vn`, `ip6tables -L -vn`, `iptables -L -vn -t nat` and `ip6tables -L -vn -t nat `
 - Reverse proxy? If you think this problem is related to your reverse proxy, please post your configuration.
 - Browser (if it's a Web UI issue) - please clean your browser cache and try again, problem persists?
 - Check `docker exec -it $(docker ps -qf name=acme-mailcow) dig +short stackoverflow.com @172.22.1.254` (set the IP accordingly, if you changed the internal mailcow network) and `docker exec -it $(docker ps -qf name=acme-mailcow) dig +short stackoverflow.com @1.1.1.1` - output? Timeout?
