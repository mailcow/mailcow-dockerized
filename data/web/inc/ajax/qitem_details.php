<?php
session_start();
header("Content-Type: application/json");
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
if (!isset($_SESSION['mailcow_cc_role'])) {
	exit();
}
function rrmdir($src) {
  $dir = opendir($src);
  while(false !== ( $file = readdir($dir)) ) {
    if (( $file != '.' ) && ( $file != '..' )) {
      $full = $src . '/' . $file;
      if ( is_dir($full) ) {
        rrmdir($full);
      }
      else {
        unlink($full);
      }
    }
  }
  closedir($dir);
  rmdir($src);
}
if (!empty($_GET['id']) && ctype_alnum($_GET['id'])) {
  $tmpdir = '/tmp/' . $_GET['id'] . '/';
  $mailc = quarantine('details', $_GET['id']);
  if (strlen($mailc['msg']) > 10485760) {
    echo json_encode(array('error' => 'Message size exceeds 10 MiB.'));
    exit;
  }
  if (!empty($mailc['msg'])) {
    // Init message array
    $data = array();
    // Init parser
    $mail_parser = new PhpMimeMailParser\Parser();
    $html2text = new Html2Text\Html2Text();
    // Load msg to parser
    $mail_parser->setText($mailc['msg']);
    // Get text/plain content
    $data['text_plain'] = $mail_parser->getMessageBody('text');
    // Get html content and convert to text
    $data['text_html'] = $html2text->convert($mail_parser->getMessageBody('html'));
    (empty($data['text_plain'])) ? $data['text_plain'] = '-' : null;
    // Get subject
    $data['subject'] = $mail_parser->getHeader('subject');
    (empty($data['subject'])) ? $data['subject'] = '-' : null;
    // Get attachments
    if (is_dir($tmpdir)) {
      rrmdir($tmpdir);
    }
    mkdir('/tmp/' . $_GET['id']);
    $mail_parser->saveAttachments($tmpdir, true);
    $atts = $mail_parser->getAttachments(true);
    if (count($atts) > 0) {
      foreach ($atts as $key => $val) {
        $data['attachments'][$key] = array(
          // Index
          // 0 => file name
          // 1 => mime type
          // 2 => file size
          // 3 => vt link by sha256
          $val->getFilename(),
          $val->getContentType(),
          filesize($tmpdir . $val->getFilename()),
          'https://www.virustotal.com/file/' . hash_file('SHA256', $tmpdir . $val->getFilename()) . '/analysis/'
        );
      }
    }
    if (isset($_GET['att'])) {
      $dl_id = intval($_GET['att']);
      $dl_filename = $data['attachments'][$dl_id][0];
      if (!is_dir($tmpdir . $dl_filename) && file_exists($tmpdir . $dl_filename)) {
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Cache-Control: private', false);
        header('Content-Type: ' . $data['attachments'][$dl_id][1]);
        header('Content-Disposition: attachment; filename="'. $dl_filename . '";');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . $data['attachments'][$dl_id][2]);
        readfile($tmpdir . $dl_filename);
        exit;
      }
    }
    echo json_encode($data);
  }
}
?>
