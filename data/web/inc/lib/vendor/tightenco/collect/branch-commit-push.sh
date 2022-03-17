#!/bin/bash


GREEN='\033[0;32m'
RED='\033[0;31m'
WHITE='\033[0;37m'
RESET='\033[0m'

function validateVersion()
{
    echo ""
    passedVersion=$1
    echo -e "${WHITE}-- Validating tag '$passedVersion'...${RESET}"

    # Todo: validate the version here using a regex; if fail, just exit
    #       ... expect 8.75.0, with no v in front of it

    if [[ $passedVersion == '' ]]; then
        echo -e "\n-- Invalid tag. Tags should be structured without v; e.g. 8.57.0"
        exit
    fi

    echo -e "${WHITE}-- Tag valid.${RESET}"
    echo ""
}

# Exit script if any command fails (e.g. phpunit)
set -e


# Require confirmation it's set up corrctly
echo 
echo -e "${WHITE}-- This script is meant to be run after running upgrade.sh, BEFORE committing to Git.${RESET}"

while true; do
    echo -e "${GREEN}-- Is that the current state of your local project?${RESET}"
    read -p "-- (y/n) " yn
    case $yn in
        [Yy]* ) break;;
        [Nn]* ) exit;;
        * ) echo "Please answer y or n.";;
    esac
done

# Get the version and exit if not valid
validateVersion $1

# Create official v prefaced version
version="v$1"

# Run tests (and bail if they fail)
phpunit
echo -e "\n${WHITE}-- Tests succeeded.${RESET}"

# Branch
echo -e "\n${WHITE}-- Creating a Git branch '$version-changes'...${RESET}\n"
git checkout -b $version-changes

# Add and commit, with "v8.57.0 changes" as the commit name
git add -A
git commit -m "$version changes"

echo 
echo -e "${WHITE}-- Git committed.${RESET}"

# Push
git push -u origin $version-changes
