import pytest
import json
import sys
import os

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../")))
from models.DomainModel import DomainModel
from models.DomainadminModel import DomainadminModel


def test_model():
    # Generate random domainadmin
    random_username = f"dadmin_test{os.urandom(4).hex()}"
    random_password = f"{os.urandom(4).hex()}"

    # Create an instance of DomainadminModel
    model = DomainadminModel(
        username=random_username,
        password=random_password,
        domains="mailcow.local",
    )

    # Test the parser_command attribute
    assert model.parser_command == "domainadmin", "Parser command should be 'domainadmin'"

    # add Domain for testing
    domain_model = DomainModel(domain="mailcow.local")
    domain_model.add()

    # 1. Domainadmin add tests, should success
    r_add = model.add()
    assert isinstance(r_add, list), f"Expected a array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add) > 0, f"Wrong array received: {json.dumps(r_add, indent=2)}"
    assert "type" in r_add[0], f"'type' key missing in response: {json.dumps(r_add, indent=2)}"
    assert r_add[0]['type'] == "success", f"Wrong 'type' received: {r_add[0]['type']}\n{json.dumps(r_add, indent=2)}"
    assert "msg" in r_add[0], f"'msg' key missing in response: {json.dumps(r_add, indent=2)}"
    assert isinstance(r_add[0]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add[0]['msg']) > 0 and len(r_add[0]['msg']) <= 3, f"Wrong 'msg' array received: {json.dumps(r_add, indent=2)}"
    assert r_add[0]['msg'][0] == "domain_admin_added", f"Wrong 'msg' received: {r_add[0]['msg'][0]}, expected: 'domain_admin_added'\n{json.dumps(r_add, indent=2)}"

    # 2. Domainadmin add tests, should fail because the domainadmin already exists
    r_add = model.add()
    assert isinstance(r_add, list), f"Expected a array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add) > 0, f"Wrong array received: {json.dumps(r_add, indent=2)}"
    assert "type" in r_add[0], f"'type' key missing in response: {json.dumps(r_add, indent=2)}"
    assert r_add[0]['type'] == "danger", f"Wrong 'type' received: {r_add[0]['type']}\n{json.dumps(r_add, indent=2)}"
    assert "msg" in r_add[0], f"'msg' key missing in response: {json.dumps(r_add, indent=2)}"
    assert isinstance(r_add[0]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_add, indent=2)}"
    assert len(r_add[0]['msg']) > 0 and len(r_add[0]['msg']) <= 2, f"Wrong 'msg' array received: {json.dumps(r_add, indent=2)}"
    assert r_add[0]['msg'][0] == "object_exists", f"Wrong 'msg' received: {r_add[0]['msg'][0]}, expected: 'object_exists'\n{json.dumps(r_add, indent=2)}"

    # 3. Domainadmin get tests
    r_get = model.get()
    assert isinstance(r_get, dict), f"Expected a dict but received: {json.dumps(r_get, indent=2)}"
    assert "selected_domains" in r_get, f"'selected_domains' key missing in response: {json.dumps(r_get, indent=2)}"
    assert "username" in r_get, f"'username' key missing in response: {json.dumps(r_get, indent=2)}"
    assert set(model.domains.replace(" ", "").split(",")) == set(r_get['selected_domains']), f"Wrong 'selected_domains' received: {r_get['selected_domains']}, expected: {model.domains}\n{json.dumps(r_get, indent=2)}"
    assert r_get['username'] == model.username, f"Wrong 'username' received: {r_get['username']}, expected: {model.username}\n{json.dumps(r_get, indent=2)}"

    # 4. Domainadmin edit tests
    model.active = 0
    r_edit = model.edit()
    assert isinstance(r_edit, list), f"Expected a array but received: {json.dumps(r_edit, indent=2)}"
    assert len(r_edit) > 0, f"Wrong array received: {json.dumps(r_edit, indent=2)}"
    assert "type" in r_edit[0], f"'type' key missing in response: {json.dumps(r_edit, indent=2)}"
    assert r_edit[0]['type'] == "success", f"Wrong 'type' received: {r_edit[0]['type']}\n{json.dumps(r_edit, indent=2)}"
    assert "msg" in r_edit[0], f"'msg' key missing in response: {json.dumps(r_edit, indent=2)}"
    assert isinstance(r_edit[0]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_edit, indent=2)}"
    assert len(r_edit[0]['msg']) > 0 and len(r_edit[0]['msg']) <= 2, f"Wrong 'msg' array received: {json.dumps(r_edit, indent=2)}"
    assert r_edit[0]['msg'][0] == "domain_admin_modified", f"Wrong 'msg' received: {r_edit[0]['msg'][0]}, expected: 'domain_admin_modified'\n{json.dumps(r_edit, indent=2)}"

    # 5. Domainadmin delete tests
    r_delete = model.delete()
    assert isinstance(r_delete, list), f"Expected a array but received: {json.dumps(r_delete, indent=2)}"
    assert len(r_delete) > 0, f"Wrong array received: {json.dumps(r_delete, indent=2)}"
    assert "type" in r_delete[0], f"'type' key missing in response: {json.dumps(r_delete, indent=2)}"
    assert r_delete[0]['type'] == "success", f"Wrong 'type' received: {r_delete[0]['type']}\n{json.dumps(r_delete, indent=2)}"
    assert "msg" in r_delete[0], f"'msg' key missing in response: {json.dumps(r_delete, indent=2)}"
    assert isinstance(r_delete[0]['msg'], list), f"Expected a 'msg' array but received: {json.dumps(r_delete, indent=2)}"
    assert len(r_delete[0]['msg']) > 0 and len(r_delete[0]['msg']) <= 2, f"Wrong 'msg' array received: {json.dumps(r_delete, indent=2)}"
    assert r_delete[0]['msg'][0] == "domain_admin_removed", f"Wrong 'msg' received: {r_delete[0]['msg'][0]}, expected: 'domain_admin_removed'\n{json.dumps(r_delete, indent=2)}"

    # delete testing Domain
    domain_model.delete()

if __name__ == "__main__":
  print("Running DomainadminModel tests...")
  test_model()
  print("All tests passed!")
