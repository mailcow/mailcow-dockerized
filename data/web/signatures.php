<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

protect_route(['admin', 'domainadmin']);

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];

$js_minifier->add('/web/js/site/edit.js');

$templates = (array)signature_template('get');
$rules     = (array)signature_rule('get');
$domains   = (array)mailbox('get', 'domains');

// Group templates by domain, attach rules to their template, count orphans defensively.
$by_domain = [];
foreach ($domains as $d) {
  $by_domain[$d] = ['templates' => [], 'orphan_rules' => []];
}
foreach ($templates as $t) {
  $dom = $t['domain'];
  if (!isset($by_domain[$dom])) {
    $by_domain[$dom] = ['templates' => [], 'orphan_rules' => []];
  }
  $t['rules'] = [];
  $by_domain[$dom]['templates'][$t['id']] = $t;
}
foreach ($rules as $r) {
  $dom = $r['domain'];
  if (!isset($by_domain[$dom])) {
    $by_domain[$dom] = ['templates' => [], 'orphan_rules' => []];
  }
  if (isset($by_domain[$dom]['templates'][$r['template_id']])) {
    $by_domain[$dom]['templates'][$r['template_id']]['rules'][] = $r;
  } else {
    $by_domain[$dom]['orphan_rules'][] = $r;
  }
}
ksort($by_domain);

$total_templates = count($templates);
$total_rules     = count($rules);

$template = 'signatures.twig';
$template_data = [
  'by_domain'       => $by_domain,
  'all_templates'   => $templates,
  'domains'         => $domains,
  'total_templates' => $total_templates,
  'total_rules'     => $total_rules,
  'lang_admin'      => json_encode($lang['admin']),
  'lang_datatables' => json_encode($lang['datatables']),
];

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
