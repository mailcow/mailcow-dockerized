#!/bin/bash

/bin/bash port-check.sh
[[ $? != 0 ]] && exit 1

for build in $(ls *build*.sh | grep -v all); do
    echo "Starting build file ${buildx} ..."
	/bin/bash ${build}
done
/bin/bash fix-permissions.sh
