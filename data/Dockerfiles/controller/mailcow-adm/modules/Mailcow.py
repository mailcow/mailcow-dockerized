import requests
import urllib3
import sys
import os
import subprocess
import tempfile
import mysql.connector
from contextlib import contextmanager
from datetime import datetime
from modules.Docker import Docker


class Mailcow:
    def __init__(self):
        self.apiUrl = "/api/v1"
        self.ignore_ssl_errors = True

        self.baseUrl = f"https://{os.getenv('IPv4_NETWORK', '172.22.1')}.247"
        self.host = os.getenv("MAILCOW_HOSTNAME", "")
        self.apiKey = ""
        if self.ignore_ssl_errors:
            urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

        self.db_config = {
            'user': os.getenv('DBUSER'),
            'password': os.getenv('DBPASS'),
            'database': os.getenv('DBNAME'),
            'unix_socket': '/var/run/mysqld/mysqld.sock',
        }

        self.docker = Docker()


    # API Functions
    def addDomain(self, domain):
        """
        Add a domain to the mailcow instance.
        :param domain: Dictionary containing domain details.
        :return: Response from the mailcow API.
        """

        return self.post('/add/domain', domain)

    def addMailbox(self, mailbox):
        """
        Add a mailbox to the mailcow instance.
        :param mailbox: Dictionary containing mailbox details.
        :return: Response from the mailcow API.
        """

        return self.post('/add/mailbox', mailbox)

    def addAlias(self, alias):
        """
        Add an alias to the mailcow instance.
        :param alias: Dictionary containing alias details.
        :return: Response from the mailcow API.
        """

        return self.post('/add/alias', alias)

    def addSyncjob(self, syncjob):
        """
        Add a sync job to the mailcow instance.
        :param syncjob: Dictionary containing sync job details.
        :return: Response from the mailcow API.
        """

        return self.post('/add/syncjob', syncjob)

    def addDomainadmin(self, domainadmin):
        """
        Add a domain admin to the mailcow instance.
        :param domainadmin: Dictionary containing domain admin details.
        :return: Response from the mailcow API.
        """

        return self.post('/add/domain-admin', domainadmin)

    def deleteDomain(self, domain):
        """
        Delete a domain from the mailcow instance.
        :param domain: Name of the domain to delete.
        :return: Response from the mailcow API.
        """

        items = [domain]
        return self.post('/delete/domain', items)

    def deleteAlias(self, id):
        """
        Delete an alias from the mailcow instance.
        :param id: ID of the alias to delete.
        :return: Response from the mailcow API.
        """

        items = [id]
        return self.post('/delete/alias', items)

    def deleteSyncjob(self, id):
        """
        Delete a sync job from the mailcow instance.
        :param id: ID of the sync job to delete.
        :return: Response from the mailcow API.
        """

        items = [id]
        return self.post('/delete/syncjob', items)

    def deleteMailbox(self, mailbox):
        """
        Delete a mailbox from the mailcow instance.
        :param mailbox: Name of the mailbox to delete.
        :return: Response from the mailcow API.
        """

        items = [mailbox]
        return self.post('/delete/mailbox', items)

    def deleteDomainadmin(self, username):
        """
        Delete a domain admin from the mailcow instance.
        :param username: Username of the domain admin to delete.
        :return: Response from the mailcow API.
        """

        items = [username]
        return self.post('/delete/domain-admin', items)

    def post(self, endpoint, data):
        """
        Make a POST request to the mailcow API.
        :param endpoint: The API endpoint to post to.
        :param data: Data to be sent in the POST request.
        :return: Response from the mailcow API.
        """

        url = f"{self.baseUrl}{self.apiUrl}/{endpoint.lstrip('/')}"
        headers = {
            "Content-Type": "application/json",
            "Host": self.host
        }
        if self.apiKey:
            headers["X-Api-Key"] = self.apiKey
        response = requests.post(
            url,
            json=data,
            headers=headers,
            verify=not self.ignore_ssl_errors
        )
        response.raise_for_status()
        return response.json()

    def getDomain(self, domain):
        """
        Get a domain from the mailcow instance.
        :param domain: Name of the domain to get.
        :return: Response from the mailcow API.
        """

        return self.get(f'/get/domain/{domain}')

    def getMailbox(self, username):
        """
        Get a mailbox from the mailcow instance.
        :param mailbox: Dictionary containing mailbox details (e.g. {"username": "user@example.com"})
        :return: Response from the mailcow API.
        """
        return self.get(f'/get/mailbox/{username}')

    def getAlias(self, id):
        """
        Get an alias from the mailcow instance.
        :param alias: Dictionary containing alias details (e.g. {"address": "alias@example.com"})
        :return: Response from the mailcow API.
        """
        return self.get(f'/get/alias/{id}')

    def getSyncjob(self, id):
        """
        Get a sync job from the mailcow instance.
        :param syncjob: Dictionary containing sync job details (e.g. {"id": "123"})
        :return: Response from the mailcow API.
        """
        return self.get(f'/get/syncjobs/{id}')

    def getDomainadmin(self, username):
        """
        Get a domain admin from the mailcow instance.
        :param username: Username of the domain admin to get.
        :return: Response from the mailcow API.
        """
        return self.get(f'/get/domain-admin/{username}')

    def getStatusVersion(self):
        """
        Get the version of the mailcow instance.
        :return: Response from the mailcow API.
        """
        return self.get('/get/status/version')

    def getStatusVmail(self):
        """
        Get the vmail status from the mailcow instance.
        :return: Response from the mailcow API.
        """
        return self.get('/get/status/vmail')

    def getStatusContainers(self):
        """
        Get the status of containers from the mailcow instance.
        :return: Response from the mailcow API.
        """
        return self.get('/get/status/containers')

    def get(self, endpoint, params=None):
        """
        Make a GET request to the mailcow API.
        :param endpoint: The API endpoint to get from.
        :param params: Parameters to be sent in the GET request.
        :return: Response from the mailcow API.
        """

        url = f"{self.baseUrl}{self.apiUrl}/{endpoint.lstrip('/')}"
        headers = {
            "Content-Type": "application/json",
            "Host": self.host
        }
        if self.apiKey:
            headers["X-Api-Key"] = self.apiKey
        response = requests.get(
            url,
            params=params,
            headers=headers,
            verify=not self.ignore_ssl_errors
        )
        response.raise_for_status()
        return response.json()

    def editDomain(self, domain, attributes):
        """
        Edit an existing domain in the mailcow instance.
        :param domain: Name of the domain to edit
        :param attributes: Dictionary containing the new domain attributes.
        """

        items = [domain]
        return self.edit('/edit/domain', items, attributes)

    def editMailbox(self, mailbox, attributes):
        """
        Edit an existing mailbox in the mailcow instance.
        :param mailbox: Name of the mailbox to edit
        :param attributes: Dictionary containing the new mailbox attributes.
        """

        items = [mailbox]
        return self.edit('/edit/mailbox', items, attributes)

    def editAlias(self, alias, attributes):
        """
        Edit an existing alias in the mailcow instance.
        :param alias: Name of the alias to edit
        :param attributes: Dictionary containing the new alias attributes.
        """

        items = [alias]
        return self.edit('/edit/alias', items, attributes)

    def editSyncjob(self, syncjob, attributes):
        """
        Edit an existing sync job in the mailcow instance.
        :param syncjob: Name of the sync job to edit
        :param attributes: Dictionary containing the new sync job attributes.
        """

        items = [syncjob]
        return self.edit('/edit/syncjob', items, attributes)

    def editDomainadmin(self, username, attributes):
        """
        Edit an existing domain admin in the mailcow instance.
        :param username: Username of the domain admin to edit
        :param attributes: Dictionary containing the new domain admin attributes.
        """

        items = [username]
        return self.edit('/edit/domain-admin', items, attributes)

    def edit(self, endpoint, items, attributes):
        """
        Make a POST request to edit items in the mailcow API.
        :param items: List of items to edit.
        :param attributes: Dictionary containing the new attributes for the items.
        :return: Response from the mailcow API.
        """

        url = f"{self.baseUrl}{self.apiUrl}/{endpoint.lstrip('/')}"
        headers = {
            "Content-Type": "application/json",
            "Host": self.host
        }
        if self.apiKey:
            headers["X-Api-Key"] = self.apiKey
        data = {
            "items": items,
            "attr": attributes
        }
        response = requests.post(
            url,
            json=data,
            headers=headers,
            verify=not self.ignore_ssl_errors
        )
        response.raise_for_status()
        return response.json()


    # System Functions
    def runSyncjob(self, id, force=False):
        """
        Run a sync job.
        :param id: ID of the sync job to run.
        :return: Response from the imapsync script.
        """

        creds_path = "/app/sieve.creds"

        conn = mysql.connector.connect(**self.db_config)
        cursor = conn.cursor(dictionary=True)

        with open(creds_path, 'r') as file:
            master_user, master_pass = file.read().strip().split(':')

        query = ("SELECT * FROM imapsync WHERE id = %s")
        cursor.execute(query, (id,))

        success = False
        syncjob = cursor.fetchone()
        if not syncjob:
            cursor.close()
            conn.close()
            return f"Sync job with ID {id} not found."
        if syncjob['active'] == 0 and not force:
            cursor.close()
            conn.close()
            return f"Sync job with ID {id} is not active."

        enc1_flag = "--tls1" if syncjob['enc1'] == "TLS" else "--ssl1" if syncjob['enc1'] == "SSL" else None


        passfile1_path = f"/tmp/passfile1_{id}.txt"
        passfile2_path = f"/tmp/passfile2_{id}.txt"
        passfile1_cmd = [
            "sh", "-c",
            f"echo {syncjob['password1']} > {passfile1_path}"
        ]
        passfile2_cmd = [
            "sh", "-c",
            f"echo {master_pass} > {passfile2_path}"
        ]

        self.docker.exec_command("dovecot-mailcow", passfile1_cmd)
        self.docker.exec_command("dovecot-mailcow", passfile2_cmd)

        imapsync_cmd = [
            "/usr/local/bin/imapsync",
            "--tmpdir", "/tmp",
            "--nofoldersizes",
            "--addheader"
        ]

        if int(syncjob['timeout1']) > 0:
            imapsync_cmd.extend(['--timeout1', str(syncjob['timeout1'])])
        if int(syncjob['timeout2']) > 0:
            imapsync_cmd.extend(['--timeout2', str(syncjob['timeout2'])])
        if syncjob['exclude']:
            imapsync_cmd.extend(['--exclude', syncjob['exclude']])
        if syncjob['subfolder2']:
            imapsync_cmd.extend(['--subfolder2', syncjob['subfolder2']])
        if int(syncjob['maxage']) > 0:
            imapsync_cmd.extend(['--maxage', str(syncjob['maxage'])])
        if int(syncjob['maxbytespersecond']) > 0:
            imapsync_cmd.extend(['--maxbytespersecond', str(syncjob['maxbytespersecond'])])
        if int(syncjob['delete2duplicates']) == 1:
            imapsync_cmd.append("--delete2duplicates")
        if int(syncjob['subscribeall']) == 1:
            imapsync_cmd.append("--subscribeall")
        if int(syncjob['delete1']) == 1:
            imapsync_cmd.append("--delete")
        if int(syncjob['delete2']) == 1:
            imapsync_cmd.append("--delete2")
        if int(syncjob['automap']) == 1:
            imapsync_cmd.append("--automap")
        if int(syncjob['skipcrossduplicates']) == 1:
            imapsync_cmd.append("--skipcrossduplicates")
        if enc1_flag:
            imapsync_cmd.append(enc1_flag)

        imapsync_cmd.extend([
            "--host1", syncjob['host1'],
            "--user1", syncjob['user1'],
            "--passfile1", passfile1_path,
            "--port1", str(syncjob['port1']),
            "--host2", "localhost",
            "--user2", f"{syncjob['user2']}*{master_user}",
            "--passfile2", passfile2_path
        ])

        if syncjob['dry'] == 1:
            imapsync_cmd.append("--dry")

        imapsync_cmd.extend([
            "--no-modulesversion",
            "--noreleasecheck"
        ])

        try:
            cursor.execute("UPDATE imapsync SET is_running = 1, success = NULL, exit_status = NULL WHERE id = %s", (id,))
            conn.commit()

            result = self.docker.exec_command("dovecot-mailcow", imapsync_cmd)
            print(result)

            success = result['status'] == "success" and result['exit_code'] == 0
            cursor.execute(
                "UPDATE imapsync SET returned_text = %s, success = %s, exit_status = %s WHERE id = %s",
                (result['output'], int(success), result['exit_code'], id)
            )
            conn.commit()

        except Exception as e:
            cursor.execute(
                "UPDATE imapsync SET returned_text = %s, success = 0 WHERE id = %s",
                (str(e), id)
            )
            conn.commit()

        finally:
            cursor.execute("UPDATE imapsync SET last_run = NOW(), is_running = 0 WHERE id = %s", (id,))
            conn.commit()

        delete_passfile1_cmd = [
            "sh", "-c",
            f"rm -f {passfile1_path}"
        ]
        delete_passfile2_cmd = [
            "sh", "-c",
            f"rm -f {passfile2_path}"
        ]
        self.docker.exec_command("dovecot-mailcow", delete_passfile1_cmd)
        self.docker.exec_command("dovecot-mailcow", delete_passfile2_cmd)

        cursor.close()
        conn.close()

        return "Sync job completed successfully." if success else "Sync job failed."
