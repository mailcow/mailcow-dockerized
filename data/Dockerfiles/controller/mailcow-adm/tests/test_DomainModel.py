import pytest
import json
import sys
import os

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../")))
from models.DomainModel import DomainModel


def test_model():
    # Create an instance of DomainModel
    model = DomainModel(
        domain="mailcow.local",
    )

    # Test the parser_command attribute
    assert model.parser_command == "domain", "Parser command should be 'domain'"

    # 1. Domain add tests, should success
    r_add = model.add()
    assert isinstance(r_add, list), f"Expected a array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add) > 0 and len(r_add) >= 2, f"Wrong array received: {json.dumps(r_add, indent=2)}"
    assert "type" in r_add[1], f"'type' key missing in response: {json.dumps(r_add, indent=2)}"
    assert r_add[1]['type'] == "success", f"Wrong 'type' received: {r_add[1]['type']}\n{json.dumps(r_add, indent=2)}"
    assert "msg" in r_add[1], f"'msg' key missing in response: {json.dumps(r_add, indent=2)}"
    assert isinstance(r_add[1]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add[1]['msg']) > 0 and len(r_add[1]['msg']) <= 2, f"Wrong 'msg' array received: {json.dumps(r_add, indent=2)}"
    assert r_add[1]['msg'][0] == "domain_added", f"Wrong 'msg' received: {r_add[1]['msg'][0]}, expected: 'domain_added'\n{json.dumps(r_add, indent=2)}"

    # 2. Domain add tests, should fail because the domain already exists
    r_add = model.add()
    assert isinstance(r_add, list), f"Expected a array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add) > 0, f"Wrong array received: {json.dumps(r_add, indent=2)}"
    assert "type" in r_add[0], f"'type' key missing in response: {json.dumps(r_add, indent=2)}"
    assert r_add[0]['type'] == "danger", f"Wrong 'type' received: {r_add[0]['type']}\n{json.dumps(r_add, indent=2)}"
    assert "msg" in r_add[0], f"'msg' key missing in response: {json.dumps(r_add, indent=2)}"
    assert isinstance(r_add[0]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add[0]['msg']) > 0 and len(r_add[0]['msg']) <= 2, f"Wrong 'msg' array received: {json.dumps(r_add, indent=2)}"
    assert r_add[0]['msg'][0] == "domain_exists", f"Wrong 'msg' received: {r_add[0]['msg'][0]}, expected: 'domain_exists'\n{json.dumps(r_add, indent=2)}"

    # 3. Domain get tests
    r_get = model.get()
    assert isinstance(r_get, dict), f"Expected a dict but received: {json.dumps(r_get, indent=2)}"
    assert "domain_name" in r_get, f"'domain_name' key missing in response: {json.dumps(r_get, indent=2)}"
    assert r_get['domain_name'] == model.domain, f"Wrong 'domain_name' received: {r_get['domain_name']}, expected: {model.domain}\n{json.dumps(r_get, indent=2)}"

    # 4. Domain edit tests
    model.active = 0
    r_edit = model.edit()
    assert isinstance(r_edit, list), f"Expected a array but received: {json.dumps(r_edit, indent=2)}"
    assert len(r_edit) > 0, f"Wrong array received: {json.dumps(r_edit, indent=2)}"
    assert "type" in r_edit[0], f"'type' key missing in response: {json.dumps(r_edit, indent=2)}"
    assert r_edit[0]['type'] == "success", f"Wrong 'type' received: {r_edit[0]['type']}\n{json.dumps(r_edit, indent=2)}"
    assert "msg" in r_edit[0], f"'msg' key missing in response: {json.dumps(r_edit, indent=2)}"
    assert isinstance(r_edit[0]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_edit, indent=2)}"
    assert len(r_edit[0]['msg']) > 0 and len(r_edit[0]['msg']) <= 2, f"Wrong 'msg' array received: {json.dumps(r_edit, indent=2)}"
    assert r_edit[0]['msg'][0] == "domain_modified", f"Wrong 'msg' received: {r_edit[0]['msg'][0]}, expected: 'domain_modified'\n{json.dumps(r_edit, indent=2)}"

    # 5. Domain delete tests
    r_delete = model.delete()
    assert isinstance(r_delete, list), f"Expected a array but received: {json.dumps(r_delete, indent=2)}"
    assert len(r_delete) > 0, f"Wrong array received: {json.dumps(r_delete, indent=2)}"
    assert "type" in r_delete[0], f"'type' key missing in response: {json.dumps(r_delete, indent=2)}"
    assert r_delete[0]['type'] == "success", f"Wrong 'type' received: {r_delete[0]['type']}\n{json.dumps(r_delete, indent=2)}"
    assert "msg" in r_delete[0], f"'msg' key missing in response: {json.dumps(r_delete, indent=2)}"
    assert isinstance(r_delete[0]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_delete, indent=2)}"
    assert len(r_delete[0]['msg']) > 0 and len(r_delete[0]['msg']) <= 2, f"Wrong 'msg' array received: {json.dumps(r_delete, indent=2)}"
    assert r_delete[0]['msg'][0] == "domain_removed", f"Wrong 'msg' received: {r_delete[0]['msg'][0]}, expected: 'domain_removed'\n{json.dumps(r_delete, indent=2)}"


if __name__ == "__main__":
  print("Running DomainModel tests...")
  test_model()
  print("All tests passed!")
