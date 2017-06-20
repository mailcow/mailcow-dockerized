#!/bin/bash

echo -en "Checking internet connection... "
timeout 1 bash -c "echo >/dev/tcp/8.8.8.8/53"
if [[ $? != 0 ]]; then
	echo -e "\e[31mfailed\e[0m"
	exit 1
else
	echo -e "\e[32mOK\e[0m"
fi

if [[ -z $(which curl) ]]; then echo "Cannot find curl, exiting."; exit 1; fi
if [[ -z $(which docker-compose) ]]; then echo "Cannot find docker-compose, exiting."; exit 1; fi
if [[ -z $(which docker) ]]; then echo "Cannot find docker, exiting."; exit 1; fi
if [[ -z $(which git) ]]; then echo "Cannot find git, exiting."; exit 1; fi
if [[ -z $(which awk) ]]; then echo "Cannot find awk, exiting."; exit 1; fi
if [[ -z $(which sha1sum) ]]; then echo "Cannot find sha1sum, exiting."; exit 1; fi

set -o pipefail
export LC_ALL=C
DATE=$(date +%Y-%m-%d_%H_%M_%S)
BRANCH=$(git rev-parse --abbrev-ref HEAD)
TMPFILE=$(mktemp "${TMPDIR:-/tmp}/curldata.XXXXXX")

curl -#o ${TMPFILE} https://raw.githubusercontent.com/mailcow/mailcow-dockerized/dev/update.sh
if [[ $(sha1sum ${TMPFILE} | awk '{ print $1 }') != $(sha1sum ./update.sh | awk '{ print $1 }') ]]; then
	echo "Updating script, please run this script again, exiting."
	chmod +x ${TMPFILE}
	mv ${TMPFILE} ./update.sh
	exit 0
fi
rm -f mv ${TMPFILE}

if [[ -f mailcow.conf ]]; then
	source mailcow.conf
else
	echo -e "\e[31mNo mailcow.conf - is mailcow installed?\e[0m"
	exit 1
fi

read -r -p "Are you sure? [y/N] " response
if [[ ! "$response" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
	echo "OK, exiting."
	exit 0
fi

echo -e "Stopping mailcow... "
# Stopping mailcow
docker-compose down

# Silently fixing remote url from andryyy to mailcow
git remote set-url origin https://github.com/mailcow/mailcow-dockerized
echo -e "\e[32mCommitting current status...\e[90m"
git add -u
git commit -am "Before update on ${DATE}" > /dev/null
echo -e "\e[32mFetching updated code from remote...\e[90m"
git fetch origin ${BRANCH}
echo -e "\e[32mMerging local with remote code (recursive, options: \"theirs\", \"patience\"...\e[90m"
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

curl -L https://github.com/docker/compose/releases/download/$(curl -Ls https://www.servercow.de/docker-compose/latest.php)/docker-compose-$(uname -s)-$(uname -m) > $(which docker-compose)
chmod +x $(which docker-compose)

docker-compose up -d --remove-orphans
#echo -e "\e[32mCleaning up...\e[0m"
if docker images -f "dangling=true" | grep ago --quiet; then
	docker rmi -f $(docker images -f "dangling=true" -q)
	docker volume rm $(docker volume ls -qf dangling=true)
fi
