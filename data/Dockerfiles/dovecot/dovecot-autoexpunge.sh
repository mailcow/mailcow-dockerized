#!/bin/bash
set -e

# SQL connection data
HOST=mysql
USER=mailcow
PWD="iZMukr8FyWrvFAIcU3IrMXhZLo29"
DBNAME=mailcow
TBLNAME="expires"
# expunge messages older than this (days)
TTL=30

expiredsql=`cat <<EOF
  USE $DBNAME;
  SELECT CONCAT(username, "~", mailbox)
  FROM $TBLNAME
  WHERE expire_stamp>0 AND expire_stamp<UNIX_TIMESTAMP()-86400*$TTL;
EOF`

for row in `echo "$expiredsql" | mysql -h $HOST -u $USER -p$PWD -N`; do
  username=`echo $row | cut -d"~" -f1`
  mailbox=`echo $row | cut -d"~" -f2-`

  # Check if mailbox name is empty. Just in case.
  if [ -n $mailbox ]; then
        #echo "Expunging: "$row
        doveadm expunge -u $username mailbox "$mailbox" savedbefore $TTL"d"
  fi
done
