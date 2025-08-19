import pytest
import json
import sys
import os

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../")))
from models.StatusModel import StatusModel


def test_model():
    # Create an instance of StatusModel
    model = StatusModel()

    # Test the parser_command attribute
    assert model.parser_command == "status", "Parser command should be 'status'"

    # 1. Status version tests
    r_version = model.version()
    assert isinstance(r_version, dict), f"Expected a dict but received: {json.dumps(r_version, indent=2)}"
    assert "version" in r_version, f"'version' key missing in response: {json.dumps(r_version, indent=2)}"

    # 2. Status vmail tests
    r_vmail = model.vmail()
    assert isinstance(r_vmail, dict), f"Expected a dict but received: {json.dumps(r_vmail, indent=2)}"
    assert "type" in r_vmail, f"'type' key missing in response: {json.dumps(r_vmail, indent=2)}"
    assert "disk" in r_vmail, f"'disk' key missing in response: {json.dumps(r_vmail, indent=2)}"
    assert "used" in r_vmail, f"'used' key missing in response: {json.dumps(r_vmail, indent=2)}"
    assert "total" in r_vmail, f"'total' key missing in response: {json.dumps(r_vmail, indent=2)}"
    assert "used_percent" in r_vmail, f"'used_percent' key missing in response: {json.dumps(r_vmail, indent=2)}"

    # 3. Status containers tests
    r_containers = model.containers()
    assert isinstance(r_containers, dict), f"Expected a dict but received: {json.dumps(r_containers, indent=2)}"


if __name__ == "__main__":
  print("Running StatusModel tests...")
  test_model()
  print("All tests passed!")
