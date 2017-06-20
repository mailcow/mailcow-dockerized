#!/bin/bash

set -o pipefail
export LC_ALL=C
DATE=$(date +%Y-%m-%d_%H_%M_%S)
BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [[ -f mailcow.conf ]]; then
	source mailcow.conf
else
	echo -e "\e[31mNo mailcow.conf - is mailcow installed?\e[0m"
	exit 1
fi

# Stopping mailcow
docker-compose down

for image in "phpfpm" "dovecot" "postfix" "sogo" "unbound" "rspamd" "clamd" "fail2ban"; do
if [[ ! -z $(docker images mailcow/${image} -q) ]]; then
  echo -e "\e[32mSaving mailcow/${image} to mailcow/${image}:${DATE}...\e[90m"
  docker tag mailcow/${image} mailcow/${image}:${DATE}
fi
done

# Silently fixing remote url from andryyy to mailcow
git remote set-url origin https://github.com/mailcow/mailcow-dockerized
echo -e "\e[32mCommitting current status...\e[90m"
git add -u
git commit -am "Before update on ${DATE}" > /dev/null
echo -e "\e[32mFetching updated code from remote...\e[90m"
git fetch origin ${BRANCH}
echo -e "\e[32mMerging local with remote code...\e[90m"
git merge -Xtheirs -Xpatience -m "After update on ${DATE}"

if [[ $? == 1 ]]; then
  echo -e "\e[31mRun into conflict, trying to fix...\e[90m"
  git status --porcelain | grep -E "UD|DU" | awk '{print $2}' | xargs rm -v
  git add -A
  git commit -m "After update on ${DATE}" > /dev/null
  git checkout .
  echo -e "\e[32mRemoved and recreated files if necessary.\e[90m"
fi
echo -e "\e[32mFetching new images, if any...\e[0m"
docker-compose pull
echo
#echo -e "\e[32mHashes to revert to:\e[0m"
#git reflog --color=always | grep "Before update on "

# TODO: Menu, select hard reset, select reset to "before update" etc.
#git reset --hard origin/${BRANCH}

# Fix missing SSL, does not overwrite existing files
[[ ! -d data/assets/ssl ]] && mkdir -p data/assets/ssl
cp -n data/assets/ssl-example/*.pem data/assets/ssl/
