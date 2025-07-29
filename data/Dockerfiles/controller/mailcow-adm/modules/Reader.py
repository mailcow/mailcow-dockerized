from jinja2 import Environment, Template
import csv

def split_at(value, sep, idx):
    try:
        return value.split(sep)[idx]
    except Exception:
        return ''

class Reader:
    """
    Reader class to handle reading and processing of CSV and JSON files for mailcow.
    """

    def __init__(self):
        pass

    def read_csv(self, file_path, delimiter=',', encoding='iso-8859-1'):
        """
        Read a CSV file and return a list of dictionaries.
        Each dictionary represents a row in the CSV file.
        :param file_path: Path to the CSV file.
        :param delimiter: Delimiter used in the CSV file (default: ',').
        """
        with open(file_path, mode='r', encoding=encoding) as file:
            reader = csv.DictReader(file, delimiter=delimiter)
            reader.fieldnames = [h.replace(" ", "_") if h else h for h in reader.fieldnames]
            return [row for row in reader]

    def map_csv_data(self, data, mapping_file_path, encoding='iso-8859-1'):
        """
        Map CSV data to a specific structure based on the provided Jinja2 template file.
        :param data: List of dictionaries representing CSV rows.
        :param mapping_file_path: Path to the Jinja2 template file.
        :return: List of dictionaries with mapped data.
        """
        with open(mapping_file_path, 'r', encoding=encoding) as tpl_file:
            template_content = tpl_file.read()
        env = Environment()
        env.filters['split_at'] = split_at
        template = env.from_string(template_content)

        mapped_data = []
        for row in data:
            rendered = template.render(**row)
            try:
                mapped_row = eval(rendered)
            except Exception:
                mapped_row = rendered
            mapped_data.append(mapped_row)
        return mapped_data