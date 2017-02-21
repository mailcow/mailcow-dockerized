<?php

/**
 * Show additional message headers
 *
 * Proof-of-concept plugin which will fetch additional headers
 * and display them in the message view.
 *
 * Enable the plugin in config.inc.php and add your desired headers:
 *   $config['show_additional_headers'] = array('User-Agent');
 *
 * @author Thomas Bruederli
 * @license GNU GPLv3+
 */
class show_additional_headers extends rcube_plugin
{
  public $task = 'mail';

  function init()
  {
    $rcmail = rcmail::get_instance();
    if ($rcmail->action == 'show' || $rcmail->action == 'preview') {
      $this->add_hook('storage_init', array($this, 'storage_init'));
      $this->add_hook('message_headers_output', array($this, 'message_headers'));
    } else if ($rcmail->action == '') {
      // with enabled_caching we're fetching additional headers before show/preview
      $this->add_hook('storage_init', array($this, 'storage_init'));
    }
  }

  function storage_init($p)
  {
    $rcmail = rcmail::get_instance();
    if ($add_headers = (array)$rcmail->config->get('show_additional_headers', array()))
      $p['fetch_headers'] = trim($p['fetch_headers'].' ' . strtoupper(join(' ', $add_headers)));

    return $p;
  }

  function message_headers($p)
  {
    $rcmail = rcmail::get_instance();
    foreach ((array)$rcmail->config->get('show_additional_headers', array()) as $header) {
      if ($value = $p['headers']->get($header))
        $p['output'][$header] = array('title' => $header, 'value' => $value);
    }

    return $p;
  }
}
