#!/usr/bin env bash

MYBACKUPDIR=/sogo_backup
USE_SIEVE=$(grep 'SOGoSieveScriptsEnabled = YES;' /etc/sogo/sogo.conf|cut -d '=' -f 2)

cd $MYBACKUPDIR 
for i in `ls`
do
  # create account in SOGo and set general preferences
  sogo-tool restore -p "${MYBACKUPDIR}" "${i}"

  # create and fill all calendars and addressbooks
  sogo-tool restore -f ALL "${MYBACKUPDIR}" "${i}"

  if [ "${USE_SIEVE}" = ' YES;' ]
  then
    # restore all ACLs and SIEVE scripts
    sogo-tool restore -p -c /etc/sogo/sieve.creds "${MYBACKUPDIR}" "${i}"
  else
    # restore all ACLs
    sogo-tool restore -p "${MYBACKUPDIR}" "${i}"
  fi
done
