#!/usr/bin/env bash

############## Begin Function Section ##############

source _modules/scripts/core.sh
source _modules/scripts/ipv6_controller.sh

source _modules/scripts/new_options.sh
source _modules/scripts/migrate_options.sh

detect_major_update() {
  if [ ${BRANCH} == "master" ]; then
    # Array with major versions
    # Add major versions here
    MAJOR_VERSIONS=(
      "2025-02"
      "2025-03"
    )

    current_version=""
    if [[ -f "${SCRIPT_DIR}/data/web/inc/app_info.inc.php" ]]; then
      current_version=$(grep 'MAILCOW_GIT_VERSION' ${SCRIPT_DIR}/data/web/inc/app_info.inc.php | sed -E 's/.*MAILCOW_GIT_VERSION="([^"]+)".*/\1/')
    fi
    if [[ -z "$current_version" ]]; then
      return 1
    fi
    release_url="https://github.com/mailcow/mailcow-dockerized/releases/tag"

    updates_to_apply=()

    for version in "${MAJOR_VERSIONS[@]}"; do
      if [[ "$current_version" < "$version" ]]; then
        updates_to_apply+=("$version")
      fi
    done

    if [[ ${#updates_to_apply[@]} -gt 0 ]]; then
      echo -e "\e[33m\nMAJOR UPDATES to be applied:\e[0m"
      for update in "${updates_to_apply[@]}"; do
        echo "$update - $release_url/$update"
      done

      echo -e "\nPlease read the release notes before proceeding."
      read -p "Do you want to proceed with the update? [y/n] " response
      if [[ "${response}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
        echo "Proceeding with the update..."
      else
        echo "Update canceled. Exiting."
        exit 1
      fi
    fi
  fi
}

remove_obsolete_options() {
  OBSOLETE_OPTIONS=(
    "ACME_CONTACT"
  )

  for option in "${OBSOLETE_OPTIONS[@]}"; do
    if [[ "$option" == "ACME_CONTACT" ]]; then
      sed -i '/^# Lets Encrypt registration contact information/d' mailcow.conf
      sed -i "/^# Let's Encrypt registration contact information/d" mailcow.conf
      sed -i '/^# Optional: Leave empty for none/d' mailcow.conf
      sed -i '/^# This value is only used on first order!/d' mailcow.conf
      sed -i '/^# Setting it at a later point will require the following steps:/d' mailcow.conf
      sed -i '/^# https:\/\/docs.mailcow.email\/troubleshooting\/debug-reset_tls\//d' mailcow.conf
      sed -i '/^ACME_CONTACT=.*/d' mailcow.conf
      sed -i '/^#ACME_CONTACT=.*/d' mailcow.conf
    else
      sed -i "/^${option}=.*/d" mailcow.conf
      sed -i "/^#${option}=.*/d" mailcow.conf
    fi
  done
}
############## End Function Section ##############

# Check permissions
if [ "$(id -u)" -ne "0" ]; then
  echo "You need to be root"
  exit 1
fi

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Run pre-update-hook
if [ -f "${SCRIPT_DIR}/pre_update_hook.sh" ]; then
  bash "${SCRIPT_DIR}/pre_update_hook.sh"
fi

# Exit on error and pipefail
set -o pipefail

# Setting high dc timeout
export COMPOSE_HTTP_TIMEOUT=600

# Add /opt/bin to PATH
PATH=$PATH:/opt/bin

umask 0022

# Unset COMPOSE_COMMAND and DOCKER_COMPOSE_VERSION Variable to be on the newest state.
unset COMPOSE_COMMAND
unset DOCKER_COMPOSE_VERSION

get_installed_tools

get_docker_version

export LC_ALL=C
DATE=$(date +%Y-%m-%d_%H_%M_%S)
BRANCH="$(cd "${SCRIPT_DIR}"; git rev-parse --abbrev-ref HEAD)"

while (($#)); do
  case "${1}" in
    --check|-c)
      echo "Checking remote code for updates..."
      LATEST_REV=$(git ls-remote --exit-code --refs --quiet https://github.com/mailcow/mailcow-dockerized "${BRANCH}" | cut -f1)
      if [ "$?" -ne 0 ]; then
        echo "A problem occurred while trying to fetch the latest revision from github."
        exit 99
      fi
      if [[ -z $(git log HEAD --pretty=format:"%H" | grep "${LATEST_REV}") ]]; then
        echo -e "Updated code is available.\nThe changes can be found here: https://github.com/mailcow/mailcow-dockerized/commits/master"
        git log --date=short --pretty=format:"%ad - %s" "$(git rev-parse --short HEAD)"..origin/master
        exit 0
      else
        echo "No updates available."
        exit 3
      fi
    ;;
    --check-tags)
      echo "Checking remote tags for updates..."
      LATEST_TAG_REV=$(git ls-remote --exit-code --quiet --tags origin | tail -1 | cut -f1)
      if [ "$?" -ne 0 ]; then
        echo "A problem occurred while trying to fetch the latest tag from github."
        exit 99
      fi
      if [[ -z $(git log HEAD --pretty=format:"%H" | grep "${LATEST_TAG_REV}") ]]; then
        echo -e "New tag is available.\nThe changes can be found here: https://github.com/mailcow/mailcow-dockerized/releases/latest"
        exit 0
      else
        echo "No updates available."
        exit 3
      fi
    ;;
    --ours)
      MERGE_STRATEGY=ours
    ;;
    --skip-start)
      SKIP_START=y
    ;;
    --skip-ping-check)
      SKIP_PING_CHECK=y
    ;;
    --stable)
      CURRENT_BRANCH="$(cd "${SCRIPT_DIR}"; git rev-parse --abbrev-ref HEAD)"
      NEW_BRANCH="master"
    ;;
    --gc)
      echo -e "\e[32mCollecting garbage...\e[0m"
      docker_garbage
      exit 0
    ;;
    --nightly)
      CURRENT_BRANCH="$(cd "${SCRIPT_DIR}"; git rev-parse --abbrev-ref HEAD)"
      NEW_BRANCH="nightly"
    ;;
    --prefetch)
      echo -e "\e[32mPrefetching images...\e[0m"
      prefetch_images
      exit 0
    ;;
    -f|--force)
      echo -e "\e[32mRunning in forced mode...\e[0m"
      FORCE=y
    ;;
    -d|--dev)
      echo -e "\e[32mRunning in Developer mode...\e[0m"
      DEV=y
    ;;
    --legacy)
      CURRENT_BRANCH="$(cd "${SCRIPT_DIR}"; git rev-parse --abbrev-ref HEAD)"
      NEW_BRANCH="legacy"
    ;;
    --help|-h)
    echo './update.sh [-c|--check, --check-tags, --ours, --gc, --nightly, --prefetch, --skip-start, --skip-ping-check, --stable, --legacy, -f|--force, -d|--dev, -h|--help]

  -c|--check           -   Check for updates and exit (exit codes => 0: update available, 3: no updates)
  --check-tags         -   Check for newer tags and exit (exit codes => 0: newer tag available, 3: no newer tag)
  --ours               -   Use merge strategy option "ours" to solve conflicts in favor of non-mailcow code (local changes over remote changes), not recommended!
  --gc                 -   Run garbage collector to delete old image tags
  --nightly            -   Switch your mailcow updates to the unstable (nightly) branch. FOR TESTING PURPOSES ONLY!!!!
  --prefetch           -   Only prefetch new images and exit (useful to prepare updates)
  --skip-start         -   Do not start mailcow after update
  --skip-ping-check    -   Skip ICMP Check to public DNS resolvers (Use it only if you'\''ve blocked any ICMP Connections to your mailcow machine)
  --stable             -   Switch your mailcow updates to the stable (master) branch. Default unless you changed it with --nightly or --legacy.
  --legacy             -   Switch your mailcow updates to the legacy branch. The legacy branch will only receive security updates until February 2026.
  -f|--force           -   Force update, do not ask questions
  -d|--dev             -   Enables Developer Mode (No Checkout of update.sh for tests)
'
    exit 0
  esac
  shift
done

[[ ! -f mailcow.conf ]] && { echo -e "\e[31mmailcow.conf is missing! Is mailcow installed?\e[0m"; exit 1;}

chmod 600 mailcow.conf
source mailcow.conf

get_compose_type

DOTS=${MAILCOW_HOSTNAME//[^.]};
if [ ${#DOTS} -lt 1 ]; then
  echo -e "\e[31mMAILCOW_HOSTNAME (${MAILCOW_HOSTNAME}) is not a FQDN!\e[0m"
  sleep 1
  echo "Please change it to a FQDN and redeploy the stack with $COMPOSE_COMMAND up -d"
  exit 1
elif [[ "${MAILCOW_HOSTNAME: -1}" == "." ]]; then
  echo "MAILCOW_HOSTNAME (${MAILCOW_HOSTNAME}) is ending with a dot. This is not a valid FQDN!"
  exit 1
elif [ ${#DOTS} -eq 1 ]; then
  echo -e "\e[33mMAILCOW_HOSTNAME (${MAILCOW_HOSTNAME}) does not contain a Subdomain. This is not fully tested and may cause issues.\e[0m"
  echo "Find more information about why this message exists here: https://github.com/mailcow/mailcow-dockerized/issues/1572"
  read -r -p "Do you want to proceed anyway? [y/N] " response
  if [[ "$response" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    echo "OK. Proceeding."
  else
    echo "OK. Exiting."
    exit 1
  fi
fi

detect_bad_asn

if [[ ("${SKIP_PING_CHECK}" == "y") ]]; then
echo -e "\e[32mSkipping Ping Check...\e[0m"

else
   echo -en "Checking internet connection... "
   if ! check_online_status; then
      echo -e "\e[31mfailed\e[0m"
      exit 1
   else
      echo -e "\e[32mOK\e[0m"
   fi
fi

if ! [ "$NEW_BRANCH" ]; then
  echo -e "\e[33mDetecting which build your mailcow runs on...\e[0m"
  sleep 1
  if [ "${BRANCH}" == "master" ]; then
    echo -e "\e[32mYou are receiving stable updates (master).\e[0m"
    echo -e "\e[33mTo change that run the update.sh Script one time with the --nightly parameter to switch to nightly builds.\e[0m"

  elif [ "${BRANCH}" == "nightly" ]; then
    echo -e "\e[31mYou are receiving unstable updates (nightly). These are for testing purposes only!!!\e[0m"
    sleep 1
    echo -e "\e[33mTo change that run the update.sh Script one time with the --stable parameter to switch to stable builds.\e[0m"

  elif [ "${BRANCH}" == "legacy" ]; then
    echo -e "\e[31mYou are receiving legacy updates. The legacy branch will only receive security updates until February 2026.\e[0m"
    sleep 1
    echo -e "\e[33mTo change that run the update.sh Script one time with the --stable parameter to switch to stable builds.\e[0m"

  else
    echo -e "\e[33mYou are receiving updates from an unsupported branch.\e[0m"
    sleep 1
    echo -e "\e[33mThe mailcow stack might still work but it is recommended to switch to the master branch (stable builds).\e[0m"
    echo -e "\e[33mTo change that run the update.sh Script one time with the --stable parameter to switch to stable builds.\e[0m"
  fi
elif [ "$FORCE" ]; then
  echo -e "\e[31mYou are running in forced mode!\e[0m"
  echo -e "\e[31mA Branch Switch can only be performed manually (monitored).\e[0m"
  echo -e "\e[31mPlease rerun the update.sh Script without the --force/-f parameter.\e[0m"
  sleep 1
elif [ "$NEW_BRANCH" == "master" ] && [ "$CURRENT_BRANCH" != "master" ]; then
  echo -e "\e[33mYou are about to switch your mailcow updates to the stable (master) branch.\e[0m"
  sleep 1
  echo -e "\e[33mBefore you do: Please take a backup of all components to ensure that no data is lost...\e[0m"
  sleep 1
  echo -e "\e[31mWARNING: Please see on GitHub or ask in the community if a switch to master is stable or not.
  In some rear cases an update back to master can destroy your mailcow configuration such as database upgrade, etc.
  Normally an upgrade back to master should be safe during each full release.
  Check GitHub for Database changes and update only if there similar to the full release!\e[0m"
  read -r -p "Are you sure you that want to continue upgrading to the stable (master) branch? [y/N] " response
  if [[ ! "${response}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    echo "OK. If you prepared yourself for that please run the update.sh Script with the --stable parameter again to trigger this process here."
    exit 0
  fi
  BRANCH="$NEW_BRANCH"
  DIFF_DIRECTORY=update_diffs
  DIFF_FILE="${DIFF_DIRECTORY}/diff_before_upgrade_to_master_$(date +"%Y-%m-%d-%H-%M-%S")"
  mv diff_before_upgrade* "${DIFF_DIRECTORY}/" 2> /dev/null
  if ! git diff-index --quiet HEAD; then
    echo -e "\e[32mSaving diff to ${DIFF_FILE}...\e[0m"
    mkdir -p "${DIFF_DIRECTORY}"
    git diff "${BRANCH}" --stat > "${DIFF_FILE}"
    git diff "${BRANCH}" >> "${DIFF_FILE}"
  fi
  echo -e "\e[32mSwitching Branch to ${BRANCH}...\e[0m"
  git fetch origin
  git checkout -f "${BRANCH}"

elif [ "$NEW_BRANCH" == "nightly" ] && [ "$CURRENT_BRANCH" != "nightly" ]; then
  echo -e "\e[33mYou are about to switch your mailcow Updates to the unstable (nightly) branch.\e[0m"
  sleep 1
  echo -e "\e[33mBefore you do: Please take a backup of all components to ensure that no Data is lost...\e[0m"
  sleep 1
  echo -e "\e[31mWARNING: A switch to nightly is possible any time. But a switch back (to master) isn't.\e[0m"
  read -r -p "Are you sure you that want to continue upgrading to the unstable (nightly) branch? [y/N] " response
  if [[ ! "${response}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    echo "OK. If you prepared yourself for that please run the update.sh Script with the --nightly parameter again to trigger this process here."
    exit 0
  fi
  BRANCH=$NEW_BRANCH
  DIFF_DIRECTORY=update_diffs
  DIFF_FILE=${DIFF_DIRECTORY}/diff_before_upgrade_to_nightly_$(date +"%Y-%m-%d-%H-%M-%S")
  mv diff_before_upgrade* ${DIFF_DIRECTORY}/ 2> /dev/null
  if ! git diff-index --quiet HEAD; then
    echo -e "\e[32mSaving diff to ${DIFF_FILE}...\e[0m"
    mkdir -p ${DIFF_DIRECTORY}
    git diff "${BRANCH}" --stat > "${DIFF_FILE}"
    git diff "${BRANCH}" >> "${DIFF_FILE}"
  fi
  git fetch origin
  git checkout -f "${BRANCH}"
elif [ "$NEW_BRANCH" == "legacy" ] && [ "$CURRENT_BRANCH" != "legacy" ]; then
  echo -e "\e[33mYou are about to switch your mailcow Updates to the legacy branch.\e[0m"
  sleep 1
  echo -e "\e[33mBefore you do: Please take a backup of all components to ensure that no Data is lost...\e[0m"
  sleep 1
  echo -e "\e[31mWARNING: A switch to stable or nightly is possible any time.\e[0m"
  read -r -p "Are you sure you want to continue upgrading to the legacy branch? [y/N] " response
  if [[ ! "${response}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    echo "OK. If you prepared yourself for that please run the update.sh Script with the --legacy parameter again to trigger this process here."
    exit 0
  fi
  BRANCH=$NEW_BRANCH
  DIFF_DIRECTORY=update_diffs
  DIFF_FILE=${DIFF_DIRECTORY}/diff_before_upgrade_to_legacy_$(date +"%Y-%m-%d-%H-%M-%S")
  mv diff_before_upgrade* ${DIFF_DIRECTORY}/ 2> /dev/null
  if ! git diff-index --quiet HEAD; then
    echo -e "\e[32mSaving diff to ${DIFF_FILE}...\e[0m"
    mkdir -p ${DIFF_DIRECTORY}
    git diff "${BRANCH}" --stat > "${DIFF_FILE}"
    git diff "${BRANCH}" >> "${DIFF_FILE}"
  fi
  git fetch origin
  git checkout -f "${BRANCH}"
fi

if [ ! "$DEV" ]; then
  echo -e "\e[32mChecking for newer update script...\e[0m"
  SHA1_1="$(sha1sum update.sh)"
  git fetch origin
  git checkout "origin/${BRANCH}" -- update.sh
  SHA1_2="$(sha1sum update.sh)"
  if [[ "${SHA1_1}" != "${SHA1_2}" ]]; then
    chmod +x update.sh
    EXIT_COUNT+=1
  fi

  MODULE_DIR="$(dirname "$0")/_modules"
  echo -e "\e[32mChecking for updates in _modules...\e[0m"
  if [ ! -d "${MODULE_DIR}" ] || [ -z "$(ls -A "${MODULE_DIR}")" ]; then
    echo -e "\e[33m_modules missing or empty — fetching from origin...\e[0m"
    git checkout "origin/${BRANCH}" -- _modules
  else
    OLD_SUM="$(find "${MODULE_DIR}" -type f -exec sha1sum {} \; | sort | sha1sum)"
    git fetch origin
    git checkout "origin/${BRANCH}" -- _modules
    NEW_SUM="$(find "${MODULE_DIR}" -type f -exec sha1sum {} \; | sort | sha1sum)"

    if [[ "${OLD_SUM}" != "${NEW_SUM}" ]]; then
      EXIT_COUNT+=1
    fi
  fi

  if [ "${EXIT_COUNT}" -ge 1 ]; then
    echo "Changes for the update Script, please run this script again, exiting!"
    exit 2
  fi
  
fi

if [ ! "$FORCE" ]; then
  read -r -p "Are you sure you want to update mailcow: dockerized? All containers will be stopped. [y/N] " response
  if [[ ! "${response}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    echo "OK, exiting."
    exit 0
  fi
  detect_major_update
fi

echo -e "\e[32mValidating docker-compose stack configuration...\e[0m"
sed -i 's/HTTPS_BIND:-:/HTTPS_BIND:-/g' docker-compose.yml
sed -i 's/HTTP_BIND:-:/HTTP_BIND:-/g' docker-compose.yml
if ! $COMPOSE_COMMAND config -q; then
  echo -e "\e[31m\nOh no, something went wrong. Please check the error message above.\e[0m"
  exit 1
fi

echo -e "\e[32mChecking for conflicting bridges...\e[0m"
MAILCOW_BRIDGE=$($COMPOSE_COMMAND config | grep -i com.docker.network.bridge.name | cut -d':' -f2)
while read NAT_ID; do
  iptables -t nat -D POSTROUTING "$NAT_ID"
done < <(iptables -L -vn -t nat --line-numbers | grep "$IPV4_NETWORK" | grep -E 'MASQUERADE.*all' | grep -v "${MAILCOW_BRIDGE}" | cut -d' ' -f1)

DIFF_DIRECTORY=update_diffs
DIFF_FILE=${DIFF_DIRECTORY}/diff_before_update_$(date +"%Y-%m-%d-%H-%M-%S")
mv diff_before_update* ${DIFF_DIRECTORY}/ 2> /dev/null
if ! git diff-index --quiet HEAD; then
  echo -e "\e[32mSaving diff to ${DIFF_FILE}...\e[0m"
  mkdir -p ${DIFF_DIRECTORY}
  git diff --stat > "${DIFF_FILE}"
  git diff >> "${DIFF_FILE}"
fi

echo -e "\e[32mPrefetching images...\e[0m"
prefetch_images

echo -e "\e[32mStopping mailcow...\e[0m"
sleep 2
MAILCOW_CONTAINERS=($($COMPOSE_COMMAND ps -q))
$COMPOSE_COMMAND down
echo -e "\e[32mChecking for remaining containers...\e[0m"
sleep 2
for container in "${MAILCOW_CONTAINERS[@]}"; do
  docker rm -f "$container" 2> /dev/null
done

configure_ipv6

[[ -f data/conf/nginx/ZZZ-ejabberd.conf ]] && rm data/conf/nginx/ZZZ-ejabberd.conf
migrate_config_options
adapt_new_options
remove_obsolete_options

if [ ! "$DEV" ]; then
  DEFAULT_REPO="https://github.com/mailcow/mailcow-dockerized"
  CURRENT_REPO=$(git config --get remote.origin.url)
  if [ "$CURRENT_REPO" != "$DEFAULT_REPO" ]; then
    echo "The Repository currently used is not the default mailcow Repository."
    echo "Currently Repository: $CURRENT_REPO"
    echo "Default Repository:   $DEFAULT_REPO"
    if [ ! "$FORCE" ]; then
      read -r -p "Should it be changed back to default? [y/N] " repo_response
      if [[ "$repo_response" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
        git remote set-url origin $DEFAULT_REPO
      fi
    else
        echo "Running in forced mode... setting Repo to default!"
        git remote set-url origin $DEFAULT_REPO
    fi
  fi
fi

if [ ! "$DEV" ]; then
  echo -e "\e[32mCommitting current status...\e[0m"
  [[ -z "$(git config user.name)" ]] && git config user.name moo
  [[ -z "$(git config user.email)" ]] && git config user.email moo@cow.moo
  [[ ! -z $(git ls-files data/conf/rspamd/override.d/worker-controller-password.inc) ]] && git rm data/conf/rspamd/override.d/worker-controller-password.inc
  git add -u
  git commit -am "Before update on ${DATE}" > /dev/null
  echo -e "\e[32mFetching updated code from remote...\e[0m"
  git fetch origin #${BRANCH}
  echo -e "\e[32mMerging local with remote code (recursive, strategy: \"${MERGE_STRATEGY:-theirs}\", options: \"patience\"...\e[0m"
  git config merge.defaultToUpstream true
  git merge -X"${MERGE_STRATEGY:-theirs}" -Xpatience -m "After update on ${DATE}"
  # Need to use a variable to not pass return codes of if checks
  MERGE_RETURN=$?
  if [[ ${MERGE_RETURN} == 128 ]]; then
    echo -e "\e[31m\nOh no, what happened?\n=> You most likely added files to your local mailcow instance that were now added to the official mailcow repository. Please move them to another location before updating mailcow.\e[0m"
    exit 1
  elif [[ ${MERGE_RETURN} == 1 ]]; then
    echo -e "\e[93mPotential conflict, trying to fix...\e[0m"
    git status --porcelain | grep -E "UD|DU" | awk '{print $2}' | xargs rm -v
    git add -A
    git commit -m "After update on ${DATE}" > /dev/null
    git checkout .
    echo -e "\e[32mRemoved and recreated files if necessary.\e[0m"
  elif [[ ${MERGE_RETURN} != 0 ]]; then
    echo -e "\e[31m\nOh no, something went wrong. Please check the error message above.\e[0m"
    echo
    echo "Run $COMPOSE_COMMAND up -d to restart your stack without updates or try again after fixing the mentioned errors."
    exit 1
  fi
elif [ "$DEV" ]; then
  echo -e "\e[33mDEVELOPER MODE: Not creating a git diff and commiting it to prevent development stuff within a backup diff...\e[0m"
fi

echo -e "\e[32mFetching new images, if any...\e[0m"
sleep 2
$COMPOSE_COMMAND pull

# Fix missing SSL, does not overwrite existing files
[[ ! -d data/assets/ssl ]] && mkdir -p data/assets/ssl
cp -n -d data/assets/ssl-example/*.pem data/assets/ssl/

echo -e "Checking IPv6 settings... "
if grep -q 'SYSCTL_IPV6_DISABLED=1' mailcow.conf; then
  echo
  echo '!! IMPORTANT !!'
  echo
  echo 'SYSCTL_IPV6_DISABLED was removed due to complications. IPv6 can be disabled by editing "docker-compose.yml" and setting "enable_ipv6: true" to "enable_ipv6: false".'
  echo "This setting will only be active after a complete shutdown of mailcow by running $COMPOSE_COMMAND down followed by $COMPOSE_COMMAND up -d."
  echo
  echo '!! IMPORTANT !!'
  echo
  read -p "Press any key to continue..." < /dev/tty
fi

# Checking for old project name bug
sed -i --follow-symlinks 's#COMPOSEPROJECT_NAME#COMPOSE_PROJECT_NAME#g' mailcow.conf

# Fix Rspamd maps
if [ -f data/conf/rspamd/custom/global_from_blacklist.map ]; then
  mv data/conf/rspamd/custom/global_from_blacklist.map data/conf/rspamd/custom/global_smtp_from_blacklist.map
fi
if [ -f data/conf/rspamd/custom/global_from_whitelist.map ]; then
  mv data/conf/rspamd/custom/global_from_whitelist.map data/conf/rspamd/custom/global_smtp_from_whitelist.map
fi

# Fix deprecated metrics.conf
if [ -f "data/conf/rspamd/local.d/metrics.conf" ]; then
  if [ ! -z "$(git diff --name-only origin/master data/conf/rspamd/local.d/metrics.conf)" ]; then
    echo -e "\e[33mWARNING\e[0m - Please migrate your customizations of data/conf/rspamd/local.d/metrics.conf to actions.conf and groups.conf after this update."
    echo "The deprecated configuration file metrics.conf will be moved to metrics.conf_deprecated after updating mailcow."
  fi
  mv data/conf/rspamd/local.d/metrics.conf data/conf/rspamd/local.d/metrics.conf_deprecated
fi

# Set app_info.inc.php
if [ ${BRANCH} == "master" ]; then
  mailcow_git_version=$(git describe --tags $(git rev-list --tags --max-count=1))
elif [ ${BRANCH} == "nightly" ]; then
  mailcow_git_version=$(git rev-parse --short $(git rev-parse @{upstream}))
  mailcow_last_git_version=""
else
  mailcow_git_version=$(git rev-parse --short HEAD)
  mailcow_last_git_version=""
fi

mailcow_git_commit=$(git rev-parse "origin/${BRANCH}")
mailcow_git_commit_date=$(git log -1 --format=%ci @{upstream} )

if [ $? -eq 0 ]; then
  echo '<?php' > data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_VERSION="'$mailcow_git_version'";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_LAST_GIT_VERSION="";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_OWNER="mailcow";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_REPO="mailcow-dockerized";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_URL="https://github.com/mailcow/mailcow-dockerized";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_COMMIT="'$mailcow_git_commit'";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_COMMIT_DATE="'$mailcow_git_commit_date'";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_BRANCH="'$BRANCH'";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_UPDATEDAT='$(date +%s)';' >> data/web/inc/app_info.inc.php
  echo '?>' >> data/web/inc/app_info.inc.php
else
  echo '<?php' > data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_VERSION="'$mailcow_git_version'";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_LAST_GIT_VERSION="";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_OWNER="mailcow";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_REPO="mailcow-dockerized";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_URL="https://github.com/mailcow/mailcow-dockerized";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_COMMIT="";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_COMMIT_DATE="";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_BRANCH="'$BRANCH'";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_UPDATEDAT='$(date +%s)';' >> data/web/inc/app_info.inc.php
  echo '?>' >> data/web/inc/app_info.inc.php
  echo -e "\e[33mCannot determine current git repository version...\e[0m"
fi

if [[ ${SKIP_START} == "y" ]]; then
  echo -e "\e[33mNot starting mailcow, please run \"$COMPOSE_COMMAND up -d --remove-orphans\" to start mailcow.\e[0m"
else
  echo -e "\e[32mStarting mailcow...\e[0m"
  sleep 2
  $COMPOSE_COMMAND up -d --remove-orphans
fi

echo -e "\e[32mCollecting garbage...\e[0m"
docker_garbage

# Run post-update-hook
if [ -f "${SCRIPT_DIR}/post_update_hook.sh" ]; then
  bash "${SCRIPT_DIR}/post_update_hook.sh"
fi

# echo "In case you encounter any problem, hard-reset to a state before updating mailcow:"
# echo
# git reflog --color=always | grep "Before update on "
# echo
# echo "Use \"git reset --hard hash-on-the-left\" and run $COMPOSE_COMMAND up -d afterwards."
