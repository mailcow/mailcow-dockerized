<?php
error_reporting(0);
if (!file_exists('/tmp/mime.types')) {
file_put_contents("/tmp/mime.types", fopen("http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types", 'r'));
}
$config = array();
$config['db_dsnw'] = 'mysql://' . getenv('DBUSER') . ':' . getenv('DBPASS') . '@mysql/' . getenv('DBNAME');
$config['default_host'] = 'tls://dovecot';
$config['default_port'] = '143';
$config['smtp_server'] = 'tls://postfix';
$config['smtp_port'] = 587;
$config['smtp_user'] = '%u';
$config['smtp_pass'] = '%p';
$config['support_url'] = '';
$config['product_name'] = 'Roundcube Webmail';
$config['des_key'] = 'rcmail-!24ByteDESkey*Str';
$config['log_dir'] = '/dev/null';
$config['temp_dir'] = '/tmp';
$config['plugins'] = array(
    'archive',
    'password',
);
$config['skin'] = 'larry';
$config['mime_types'] = '/tmp/mime.types';
$config['imap_conn_options'] = array(
        'ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true)
);
$config['smtp_conn_options'] = array(
        'ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true)
);
$config['enable_installer'] = false;
