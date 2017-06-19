#!/bin/bash

set -o pipefail
export LC_ALL=C
DATE=$(date)
BRANCH=$(git rev-parse --abbrev-ref HEAD)

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
echo -e "\e[32mDone!\e[0m"

# TODO: Menu, select hard reset etc.
#git reset --hard origin/${BRANCH}
