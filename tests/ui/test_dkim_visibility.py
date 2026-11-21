#!/usr/bin/env python3
"""Render the actual Twig template and exercise its Bootstrap filters in Chromium.

Requires PHP >= 8.2 and Chromium already installed. No npm packages, web server,
mailcow services, production data, or network fetches are used.
P10 exception: test-runner helpers use explicit exceptions rather than quotas.
"""
import html
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile
import unittest


class DkimVisibilityTest(unittest.TestCase):
  def test_initial_visibility_and_filters(self):
    chromium = os.environ.get("CHROMIUM") or shutil.which("chromium")
    self.assertTrue(chromium, "Install Chromium or set CHROMIUM to its executable")
    fixture = Path(__file__).with_name("dkim_visibility_fixture.php")
    render = subprocess.run(["php", str(fixture)], capture_output=True, text=True, timeout=30)
    self.assertEqual(render.returncode, 0, render.stderr)
    scratch = "/dev/shm" if Path("/dev/shm").is_dir() else None
    with tempfile.TemporaryDirectory(prefix="mailcow-dkim-test-", dir=scratch) as tmp:
      page = Path(tmp) / "fixture.html"
      page.write_text(render.stdout, encoding="utf-8")
      for width in (1280, 480):
        with self.subTest(viewport_width=width):
          result = self.run_browser(chromium, page, Path(tmp) / str(width), width)
          self.assertTrue(result["passed"], json.dumps(result, indent=2))
          self.assertGreaterEqual(result["checks"], 90)

  def run_browser(self, chromium, page, profile, width):
    command = [
      chromium, "--headless", "--disable-gpu", "--no-first-run",
      "--no-default-browser-check", "--disable-background-networking",
      "--disable-component-update", "--disable-sync",
      "--host-resolver-rules=MAP * ~NOTFOUND",
      "--user-data-dir=" + str(profile), "--window-size=" + str(width) + ",900",
      "--virtual-time-budget=12000", "--dump-dom", page.as_uri(),
    ]
    browser = subprocess.run(command, capture_output=True, text=True, timeout=45)
    self.assertEqual(browser.returncode, 0, browser.stderr[-3000:])
    match = re.search(r'<pre id="dkim-test-results">([^<]*)</pre>', browser.stdout)
    self.assertIsNotNone(match, "Browser did not return the DOM test receipt")
    text = html.unescape(match.group(1))
    self.assertNotEqual(text, "PENDING", "Browser test did not finish")
    result = json.loads(text)
    print(json.dumps({"viewportWidth": width, **result}), flush=True)
    return result


if __name__ == "__main__":
  unittest.main()
