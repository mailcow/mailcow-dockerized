#!/usr/bin/php -q
<?php

# SQL connection data
$HOST=mysql;
$USER=getenv('DBUSER');
$PWD=getenv('DBPASS');
$DBNAME=getenv('DBNAME');
$TBLNAME="expires";
# expunge messages older than this (days)
$TTL=30;

$expiredsql=<<<EOF
  USE $DBNAME;
  SELECT CONCAT(username, '~', mailbox)
  FROM $TBLNAME
  WHERE expire_stamp>0 AND expire_stamp<UNIX_TIMESTAMP()-86400*$TTL;
EOF;

foreach(array_filter(explode(PHP_EOL, shell_exec("echo \"$expiredsql\" | mysql -h $HOST -P $PORT -u $USER -p$PWD -N"))) as $row) {
  $username=explode('~',$row)[0];
  $mailbox=explode('~',$row)[1];
  $domain=explode('@',$username)[1];
  
  $domainaesql=<<<EOF
    USE $DBNAME;
    SELECT auto_expunge
    FROM domain
    WHERE domain='$domain'
    LIMIT 1;
EOF;
  
  $domainAE=intval(shell_exec("echo \"$domainaesql\" | mysql -h $HOST -P $PORT -u $USER -p$PWD -N"));

  # Check if mailbox name is empty. Just in case.
  if(!empty($mailbox) && $domainAE===1) {
    echo "Expunging: $row\n";
    #shell_exec("doveadm expunge -u $username mailbox $mailbox savedbefore $TTL".'d');
  }
}
