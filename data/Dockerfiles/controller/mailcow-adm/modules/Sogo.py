import requests
import urllib3
import os
from uuid import uuid4
from collections import defaultdict


class Sogo:
    def __init__(self, username, password=""):
        self.apiUrl = "/SOGo/so"
        self.davUrl = "/SOGo/dav"
        self.ignore_ssl_errors = True

        self.baseUrl = f"https://{os.getenv('IPv4_NETWORK', '172.22.1')}.247"
        self.host = os.getenv("MAILCOW_HOSTNAME", "")
        if self.ignore_ssl_errors:
            urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
        self.username = username
        self.password = password

    def addCalendar(self, calendar_name):
        """
        Add a new calendar to the sogo instance.
        :param calendar_name: Name of the calendar to be created
        :return: Response from the sogo API.
        """

        res = self.post(f"/{self.username}/Calendar/createFolder", {
            "name": calendar_name
        })
        try:
            return res.json()
        except ValueError:
            return res.text

    def getCalendarIdByName(self, calendar_name):
        """
        Get the calendar ID by its name.
        :param calendar_name: Name of the calendar to find
        :return: Calendar ID if found, otherwise None.
        """

        res = self.get(f"/{self.username}/Calendar/calendarslist")
        try:
            for calendar in res.json()["calendars"]:
                if calendar['name'] == calendar_name:
                    return calendar['id']
        except ValueError:
            return None
        return None

    def getCalendar(self):
        """
        Get calendar list.
        :return: Response from SOGo API.
        """

        res = self.get(f"/{self.username}/Calendar/calendarslist")
        try:
            return res.json()
        except ValueError:
            return res.text

    def deleteCalendar(self, calendar_id):
        """
        Delete a calendar.
        :param calendar_id: ID of the calendar to be deleted
        :return: Response from SOGo API.
        """
        res = self.get(f"/{self.username}/Calendar/{calendar_id}/delete")
        return res.status_code == 204

    def importCalendar(self, calendar_name, ics_file):
        """
        Import a calendar from an ICS file.
        :param calendar_name: Name of the calendar to import into
        :param ics_file: Path to the ICS file to import
        :return: Response from SOGo API.
        """

        try:
            with open(ics_file, "rb") as f:
                pass
        except Exception as e:
            print(f"Could not open ICS file '{ics_file}': {e}")
            return {"status": "error", "message": str(e)}

        new_calendar = self.addCalendar(calendar_name)
        selected_calendar = new_calendar.json()["id"]

        url = f"{self.baseUrl}{self.apiUrl}/{self.username}/Calendar/{selected_calendar}/import"
        auth = (self.username, self.password)
        with open(ics_file, "rb") as f:
            files = {'icsFile': (ics_file, f, 'text/calendar')}
            res = requests.post(
                url,
                files=files,
                auth=auth,
                verify=not self.ignore_ssl_errors
            )
            try:
                return res.json()
            except ValueError:
                return res.text

        return None

    def setCalendarACL(self, calendar_id, sharee_email, acl="r", subscribe=False):
        """
        Set CalDAV calendar permissions for a user (sharee).
        :param calendar_id: ID of the calendar to share
        :param sharee_email: Email of the user to share with
        :param acl: "w" for write, "r" for read-only or combination "rw" for read-write
        :param subscribe: True will scubscribe the sharee to the calendar
        :return: None
        """

        # Access rights
        if acl == "" or len(acl) > 2:
            return "Invalid acl level specified. Use 'w', 'r' or combinations like 'rw'."
        rights = [{
            "c_email": sharee_email,
            "uid": sharee_email,
            "userClass": "normal-user",
            "rights": {
                "Public": "None",
                "Private": "None",
                "Confidential": "None",
                "canCreateObjects": 0,
                "canEraseObjects": 0
            }
        }]
        if "w" in acl:
            rights[0]["rights"]["canCreateObjects"] = 1
            rights[0]["rights"]["canEraseObjects"] = 1
        if "r" in acl:
            rights[0]["rights"]["Public"] = "Viewer"
            rights[0]["rights"]["Private"] = "Viewer"
            rights[0]["rights"]["Confidential"] = "Viewer"

        r_add = self.get(f"/{self.username}/Calendar/{calendar_id}/addUserInAcls?uid={sharee_email}")
        if r_add.status_code < 200 or r_add.status_code > 299:
            try:
                return r_add.json()
            except ValueError:
                return r_add.text

        r_save = self.post(f"/{self.username}/Calendar/{calendar_id}/saveUserRights", rights)
        if r_save.status_code < 200 or r_save.status_code > 299:
            try:
                return r_save.json()
            except ValueError:
                return r_save.text

        if subscribe:
            r_subscribe = self.get(f"/{self.username}/Calendar/{calendar_id}/subscribeUsers?uids={sharee_email}")
            if r_subscribe.status_code < 200 or r_subscribe.status_code > 299:
                try:
                    return r_subscribe.json()
                except ValueError:
                    return r_subscribe.text

        return r_save.status_code == 200

    def getCalendarACL(self, calendar_id):
        """
        Get CalDAV calendar permissions for a user (sharee).
        :param calendar_id: ID of the calendar to get ACL from
        :return: Response from SOGo API.
        """

        res = self.get(f"/{self.username}/Calendar/{calendar_id}/acls")
        try:
            return res.json()
        except ValueError:
            return res.text

    def deleteCalendarACL(self, calendar_id, sharee_email):
        """
        Delete a calendar ACL for a user (sharee).
        :param calendar_id: ID of the calendar to delete ACL from
        :param sharee_email: Email of the user whose ACL to delete
        :return: Response from SOGo API.
        """

        res = self.get(f"/{self.username}/Calendar/{calendar_id}/removeUserFromAcls?uid={sharee_email}")
        return res.status_code == 204

    def addAddressbook(self, addressbook_name):
        """
        Add a new addressbook to the sogo instance.
        :param addressbook_name: Name of the addressbook to be created
        :return: Response from the sogo API.
        """

        res = self.post(f"/{self.username}/Contacts/createFolder", {
            "name": addressbook_name
        })
        try:
            return res.json()
        except ValueError:
            return res.text

    def getAddressbookIdByName(self, addressbook_name):
        """
        Get the addressbook ID by its name.
        :param addressbook_name: Name of the addressbook to find
        :return: Addressbook ID if found, otherwise None.
        """

        res = self.get(f"/{self.username}/Contacts/addressbooksList")
        try:
            for addressbook in res.json()["addressbooks"]:
                if addressbook['name'] == addressbook_name:
                    return addressbook['id']
        except ValueError:
            return None
        return None

    def deleteAddressbook(self, addressbook_id):
        """
        Delete an addressbook.
        :param addressbook_id: ID of the addressbook to be deleted
        :return: Response from SOGo API.
        """

        res = self.get(f"/{self.username}/Contacts/{addressbook_id}/delete")
        return res.status_code == 204

    def getAddressbookList(self):
        """
        Get addressbook list.
        :return: Response from SOGo API.
        """

        res = self.get(f"/{self.username}/Contacts/addressbooksList")
        try:
            return res.json()
        except ValueError:
            return res.text

    def setAddressbookACL(self, addressbook_id, sharee_email, acl="r", subscribe=False):
        """
        Set CalDAV addressbook permissions for a user (sharee).
        :param addressbook_id: ID of the addressbook to share
        :param sharee_email: Email of the user to share with
        :param acl: "w" for write, "r" for read-only or combination "rw" for read-write
        :param subscribe: True will subscribe the sharee to the addressbook
        :return: None
        """

        # Access rights
        if acl == "" or len(acl) > 2:
            print("Invalid acl level specified. Use 's', 'w', 'r' or combinations like 'rws'.")
            return "Invalid acl level specified. Use 'w', 'r' or combinations like 'rw'."
        rights = [{
            "c_email": sharee_email,
            "uid": sharee_email,
            "userClass": "normal-user",
            "rights": {
                "canCreateObjects": 0,
                "canEditObjects": 0,
                "canEraseObjects": 0,
                "canViewObjects": 0,
            }
        }]
        if "w" in acl:
            rights[0]["rights"]["canCreateObjects"] = 1
            rights[0]["rights"]["canEditObjects"] = 1
            rights[0]["rights"]["canEraseObjects"] = 1
        if "r" in acl:
            rights[0]["rights"]["canViewObjects"] = 1

        r_add = self.get(f"/{self.username}/Contacts/{addressbook_id}/addUserInAcls?uid={sharee_email}")
        if r_add.status_code < 200 or r_add.status_code > 299:
            try:
                return r_add.json()
            except ValueError:
                return r_add.text

        r_save = self.post(f"/{self.username}/Contacts/{addressbook_id}/saveUserRights", rights)
        if r_save.status_code < 200 or r_save.status_code > 299:
            try:
                return r_save.json()
            except ValueError:
                return r_save.text

        if subscribe:
            r_subscribe = self.get(f"/{self.username}/Contacts/{addressbook_id}/subscribeUsers?uids={sharee_email}")
            if r_subscribe.status_code < 200 or r_subscribe.status_code > 299:
                try:
                    return r_subscribe.json()
                except ValueError:
                    return r_subscribe.text

        return r_save.status_code == 200

    def getAddressbookACL(self, addressbook_id):
        """
        Get CalDAV addressbook permissions for a user (sharee).
        :param addressbook_id: ID of the addressbook to get ACL from
        :return: Response from SOGo API.
        """

        res = self.get(f"/{self.username}/Contacts/{addressbook_id}/acls")
        try:
            return res.json()
        except ValueError:
            return res.text

    def deleteAddressbookACL(self, addressbook_id, sharee_email):
        """
        Delete an addressbook ACL for a user (sharee).
        :param addressbook_id: ID of the addressbook to delete ACL from
        :param sharee_email: Email of the user whose ACL to delete
        :return: Response from SOGo API.
        """

        res = self.get(f"/{self.username}/Contacts/{addressbook_id}/removeUserFromAcls?uid={sharee_email}")
        return res.status_code == 204

    def getAddressbookNewGuid(self, addressbook_id):
        """
        Request a new GUID for a SOGo addressbook.
        :param addressbook_id: ID of the addressbook
        :return: JSON response from SOGo or None if not found
        """
        res = self.get(f"/{self.username}/Contacts/{addressbook_id}/newguid")
        try:
            return res.json()
        except ValueError:
            return res.text

    def addAddressbookContact(self, addressbook_id, contact_name, contact_email):
        """
        Save a vCard as a contact in the specified addressbook.
        :param addressbook_id: ID of the addressbook
        :param contact_name: Name of the contact
        :param contact_email: Email of the contact
        :return: JSON response from SOGo or None if not found
        """
        vcard_id = self.getAddressbookNewGuid(addressbook_id)
        contact_data = {
            "id": vcard_id["id"],
            "pid": vcard_id["pid"],
            "c_cn": contact_name,
            "emails": [{
                "type": "pref",
                "value": contact_email
            }],
            "isNew": True,
            "c_component": "vcard",
        }

        endpoint = f"/{self.username}/Contacts/{addressbook_id}/{vcard_id['id']}/saveAsContact"
        res = self.post(endpoint, contact_data)
        try:
            return res.json()
        except ValueError:
            return res.text

    def getAddressbookContacts(self, addressbook_id, contact_email=None):
        """
        Get all contacts from the specified addressbook.
        :param addressbook_id: ID of the addressbook
        :return: JSON response with contacts or None if not found
        """
        res = self.get(f"/{self.username}/Contacts/{addressbook_id}/view")
        try:
            res_json = res.json()
            headers = res_json.get("headers", [])
            if not headers or len(headers) < 2:
                return []

            field_names = headers[0]
            contacts = []
            for row in headers[1:]:
                contact = dict(zip(field_names, row))
                contacts.append(contact)

            if contact_email:
                contact = {}
                for c in contacts:
                    if c["c_mail"] == contact_email or c["c_cn"] == contact_email:
                        contact = c
                        break
                return contact

            return contacts
        except ValueError:
            return res.text

    def addAddressbookContactList(self, addressbook_id, contact_name, contact_email=None):
        """
        Add a new contact list to the addressbook.
        :param addressbook_id: ID of the addressbook
        :param contact_name: Name of the contact list
        :param contact_email: Comma-separated emails to include in the list
        :return: Response from SOGo API.
        """
        gal_domain = self.username.split("@")[-1]
        vlist_id = self.getAddressbookNewGuid(addressbook_id)
        contact_emails = contact_email.split(",") if contact_email else []
        contacts = self.getAddressbookContacts(addressbook_id)

        refs = []
        for contact in contacts:
            if contact['c_mail'] in contact_emails:
                refs.append({
                    "refs": [],
                    "categories": [],
                    "c_screenname": contact.get("c_screenname", ""),
                    "pid": contact.get("pid", vlist_id["pid"]),
                    "id": contact.get("id", ""),
                    "notes": [""],
                    "empty": " ",
                    "hasphoto": contact.get("hasphoto", 0),
                    "c_cn": contact.get("c_cn", ""),
                    "c_uid": contact.get("c_uid", None),
                    "containername": contact.get("containername", f"GAL {gal_domain}"),  # or your addressbook name
                    "sourceid": contact.get("sourceid", gal_domain),
                    "c_component": contact.get("c_component", "vcard"),
                    "c_sn": contact.get("c_sn", ""),
                    "c_givenname": contact.get("c_givenname", ""),
                    "c_name": contact.get("c_name", contact.get("id", "")),
                    "c_telephonenumber": contact.get("c_telephonenumber", ""),
                    "fn": contact.get("fn", ""),
                    "c_mail": contact.get("c_mail", ""),
                    "emails": contact.get("emails", []),
                    "c_o": contact.get("c_o", ""),
                    "reference": contact.get("id", ""),
                    "birthday": contact.get("birthday", "")
                })

        contact_data = {
            "refs": refs,
            "categories": [],
            "c_screenname": None,
            "pid": vlist_id["pid"],
            "c_component": "vlist",
            "notes": [""],
            "empty": " ",
            "isNew": True,
            "id": vlist_id["id"],
            "c_cn": contact_name,
            "birthday": ""
        }

        endpoint = f"/{self.username}/Contacts/{addressbook_id}/{vlist_id['id']}/saveAsList"
        res = self.post(endpoint, contact_data)
        try:
            return res.json()
        except ValueError:
            return res.text

    def deleteAddressbookItem(self, addressbook_id, contact_name):
        """
        Delete an addressbook item by its ID.
        :param addressbook_id: ID of the addressbook item to delete
        :param contact_name: Name of the contact to delete
        :return: Response from SOGo API.
        """
        res = self.getAddressbookContacts(addressbook_id, contact_name)

        if "id" not in res:
            print(f"Contact '{contact_name}' not found in addressbook '{addressbook_id}'.")
            return None
        res = self.post(f"/{self.username}/Contacts/{addressbook_id}/batchDelete", {
            "uids": [res["id"]],
        })
        return res.status_code == 204

    def get(self, endpoint, params=None):
        """
        Make a GET request to the mailcow API.
        :param endpoint: The API endpoint to get.
        :param params: Optional parameters for the GET request.
        :return: Response from the mailcow API.
        """
        url = f"{self.baseUrl}{self.apiUrl}{endpoint}"
        auth = (self.username, self.password)
        headers = {"Host": self.host}

        response = requests.get(
            url,
            params=params,
            auth=auth,
            headers=headers,
            verify=not self.ignore_ssl_errors
        )
        return response

    def post(self, endpoint, data):
        """
        Make a POST request to the mailcow API.
        :param endpoint: The API endpoint to post to.
        :param data: Data to be sent in the POST request.
        :return: Response from the mailcow API.
        """
        url = f"{self.baseUrl}{self.apiUrl}{endpoint}"
        auth = (self.username, self.password)
        headers = {"Host": self.host}

        response = requests.post(
            url,
            json=data,
            auth=auth,
            headers=headers,
            verify=not self.ignore_ssl_errors
        )
        return response

