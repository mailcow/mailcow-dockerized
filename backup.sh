!/bin/bash

DATE=$(date +"%Y%m%d_%H%M%S")
BACKUP_DIR=/backups
MAILCOW_DIR=/root/mailcow-dockerized
DAYS_TO_KEEP="14"

LOG="logger -t $PROGNAME"

# log to stdout if on a tty
tty -s && LOG="cat -"

function initChecks {
  if [ ! -d "$BACKUP_DIR" ]; then
    mkdir -m700  -p "$BACKUP_DIR"
    if [ $? -ne 0 ]; then
          echo "BACKUP_DIR doesn't exist and couldn't create it, aborting ($BACKUP_DIR)" | $LOG
          exit 1
    fi
  fi

  if [ ! -w "$BACKUP_DIR" ]; then
    echo "$BACKUP_DIR not writable. Aborting" | $LOG
    exit 1
  fi
}

function removeOldBackups {

  if [ ! -z $DRYRUN ]; then
    RM="echo \"not deleted\""
  else
    RM="rm -rf"
  fi

  echo "Deleting old backups..." | $LOG
  find ${BACKUP_DIR}/ -maxdepth 1 -type d -iname "mailcow-*" -mtime "+$DAYS_TO_KEEP" -ls -exec $RM {} \; 2>&1 | $LOG
  echo "Done deleting old backups." | $LOG
}


function dumpit {
  mkdir -m700  "$BACKUP_DIR/mailcow-${DATE}" 2>&1  | $LOG
  if [ $? -ne 0 ]; then
    exit 1
  fi
  cd $MAILCOW_DIR
  source mailcow.conf
  docker-compose exec -T mysql-mailcow mysqldump --default-character-set=utf8mb4 -u${DBUSER} -p${DBPASS} ${DBNAME} > backup_mysql.sql | $LOG
  docker run --rm -i -v $(docker inspect --format '{{ range .Mounts }}{{ if eq .Destination "/var/vmail" }}{{ .Name }}{{ end }}{{ end }}' $(docker-compose ps -q dovecot-mailcow)):/vmail -v ${PWD}:/backup debian:stretch-slim tar cvfz /backup/backup_vmail.tar.gz /vmail | $LOG
  tar -czvf "mailcow-${DATE}.tar.gz" backup_mysql.sql backup_vmail.tar.gz | $LOG
  rm backup_mysql.sql backup_vmail.tar.gz
  mv "mailcow-${DATE}.tar.gz" "$BACKUP_DIR/mailcow-${DATE}"
  RC=$?
  if [ $RC -ne 0 ]; then
    echo -e "FAILED, error while dumping mailcow data" | $LOG
    exit $RC
  else
    echo -e "OK: dumped mailcow data" | $LOG
  fi
}

echo "$PROGNAME starting" | $LOG
initChecks
dumpit
removeOldBackups
echo "$PROGNAME exiting" | $LOG
