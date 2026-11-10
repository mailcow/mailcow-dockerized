#!/usr/bin/env bash
# _modules/scripts/backup.sh
# THIS SCRIPT IS DESIGNED TO BE RUNNING BY MAILCOW SCRIPTS ONLY!
# DO NOT, AGAIN, NOT TRY TO RUN THIS SCRIPT STANDALONE!!!!!!

# Backup with restic before the update when it is set up (target, confirmed password, initialized repository):
# interactive: asked for; --backup: without asking; --force: none (with --backup: without asking, a failure or a backup
# that cannot run stops the update; only "not set up" continues)
backup_failed() {
  echo -e "\e[31m${1}\e[0m"
  if [ "$FORCE" ]; then
    echo "The update was started with --force --backup, it stops here. Nothing was changed."
    exit 1
  fi
  read -r -p "Continue the update without a backup? [y/N] " backup_response
  if [[ ! "${backup_response}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    echo "OK, exiting."
    exit 1
  fi
}

# Whether to back up (asked with the other questions); the backup itself runs before the first change of the checkout
backup_decide() {
  local output rc
  # exported: update.sh restarts itself after a branch switch, the decision and a backup already made stay
  if [ "$MAILCOW_UPDATE_BACKUP_DECIDED" ]; then
    return 0
  fi
  export MAILCOW_UPDATE_BACKUP_DECIDED=y
  export RUN_BACKUP=
  if [ "$FORCE" ] && [ ! "$BACKUP" ]; then
    return 0
  fi
  if [[ -z "$($COMPOSE_COMMAND ps -q restic-mailcow 2> /dev/null)" ]]; then
    output="the backup container does not run"
    rc=1
  else
    # exit code 0: set up, 2: not set up, others: error (e.g. credentials this container cannot read)
    output=$($COMPOSE_COMMAND exec -T restic-mailcow python3 /usr/local/bin/restic_runner.py --configured 2>&1 < /dev/null)
    rc=$?
  fi
  if [[ ${rc} -eq 2 ]]; then
    [ "$BACKUP" ] && echo -e "\e[33mNo backup before the update: ${output}.\e[0m"
    return 0
  elif [[ ${rc} -ne 0 ]]; then
    if [ "$BACKUP" ]; then
      backup_failed "No backup before the update possible: ${output}"
    elif [[ -n "$($COMPOSE_COMMAND ps -q restic-mailcow 2> /dev/null)" ]]; then
      echo -e "\e[33mNo backup before the update: ${output}\e[0m"
    fi
    return 0
  fi
  if [ ! "$BACKUP" ]; then
    read -r -p "Create a backup before the update? [Y/n] " backup_response
    if [[ "${backup_response}" =~ ^([nN][oO]|[nN])$ ]]; then
      echo "OK, no backup."
      return 0
    fi
  fi
  RUN_BACKUP=y
}

# The backup chosen by backup_decide, once
backup_run() {
  if [ ! "$RUN_BACKUP" ]; then
    return 0
  fi
  RUN_BACKUP=
  if [[ "${RESTIC_BACKUP_MODE}" =~ ^(data|config)$ ]]; then
    echo -e "\e[33mRESTIC_BACKUP_MODE=${RESTIC_BACKUP_MODE}: the backup holds no complete mailcow directory, a restore does not bring back this mailcow version.\e[0m"
  fi
  echo -e "\e[32mCreating a backup before the update...\e[0m"
  if ! $COMPOSE_COMMAND exec -T restic-mailcow python3 /usr/local/bin/restic_runner.py --backup < /dev/null; then
    backup_failed "The backup before the update failed, see the output above and the backup jobs in the mailcow UI."
  fi
}
