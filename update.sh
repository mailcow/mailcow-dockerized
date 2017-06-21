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

echo -e "\e[32mChecking for newer update script...\e[0m"
SHA1_1=$(sha1sum update.sh)
git fetch origin ${BRANCH}
git checkout origin/${BRANCH} update.sh
SHA1_2=$(sha1sum update.sh)
if [[ ${SHA1_1} != ${SHA1_2} ]]; then
	echo "update.sh changed, please run this script again, exiting."
	chmod +x update.sh
	exit 0
fi
rm -f mv ${TMPFILE}

if [[ -f mailcow.conf ]]; then
	source mailcow.conf
else
	echo -e "\e[31mNo mailcow.conf - is mailcow installed?\e[0m"
	exit 1
fi

read -r -p "Are you sure you want to update mailcow: dockerized? All containers will be stopped. [y/N] " response
if [[ ! "$response" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
	echo "OK, exiting."
	exit 0
fi

echo -e "Stopping mailcow... "
sleep 2
docker-compose down

# Silently fixing remote url from andryyy to mailcow
git remote set-url origin https://github.com/mailcow/mailcow-dockerized
echo -e "\e[32mCommitting current status...\e[0m"
git add -u
git commit -am "Before update on ${DATE}" > /dev/null
echo -e "\e[32mFetching updated code from remote...\e[0m"
git fetch origin ${BRANCH}
echo -e "\e[32mMerging local with remote code (recursive, options: \"theirs\", \"patience\"...\e[0m"
git merge -Xtheirs -Xpatience -m "After update on ${DATE}"
# Need to use a variable to not pass return codes of if checks
MERGE_RETURN=$?
if [[ ${MERGE_RETURN} == 128 ]]; then
	echo -e "\e[31m\nOh no, what happened?\n=> You most likely added files to your local mailcow instance that were now added to the official mailcow repository. Please move them to another location before updating mailcow.\e[0m"
	exit 1
elif [[ ${MERGE_RETURN} == 1 ]]; then
	echo -e "\e[93mPotenial conflict, trying to fix...\e[0m"
	git status --porcelain | grep -E "UD|DU" | awk '{print $2}' | xargs rm -v
	git add -A
	git commit -m "After update on ${DATE}" > /dev/null
	git checkout .
	echo -e "\e[32mRemoved and recreated files if necessary.\e[0m"
elif [[ ${MERGE_RETURN} != 0 ]]; then
	echo -e "\e[31m\nOh no, something went wrong. Please check the error message above.\e[0m"
	echo
	echo "Run docker-compose up -d to restart your stack without updates or try again after fixing the mentioned errors."
	exit 1
fi

echo -e "\e[32mFetching new images, if any...\e[0m"
sleep 2
docker-compose pull

# Fix missing SSL, does not overwrite existing files
[[ ! -d data/assets/ssl ]] && mkdir -p data/assets/ssl
cp -n data/assets/ssl-example/*.pem data/assets/ssl/

echo -e "\e[32mFetching new docker-compose version...\e[0m"
sleep 2
curl -L https://github.com/docker/compose/releases/download/$(curl -Ls https://www.servercow.de/docker-compose/latest.php)/docker-compose-$(uname -s)-$(uname -m) > $(which docker-compose)
chmod +x $(which docker-compose)

echo -e "\e[32mStarting mailcow...\e[0m"
sleep 2
docker-compose up -d --remove-orphans
#echo -e "\e[32mCleaning up Docker objects...\e[0m"
if docker images -f "dangling=true" | grep ago --quiet; then
	docker rmi -f $(docker images -f "dangling=true" -q)
	docker volume rm $(docker volume ls -qf dangling=true)
fi

echo "In case you encounter any problem, hard-reset to a state before updating mailcow:"
echo
git reflog --color=always | grep "Before update on "
echo
echo "Use \"git reset --hard hash-on-the-left\" and run docker-compose up -d afterwards."
