from jinja2 import Environment, FileSystemLoader
from modules.BootstrapBase import BootstrapBase
from pathlib import Path
import os
import sys
import time
import platform

class Bootstrap(BootstrapBase):
  def bootstrap(self):
    # Skip Clamd if set
    if self.isYes(os.getenv("SKIP_CLAMD", "")):
      print("SKIP_CLAMD is set, skipping ClamAV startup...")
      time.sleep(365 * 24 * 60 * 60)
      sys.exit(1)

    # Connect to MySQL
    self.connect_mysql()

    print("Cleaning up tmp files...")
    tmp_files = Path("/var/lib/clamav").glob("clamav-*.tmp")
    for tmp_file in tmp_files:
      try:
        self.remove(tmp_file)
        print(f"Removed: {tmp_file}")
      except Exception as e:
        print(f"Failed to remove {tmp_file}: {e}")

    self.create_dir("/run/clamav")
    self.create_dir("/var/lib/clamav")

    # Setup Jinja2 Environment and load vars
    self.env = Environment(
      loader=FileSystemLoader([
        '/etc/clamav/custom_templates',
        '/etc/clamav/config_templates'
      ]),
      keep_trailing_newline=True,
      lstrip_blocks=True,
      trim_blocks=True
    )
    extra_vars = {
    }
    self.env_vars = self.prepare_template_vars('/overwrites.json', extra_vars)

    print("Set Timezone")
    self.set_timezone()

    print("Render config")
    self.render_config("/etc/clamav/config.json")

    # Fix permissions
    self.set_owner("/var/lib/clamav", "clamav", "clamav", recursive=True)
    self.set_owner("/run/clamav", "clamav", "clamav", recursive=True)
    self.set_permissions("/var/lib/clamav", 0o755)
    for item in Path("/var/lib/clamav").glob("*"):
      self.set_permissions(item, 0o644)
    self.set_permissions("/run/clamav", 0o750)

    # Copying to /etc/clamav to expose file as-is to administrator
    self.copy_file("/var/lib/clamav/whitelist.ign2", "/etc/clamav/whitelist.ign2")
