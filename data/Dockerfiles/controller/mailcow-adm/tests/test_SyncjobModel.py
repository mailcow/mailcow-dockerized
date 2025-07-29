import pytest
import json
import sys
import os

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../")))
from models.DomainModel import DomainModel
from models.MailboxModel import MailboxModel
from models.SyncjobModel import SyncjobModel


def test_model():
    # Generate random Mailbox
    random_username = f"mbox_test@mailcow.local"
    random_password = f"{os.urandom(4).hex()}"

    # Create an instance of SyncjobModel
    model = SyncjobModel(
        username=random_username,
        host1="mailcow.local",
        port1=993,
        user1="testuser@mailcow.local",
        password1="testpassword",
        enc1="SSL",
    )

    # Test the parser_command attribute
    assert model.parser_command == "syncjob", "Parser command should be 'syncjob'"

    # add Domain and Mailbox for testing
    domain_model = DomainModel(domain="mailcow.local")
    domain_model.add()
    mbox_model = MailboxModel(username=random_username, password=random_password)
    mbox_model.add()

    # 1. Syncjob add tests, should success
    r_add = model.add()
    assert isinstance(r_add, list), f"Expected a array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add) > 0 and len(r_add) <= 2, f"Wrong array received: {json.dumps(r_add, indent=2)}"
    assert "type" in r_add[0], f"'type' key missing in response: {json.dumps(r_add, indent=2)}"
    assert r_add[0]['type'] == "success", f"Wrong 'type' received: {r_add[0]['type']}\n{json.dumps(r_add, indent=2)}"
    assert "msg" in r_add[0], f"'msg' key missing in response: {json.dumps(r_add, indent=2)}"
    assert isinstance(r_add[0]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add[0]['msg']) > 0 and len(r_add[0]['msg']) <= 3, f"Wrong 'msg' array received: {json.dumps(r_add, indent=2)}"
    assert r_add[0]['msg'][0] == "mailbox_modified", f"Wrong 'msg' received: {r_add[0]['msg'][0]}, expected: 'mailbox_modified'\n{json.dumps(r_add, indent=2)}"

    # Assign created syncjob ID for further tests
    model.id = r_add[0]['msg'][2]

    # 2. Syncjob add tests, should fail because the syncjob already exists
    r_add = model.add()
    assert isinstance(r_add, list), f"Expected a array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add) > 0, f"Wrong array received: {json.dumps(r_add, indent=2)}"
    assert "type" in r_add[0], f"'type' key missing in response: {json.dumps(r_add, indent=2)}"
    assert r_add[0]['type'] == "danger", f"Wrong 'type' received: {r_add[0]['type']}\n{json.dumps(r_add, indent=2)}"
    assert "msg" in r_add[0], f"'msg' key missing in response: {json.dumps(r_add, indent=2)}"
    assert isinstance(r_add[0]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add[0]['msg']) > 0 and len(r_add[0]['msg']) <= 2, f"Wrong 'msg' array received: {json.dumps(r_add, indent=2)}"
    assert r_add[0]['msg'][0] == "object_exists", f"Wrong 'msg' received: {r_add[0]['msg'][0]}, expected: 'object_exists'\n{json.dumps(r_add, indent=2)}"

    # 3. Syncjob get tests
    r_get = model.get()
    assert isinstance(r_get, list), f"Expected a list but received: {json.dumps(r_get, indent=2)}"
    assert "user2" in r_get[0], f"'user2' key missing in response: {json.dumps(r_get, indent=2)}"
    assert "host1" in r_get[0], f"'host1' key missing in response: {json.dumps(r_get, indent=2)}"
    assert "port1" in r_get[0], f"'port1' key missing in response: {json.dumps(r_get, indent=2)}"
    assert "user1" in r_get[0], f"'user1' key missing in response: {json.dumps(r_get, indent=2)}"
    assert "enc1" in r_get[0], f"'enc1' key missing in response: {json.dumps(r_get, indent=2)}"
    assert r_get[0]['user2'] == model.username, f"Wrong 'user2' received: {r_get[0]['user2']}, expected: {model.username}\n{json.dumps(r_get, indent=2)}"
    assert r_get[0]['host1'] == model.host1, f"Wrong 'host1' received: {r_get[0]['host1']}, expected: {model.host1}\n{json.dumps(r_get, indent=2)}"
    assert r_get[0]['port1'] == model.port1, f"Wrong 'port1' received: {r_get[0]['port1']}, expected: {model.port1}\n{json.dumps(r_get, indent=2)}"
    assert r_get[0]['user1'] == model.user1, f"Wrong 'user1' received: {r_get[0]['user1']}, expected: {model.user1}\n{json.dumps(r_get, indent=2)}"
    assert r_get[0]['enc1'] == model.enc1, f"Wrong 'enc1' received: {r_get[0]['enc1']}, expected: {model.enc1}\n{json.dumps(r_get, indent=2)}"

    # 4. Syncjob edit tests
    model.active = 1
    r_edit = model.edit()
    assert isinstance(r_edit, list), f"Expected a array but received: {json.dumps(r_edit, indent=2)}"
    assert len(r_edit) > 0, f"Wrong array received: {json.dumps(r_edit, indent=2)}"
    assert "type" in r_edit[0], f"'type' key missing in response: {json.dumps(r_edit, indent=2)}"
    assert r_edit[0]['type'] == "success", f"Wrong 'type' received: {r_edit[0]['type']}\n{json.dumps(r_edit, indent=2)}"
    assert "msg" in r_edit[0], f"'msg' key missing in response: {json.dumps(r_edit, indent=2)}"
    assert isinstance(r_edit[0]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_edit, indent=2)}"
    assert len(r_edit[0]['msg']) > 0 and len(r_edit[0]['msg']) <= 2, f"Wrong 'msg' array received: {json.dumps(r_edit, indent=2)}"
    assert r_edit[0]['msg'][0] == "mailbox_modified", f"Wrong 'msg' received: {r_edit[0]['msg'][0]}, expected: 'mailbox_modified'\n{json.dumps(r_edit, indent=2)}"

    # 5. Syncjob delete tests
    r_delete = model.delete()
    assert isinstance(r_delete, list), f"Expected a array but received: {json.dumps(r_delete, indent=2)}"
    assert len(r_delete) > 0, f"Wrong array received: {json.dumps(r_delete, indent=2)}"
    assert "type" in r_delete[0], f"'type' key missing in response: {json.dumps(r_delete, indent=2)}"
    assert r_delete[0]['type'] == "success", f"Wrong 'type' received: {r_delete[0]['type']}\n{json.dumps(r_delete, indent=2)}"
    assert "msg" in r_delete[0], f"'msg' key missing in response: {json.dumps(r_delete, indent=2)}"
    assert isinstance(r_delete[0]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_delete, indent=2)}"
    assert len(r_delete[0]['msg']) > 0 and len(r_delete[0]['msg']) <= 2, f"Wrong 'msg' array received: {json.dumps(r_delete, indent=2)}"
    assert r_delete[0]['msg'][0] == "deleted_syncjob", f"Wrong 'msg' received: {r_delete[0]['msg'][0]}, expected: 'deleted_syncjob'\n{json.dumps(r_delete, indent=2)}"

    # delete testing Domain and Mailbox
    mbox_model.delete()
    domain_model.delete()


if __name__ == "__main__":
  print("Running SyncjobModel tests...")
  test_model()
  print("All tests passed!")
