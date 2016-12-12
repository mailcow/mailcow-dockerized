#!/bin/bash

. mailcow.conf

NAME="mariadb-mailcow"

reconf() {
	echo "Installing database schema (this will not overwrite existing data)"
	echo "It may take a while for MariaDB to warm up, please wait..."
	echo docker exec ${NAME} mysql -u${DBUSER} -p${DBPASS} ${DBNAME}
	until docker exec ${NAME} /bin/bash -c "mysql -u'${DBUSER}' -p'${DBPASS}' ${DBNAME} < /assets/init.sql"; do
		echo "Trying again in 2 seconds..."
		sleep 2
	done
	echo "Done."
}

dump() {
	DATE=$(date +"%Y%m%d_%H%M%S")
    echo "Creating dump file ./backup_${DBNAME}_${DATE}.sql"
    docker exec -it ${NAME} /bin/bash mysqldump --default-character-set=utf8mb4 -u${DBUSER} -p${DBPASS} ${DBNAME} > backup_${DBNAME}_${DATE}.sql
}

restore() {
    echo "Restoring dump file ${2}..."
    docker exec -i ${NAME} mysql -u${DBUSER} -p${DBPASS} ${DBNAME} < ${1}
}

insert_admin() {
	echo 'Setting mailcow UI admin login to "admin:moohoo"...'
	echo "It may take a while for MariaDB to warm up, please wait..."
	until docker exec ${NAME} /bin/bash -c "mysql -u'${DBUSER}' -p'${DBPASS}' ${DBNAME} < /assets/pw.sql"; do
		echo "Trying again in 2 seconds..."
		sleep 2
	done
	echo "Done."
}

client() {
	echo "==============================="
	echo "DB: ${DBNAME} - USER: ${DBUSER}"
	echo "==============================="
    docker exec -it ${NAME} mysql -u${DBUSER} -p${DBPASS} ${DBNAME}
}

if [[ ${1} == "--init-schema" ]]; then
	reconf
    exit 0
elif [[ ${1} == "--dump" ]]; then
	dump
	exit 0
elif [[ ${1} == "--restore" ]]; then
	if [[ -z ${2} || ! -f ${2} ]]; then
		echo "Invalid input file"
		exit 1
	fi
	restore ${2}
	exit 0
elif [[ ${1} == "--client" ]]; then
	client
	exit 0
elif [[ ${1} == "--reset-admin" ]]; then
	insert_admin
	exit 0
elif [[ ! -z ${1} ]]; then
	echo "Unknown parameter"
	exit 1
fi

echo "Stopping and removing containers with name tag ${NAME}..."
if [[ ! -z $(docker ps -af "name=${NAME}" -q) ]]; then
	docker stop $(docker ps -af "name=${NAME}" -q)
	docker rm $(docker ps -af "name=${NAME}" -q)
fi

if [[ ! -z "$(docker images -q mariadb:${DBVERS})" ]]; then
    read -r -p "Found image locally. Delete local image and repull? [y/N] " response
    response=${response,,}
    if [[ $response =~ ^(yes|y)$ ]]; then
        docker rmi mariadb:${DBVERS}
    fi
fi

docker run \
	-v ${PWD}/data/db/mysql/:/var/lib/mysql/ \
	-v ${PWD}/data/conf/mysql/:/etc/mysql/conf.d/:ro \
	-v ${PWD}/data/assets/mysql:/assets:ro \
	--name=${NAME} \
	--network=${DOCKER_NETWORK} \
	-h mysql \
	-e MYSQL_ROOT_PASSWORD=${DBROOT} \
	-e MYSQL_DATABASE=${DBNAME} \
	-e MYSQL_USER=${DBUSER} \
	-e MYSQL_PASSWORD=${DBPASS} \
	-d mariadb:${DBVERS}

reconf

read -r -p "Do you want to reset mailcow admin to admin:moohoo? [y/N] " response
response=${response,,}
if [[ $response =~ ^(yes|y)$ ]]; then
	insert_admin
fi
