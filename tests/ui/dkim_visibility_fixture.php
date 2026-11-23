<?php
// Test-only fixture: no application bootstrap, database, Redis, or real keys.
// P10 exception: fixture helpers use explicit failures instead of assertion quotas.
declare(strict_types=1);

$web = dirname(__DIR__, 2) . '/data/web';
require $web . '/inc/lib/vendor/autoload.php';
$read = static function (string $path): string {
  $value = file_get_contents($path);
  if ($value === false) {
    throw new RuntimeException("Cannot read fixture dependency: $path");
  }
  return $value;
};
$twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader($web . '/templates'), [
  'strict_variables' => true,
  'autoescape' => 'html',
]);
$key = [
  'dkim_selector' => 'fixture',
  'length' => 2048,
  'dkim_txt' => 'v=DKIM1; k=rsa; p=FIXTURE-NOT-A-REAL-PUBLIC-KEY',
  'privkey' => 'FIXTURE-NOT-A-REAL-PRIVATE-KEY',
];
$context = [
  'lang' => json_decode($read($web . '/lang/lang.en-gb.json'), true, 512, JSON_THROW_ON_ERROR),
  'dkim_domains' => [
    'valid.example' => [
      'dkim' => $key,
      'alias_domains' => [
        'alias-valid.example' => ['dkim' => $key],
        'alias-missing.example' => ['dkim' => null],
      ],
    ],
    'missing.example' => ['dkim' => null, 'alias_domains' => []],
  ],
  'dkim_blind_domains' => [
    'unused.example' => ['dkim' => $key],
    'no-key.example' => ['dkim' => null],
  ],
  'dkim_domains_with_keys' => ['valid.example', 'alias-valid.example', 'unused.example'],
  'all_domains' => ['valid.example', 'alias-valid.example', 'alias-missing.example', 'missing.example'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>DKIM row visibility regression</title>
  <style><?= $read($web . '/css/themes/lumen-bootstrap.css') ?></style>
</head>
<body>
  <main id="admin-content" class="tab-content">
    <?= $twig->render('admin/tab-config-dkim.twig', $context) ?>
  </main>
  <pre id="dkim-test-results">PENDING</pre>
  <script><?= $read($web . '/js/build/001-bootstrap.bundle.min.js') ?></script>
  <script>
    'use strict';
    const outcomes = [];
    const expectedRows = {
      'valid.example': 'valid',
      'alias-valid.example': 'valid',
      'missing.example': 'missing',
      'alias-missing.example': 'missing',
      'unused.example': 'unused',
    };
    const categories = ['valid', 'missing', 'unused'];
    const pause = () => new Promise(resolve => setTimeout(resolve, 450));
    const check = (name, actual, expected) => {
      outcomes.push({name, passed: actual === expected, actual, expected});
    };
    const row = name => document.querySelector('input[name="multi_select"][value="' + name + '"]')
      ?.closest('.row');
    const visible = element => Boolean(element && element.getBoundingClientRect().height > 0
      && getComputedStyle(element).display !== 'none');
    const filters = () => Object.fromEntries(categories.map(category => [
      category, document.getElementById('check-dkim_key_' + category),
    ]));

    function checkRows(stage, enabled) {
      for (const [name, category] of Object.entries(expectedRows)) {
        check(stage + ': ' + name, visible(row(name)), enabled.includes(category));
      }
      for (const category of categories) {
        check(stage + ': checkbox ' + category, filters()[category].checked,
          enabled.includes(category));
      }
    }

    async function clickFilter(category) {
      const label = document.querySelector('label[for="check-dkim_key_' + category + '"]');
      if (!label) throw new Error('Missing filter label: ' + category);
      label.click(); // Exercise Bootstrap's real data API and the label/checkbox default action.
      await pause();
    }

    async function run() {
      document.getElementById('tab-config-dkim').classList.add('active', 'show');
      // Open only the enclosing panel, as entering the admin tab does; never repair row classes.
      new bootstrap.Collapse(document.getElementById('collapse-tab-config-dkim'),
        {toggle: false}).show();
      await pause();
      check('rendered row count', document.querySelectorAll('input[name="multi_select"]').length, 5);
      check('blind entry without key omitted', row('no-key.example') === undefined, true);
      checkRows('initial', categories);
      const originalText = Object.fromEntries(Object.keys(expectedRows).map(name => [name, row(name).textContent]));
      for (const category of categories) {
        await clickFilter(category);
        checkRows(category + ' off', categories.filter(value => value !== category));
        await clickFilter(category);
        checkRows(category + ' restored', categories);
      }
      for (const category of categories) await clickFilter(category);
      checkRows('all off', []);
      await clickFilter('missing');
      checkRows('only missing', ['missing']);
      await clickFilter('valid');
      await clickFilter('unused');
      checkRows('all restored', categories);
      for (const name of Object.keys(expectedRows)) {
        check('content preserved: ' + name, row(name).textContent, originalText[name]);
        check('selection availability: ' + name,
          row(name).querySelector('input[name="multi_select"]').disabled,
          expectedRows[name] === 'missing');
      }
    }

    run().catch(error => outcomes.push({name: 'harness', passed: false, error: String(error)}))
      .finally(() => {
        document.getElementById('dkim-test-results').textContent = JSON.stringify({
          passed: outcomes.length > 0 && outcomes.every(outcome => outcome.passed),
          checks: outcomes.length,
          failures: outcomes.filter(outcome => !outcome.passed),
        });
      });
  </script>
</body>
</html>
