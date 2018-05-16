#!/usr/bin/php -q
<?php

# SQL connection data
$HOST='mysql';
$PORT='3306';
$USER=getenv('DBUSER');
$PWD=getenv('DBPASS');
$DBNAME=getenv('DBNAME');
$TBLNAME="expires";
# expunge messages older than this (days)
$TTL=30;

// Redis
$redis = new Redis();
$redis->connect('redis-mailcow', 6379);

$expiredsql=<<<EOF
  USE $DBNAME;
  SELECT CONCAT(username, '~', mailbox)
  FROM $TBLNAME
  WHERE expire_stamp>0 AND expire_stamp<UNIX_TIMESTAMP()-86400*$TTL;
EOF;

$rows = array_filter(explode(PHP_EOL, shell_exec("echo \"$expiredsql\" | mysql -h $HOST -P $PORT -u $USER -p$PWD -N")));

if(empty($rows)) {
  try {
    $json = json_encode(
      array(
        "time" => time(),
        "priority" => 'info',
        "message" => "No mail is due for expunction yet (Sleeping for 24hrs)"
      )
    );
    $redis->lPush('AUTOEXPUNGE_LOG', $json);
    $redis->lTrim('AUTOEXPUNGE_LOG', 0, 100);
    echo "No mail is due for expunction yet (Sleeping for 24hrs)\n";
  }
  catch (RedisException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'Redis: '.$e
    );
    return false;
  }
} else {
  foreach($rows as $row) {
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

    if(!empty($mailbox) && $domainAE===1) {
      try {
        $json = json_encode(
          array(
            "time" => time(),
            "priority" => 'warn',
            "message" => "Expunging: $row"
          )
        );
        $redis->lPush('AUTOEXPUNGE_LOG', $json);
        $redis->lTrim('AUTOEXPUNGE_LOG', 0, 100);
        echo "Expunging: $row\n";
        shell_exec("doveadm expunge -u $username mailbox $mailbox savedbefore $TTL".'d');
      }
      catch (RedisException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Redis: '.$e
        );
        return false;
      }
    } elseif($domainAE!==1) {
      try {
        $json = json_encode(
          array(
            "time" => time(),
            "priority" => 'info',
            "message" => "Skipping \"$row\" as per domain-wide policy"
          )
        );
        $redis->lPush('AUTOEXPUNGE_LOG', $json);
        $redis->lTrim('AUTOEXPUNGE_LOG', 0, 100);
        echo "Skipping \"$row\" as per domain-wide policy\n";
      }
      catch (RedisException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Redis: '.$e
        );
        return false;
      }
    }
  } 
}
