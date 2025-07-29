import pytest
from models.BaseModel import BaseModel


class Args:
    def __init__(self, **kwargs):
        for key, value in kwargs.items():
            setattr(self, key, value)


def test_has_required_args():
    BaseModel.required_args = {
        "test_object": [["arg1"], ["arg2", "arg3"]],
    }

    # Test cases with Args object
    args = Args(object="non_existent_object")
    assert BaseModel.has_required_args(args) == False

    args = Args(object="test_object")
    assert BaseModel.has_required_args(args) == False

    args = Args(object="test_object", arg1="value")
    assert BaseModel.has_required_args(args) == True

    args = Args(object="test_object", arg2="value")
    assert BaseModel.has_required_args(args) == False

    args = Args(object="test_object", arg3="value")
    assert BaseModel.has_required_args(args) == False

    args = Args(object="test_object", arg2="value", arg3="value")
    assert BaseModel.has_required_args(args) == True

    # Test cases with dict object
    args = {"object": "non_existent_object"}
    assert BaseModel.has_required_args(args) == False

    args = {"object": "test_object"}
    assert BaseModel.has_required_args(args) == False

    args = {"object": "test_object", "arg1": "value"}
    assert BaseModel.has_required_args(args) == True

    args = {"object": "test_object", "arg2": "value"}
    assert BaseModel.has_required_args(args) == False

    args = {"object": "test_object", "arg3": "value"}
    assert BaseModel.has_required_args(args) == False

    args = {"object": "test_object", "arg2": "value", "arg3": "value"}
    assert BaseModel.has_required_args(args) == True


    BaseModel.required_args = {
        "test_object": [[]],
    }

    # Test cases with Args object
    args = Args(object="non_existent_object")
    assert BaseModel.has_required_args(args) == False

    args = Args(object="test_object")
    assert BaseModel.has_required_args(args) == True

    # Test cases with dict object
    args = {"object": "non_existent_object"}
    assert BaseModel.has_required_args(args) == False

    args = {"object": "test_object"}
    assert BaseModel.has_required_args(args) == True
