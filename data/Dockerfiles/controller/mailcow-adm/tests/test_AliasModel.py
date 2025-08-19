import pytest
import json
import sys
import os

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../")))
from models.DomainModel import DomainModel
from models.AliasModel import AliasModel


def test_model():
    # Generate random alias
    random_alias = f"alias_test{os.urandom(4).hex()}@mailcow.local"

    # Create an instance of AliasModel
    model = AliasModel(
        address=random_alias,
        goto="test@mailcow.local,test2@mailcow.local"
    )

    # Test the parser_command attribute
    assert model.parser_command == "alias", "Parser command should be 'alias'"

    # add Domain for testing
    domain_model = DomainModel(domain="mailcow.local")
    domain_model.add()

    # 1. Alias add tests, should success
    r_add = model.add()
    assert isinstance(r_add, list), f"Expected a array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add) > 0, f"Wrong array received: {json.dumps(r_add, indent=2)}"
    assert "type" in r_add[0], f"'type' key missing in response: {json.dumps(r_add, indent=2)}"
    assert r_add[0]['type'] == "success", f"Wrong 'type' received: {r_add[0]['type']}\n{json.dumps(r_add, indent=2)}"
    assert "msg" in r_add[0], f"'msg' key missing in response: {json.dumps(r_add, indent=2)}"
    assert isinstance(r_add[0]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add[0]['msg']) > 0 and len(r_add[0]['msg']) <= 3, f"Wrong 'msg' array received: {json.dumps(r_add, indent=2)}"
    assert r_add[0]['msg'][0] == "alias_added", f"Wrong 'msg' received: {r_add[0]['msg'][0]}, expected: 'alias_added'\n{json.dumps(r_add, indent=2)}"

    # Assign created alias ID for further tests
    model.id = r_add[0]['msg'][2]

    # 2. Alias add tests, should fail because the alias already exists
    r_add = model.add()
    assert isinstance(r_add, list), f"Expected a array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add) > 0, f"Wrong array received: {json.dumps(r_add, indent=2)}"
    assert "type" in r_add[0], f"'type' key missing in response: {json.dumps(r_add, indent=2)}"
    assert r_add[0]['type'] == "danger", f"Wrong 'type' received: {r_add[0]['type']}\n{json.dumps(r_add, indent=2)}"
    assert "msg" in r_add[0], f"'msg' key missing in response: {json.dumps(r_add, indent=2)}"
    assert isinstance(r_add[0]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add[0]['msg']) > 0 and len(r_add[0]['msg']) <= 2, f"Wrong 'msg' array received: {json.dumps(r_add, indent=2)}"
    assert r_add[0]['msg'][0] == "is_alias_or_mailbox", f"Wrong 'msg' received: {r_add[0]['msg'][0]}, expected: 'is_alias_or_mailbox'\n{json.dumps(r_add, indent=2)}"

    # 3. Alias get tests
    r_get = model.get()
    assert isinstance(r_get, dict), f"Expected a dict but received: {json.dumps(r_get, indent=2)}"
    assert "domain" in r_get, f"'domain' key missing in response: {json.dumps(r_get, indent=2)}"
    assert "goto" in r_get, f"'goto' key missing in response: {json.dumps(r_get, indent=2)}"
    assert "address" in r_get, f"'address' key missing in response: {json.dumps(r_get, indent=2)}"
    assert r_get['domain'] == model.address.split("@")[1], f"Wrong 'domain' received: {r_get['domain']}, expected: {model.address.split('@')[1]}\n{json.dumps(r_get, indent=2)}"
    assert r_get['goto'] == model.goto, f"Wrong 'goto' received: {r_get['goto']}, expected: {model.goto}\n{json.dumps(r_get, indent=2)}"
    assert r_get['address'] == model.address, f"Wrong 'address' received: {r_get['address']}, expected: {model.address}\n{json.dumps(r_get, indent=2)}"

    # 4. Alias edit tests
    model.goto = "test@mailcow.local"
    model.active = 0
    r_edit = model.edit()
    assert isinstance(r_edit, list), f"Expected a array but received: {json.dumps(r_edit, indent=2)}"
    assert len(r_edit) > 0, f"Wrong array received: {json.dumps(r_edit, indent=2)}"
    assert "type" in r_edit[0], f"'type' key missing in response: {json.dumps(r_edit, indent=2)}"
    assert r_edit[0]['type'] == "success", f"Wrong 'type' received: {r_edit[0]['type']}\n{json.dumps(r_edit, indent=2)}"
    assert "msg" in r_edit[0], f"'msg' key missing in response: {json.dumps(r_edit, indent=2)}"
    assert isinstance(r_edit[0]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_edit, indent=2)}"
    assert len(r_edit[0]['msg']) > 0 and len(r_edit[0]['msg']) <= 2, f"Wrong 'msg' array received: {json.dumps(r_edit, indent=2)}"
    assert r_edit[0]['msg'][0] == "alias_modified", f"Wrong 'msg' received: {r_edit[0]['msg'][0]}, expected: 'alias_modified'\n{json.dumps(r_edit, indent=2)}"

    # 5. Alias delete tests
    r_delete = model.delete()
    assert isinstance(r_delete, list), f"Expected a array but received: {json.dumps(r_delete, indent=2)}"
    assert len(r_delete) > 0, f"Wrong array received: {json.dumps(r_delete, indent=2)}"
    assert "type" in r_delete[0], f"'type' key missing in response: {json.dumps(r_delete, indent=2)}"
    assert r_delete[0]['type'] == "success", f"Wrong 'type' received: {r_delete[0]['type']}\n{json.dumps(r_delete, indent=2)}"
    assert "msg" in r_delete[0], f"'msg' key missing in response: {json.dumps(r_delete, indent=2)}"
    assert isinstance(r_delete[0]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_delete, indent=2)}"
    assert len(r_delete[0]['msg']) > 0 and len(r_delete[0]['msg']) <= 2, f"Wrong 'msg' array received: {json.dumps(r_delete, indent=2)}"
    assert r_delete[0]['msg'][0] == "alias_removed", f"Wrong 'msg' received: {r_delete[0]['msg'][0]}, expected: 'alias_removed'\n{json.dumps(r_delete, indent=2)}"

    # delete testing Domain
    domain_model.delete()


if __name__ == "__main__":
  print("Running AliasModel tests...")
  test_model()
  print("All tests passed!")
