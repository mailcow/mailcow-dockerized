<?php

$config['product_name'] = 'Uptempo Mail';

// Both Docker services present the certificate issued for Mailcow's hostname.
$config['imap_conn_options'] = [
    'ssl' => [
        'cafile' => '/etc/mailcow-webmail.crt',
        'capath' => '/etc/ssl/certs',
        'peer_name' => 'mail.cloudfan.shop',
        'verify_peer' => true,
        'verify_peer_name' => true,
    ],
];
$config['smtp_conn_options'] = $config['imap_conn_options'];
