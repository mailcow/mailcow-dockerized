#!/bin/bash

MAX_AGE=$(redis-cli --raw -h redis-mailcow GET Q_MAX_AGE)

if [[ -z ${MAX_AGE} ]]; then
  echo "Max age for quarantine items not defined"
  exit 1
fi

NUM_REGEXP='^[0-9]+$'
if ! [[ ${MAX_AGE} =~ ${NUM_REGEXP} ]] ; then
  echo "Max age for quarantine items invalid"
  exit 1
fi

TO_DELETE=$(mysql --socket=/var/run/mysqld/mysqld.sock -u __DBUSER__ -p__DBPASS__ __DBNAME__ -e "SELECT COUNT(id) FROM quarantine WHERE created < NOW() - INTERVAL ${MAX_AGE//[!0-9]/} DAY" -BN)
mysql --socket=/var/run/mysqld/mysqld.sock -u __DBUSER__ -p__DBPASS__ __DBNAME__ -e "DELETE FROM quarantine WHERE created < NOW() - INTERVAL ${MAX_AGE//[!0-9]/} DAY"
echo "Deleted ${TO_DELETE} items from quarantine table (max age is ${MAX_AGE//[!0-9]/} days)"
