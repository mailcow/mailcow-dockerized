## Installing using Ansible

The provided playbook and role will install "mailcow-dockerized" using ansible.
This is useful in case you are using ansible to setup all other aspects of your environment and do not want to ssh manually into a server for installing mailcow.

## Requirements

The host should already have installed:

- docker (obviously!)
- pip (to install docker-compose with required module for ansible)

and from pip you should install the following packages:

- docker-compose (for mailcow installation)
- pyyaml (required for docker_service ansible module)

These requirements are out of scope for this playbook but they are easy to implement in ansible as previous steps before using the role provided here.

# Configuration

The hostname as given in the inventory will be used for "MAILCOW_HOSTNAME".

The default "MAILCOW_TZ" used is 'UTC' but this can be changed by setting "timezone_selection" ansible variable.
