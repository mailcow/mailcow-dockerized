import json
import random
import string


class Utils:
    def __init(self):
        pass

    def normalize_email(self, email):
        replacements = {
            "ä": "ae", "ö": "oe", "ü": "ue", "ß": "ss",
            "Ä": "Ae", "Ö": "Oe", "Ü": "Ue"
        }
        for orig, repl in replacements.items():
            email = email.replace(orig, repl)
        return email

    def generate_password(self, length=8):
        chars = string.ascii_letters + string.digits
        return ''.join(random.choices(chars, k=length))

    def pprint(self, data=""):
        """
        Pretty print a dictionary, list, or text.
        If data is a text containing JSON, it will be printed in a formatted way.
        """
        if isinstance(data, (dict, list)):
            print(json.dumps(data, indent=2, ensure_ascii=False))
        elif isinstance(data, str):
            try:
                json_data = json.loads(data)
                print(json.dumps(json_data, indent=2, ensure_ascii=False))
            except json.JSONDecodeError:
                print(data)
        else:
            print(data)
