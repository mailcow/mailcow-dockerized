# DKIM visibility regression

From the repository root, with PHP 8.2+ and Chromium installed:

```sh
python3 tests/ui/test_dkim_visibility.py
```

Set `CHROMIUM` to a different Chromium executable if needed.

The test renders the real DKIM Twig template using the repository's bundled
Twig, Bootstrap JavaScript, and Lumen CSS. All domain and key data are synthetic;
it never loads the application bootstrap or contacts Mailcow, Redis, DNS, or mail
services. Each browser run uses a disposable profile and removes it afterwards.

Desktop and narrow viewports cover valid and missing primary domains, valid and
missing alias domains, and unused keys. The test checks initial computed
visibility, each filter off/on, combined filters, retained row contents, and
selection availability. An unused-domain entry with no key must remain omitted.

The unchanged template fails these behavioural checks: only valid primary-domain
rows begin visible, and the valid filter toggles primary and alias rows in
opposite directions. Replacing the four legacy `collapse in` classes with
`collapse show` must make the same tests pass.
