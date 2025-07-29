import pytest
import json
import sys
import os

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../")))
from models.DomainModel import DomainModel
from models.MailboxModel import MailboxModel


def test_model():
    # Generate random mailbox
    random_username = f"mbox_test{os.urandom(4).hex()}@mailcow.local"
    random_password = f"{os.urandom(4).hex()}"

    # Create an instance of MailboxModel
    model = MailboxModel(
        username=random_username,
        password=random_password
    )

    # Test the parser_command attribute
    assert model.parser_command == "mailbox", "Parser command should be 'mailbox'"

    # add Domain for testing
    domain_model = DomainModel(domain="mailcow.local")
    domain_model.add()

    # 1. Mailbox add tests, should success
    r_add = model.add()
    assert isinstance(r_add, list), f"Expected a array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add) > 0 and len(r_add) <= 2, f"Wrong array received: {json.dumps(r_add, indent=2)}"
    assert "type" in r_add[1], f"'type' key missing in response: {json.dumps(r_add, indent=2)}"
    assert r_add[1]['type'] == "success", f"Wrong 'type' received: {r_add[1]['type']}\n{json.dumps(r_add, indent=2)}"
    assert "msg" in r_add[1], f"'msg' key missing in response: {json.dumps(r_add, indent=2)}"
    assert isinstance(r_add[1]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add[1]['msg']) > 0 and len(r_add[1]['msg']) <= 3, f"Wrong 'msg' array received: {json.dumps(r_add, indent=2)}"
    assert r_add[1]['msg'][0] == "mailbox_added", f"Wrong 'msg' received: {r_add[1]['msg'][0]}, expected: 'mailbox_added'\n{json.dumps(r_add, indent=2)}"

    # 2. Mailbox add tests, should fail because the mailbox already exists
    r_add = model.add()
    assert isinstance(r_add, list), f"Expected a array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add) > 0, f"Wrong array received: {json.dumps(r_add, indent=2)}"
    assert "type" in r_add[0], f"'type' key missing in response: {json.dumps(r_add, indent=2)}"
    assert r_add[0]['type'] == "danger", f"Wrong 'type' received: {r_add[0]['type']}\n{json.dumps(r_add, indent=2)}"
    assert "msg" in r_add[0], f"'msg' key missing in response: {json.dumps(r_add, indent=2)}"
    assert isinstance(r_add[0]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add[0]['msg']) > 0 and len(r_add[0]['msg']) <= 2, f"Wrong 'msg' array received: {json.dumps(r_add, indent=2)}"
    assert r_add[0]['msg'][0] == "object_exists", f"Wrong 'msg' received: {r_add[0]['msg'][0]}, expected: 'object_exists'\n{json.dumps(r_add, indent=2)}"

    # 3. Mailbox get tests
    r_get = model.get()
    assert isinstance(r_get, dict), f"Expected a dict but received: {json.dumps(r_get, indent=2)}"
    assert "domain" in r_get, f"'domain' key missing in response: {json.dumps(r_get, indent=2)}"
    assert "local_part" in r_get, f"'local_part' key missing in response: {json.dumps(r_get, indent=2)}"
    assert r_get['domain'] == model.domain, f"Wrong 'domain' received: {r_get['domain']}, expected: {model.domain}\n{json.dumps(r_get, indent=2)}"
    assert r_get['local_part'] == model.local_part, f"Wrong 'local_part' received: {r_get['local_part']}, expected: {model.local_part}\n{json.dumps(r_get, indent=2)}"

    # 4. Mailbox edit tests
    model.active = 0
    r_edit = model.edit()
    assert isinstance(r_edit, list), f"Expected a array but received: {json.dumps(r_edit, indent=2)}"
    assert len(r_edit) > 0, f"Wrong array received: {json.dumps(r_edit, indent=2)}"
    assert "type" in r_edit[0], f"'type' key missing in response: {json.dumps(r_edit, indent=2)}"
    assert r_edit[0]['type'] == "success", f"Wrong 'type' received: {r_edit[0]['type']}\n{json.dumps(r_edit, indent=2)}"
    assert "msg" in r_edit[0], f"'msg' key missing in response: {json.dumps(r_edit, indent=2)}"
    assert isinstance(r_edit[0]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_edit, indent=2)}"
    assert len(r_edit[0]['msg']) > 0 and len(r_edit[0]['msg']) <= 2, f"Wrong 'msg' array received: {json.dumps(r_edit, indent=2)}"
    assert r_edit[0]['msg'][0] == "mailbox_modified", f"Wrong 'msg' received: {r_edit[0]['msg'][0]}, expected: 'mailbox_modified'\n{json.dumps(r_edit, indent=2)}"

    # 5. Mailbox delete tests
    r_delete = model.delete()
    assert isinstance(r_delete, list), f"Expected a array but received: {json.dumps(r_delete, indent=2)}"
    assert len(r_delete) > 0, f"Wrong array received: {json.dumps(r_delete, indent=2)}"
    assert "type" in r_delete[0], f"'type' key missing in response: {json.dumps(r_delete, indent=2)}"
    assert r_delete[0]['type'] == "success", f"Wrong 'type' received: {r_delete[0]['type']}\n{json.dumps(r_delete, indent=2)}"
    assert "msg" in r_delete[0], f"'msg' key missing in response: {json.dumps(r_delete, indent=2)}"
    assert isinstance(r_delete[0]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_delete, indent=2)}"
    assert len(r_delete[0]['msg']) > 0 and len(r_delete[0]['msg']) <= 2, f"Wrong 'msg' array received: {json.dumps(r_delete, indent=2)}"
    assert r_delete[0]['msg'][0] == "mailbox_removed", f"Wrong 'msg' received: {r_delete[0]['msg'][0]}, expected: 'mailbox_removed'\n{json.dumps(r_delete, indent=2)}"

    # delete testing Domain
    domain_model.delete()


if __name__ == "__main__":
  print("Running MailboxModel tests...")
  test_model()
  print("All tests passed!")
