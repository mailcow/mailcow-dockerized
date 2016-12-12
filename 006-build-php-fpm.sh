#!/bin/bash

. mailcow.conf

NAME="php-fpm-mailcow"

if [[ ! -z $(docker ps -af "name=${NAME}" -q) ]]; then
	docker stop $(docker ps -af "name=${NAME}" -q)
	docker rm $(docker ps -af "name=${NAME}" -q)
fi

if [[ ! -z "$(docker images -q php:${PHPVERS})" ]]; then
    read -r -p "Found image locally. Delete local image and repull? [y/N] " response
    response=${response,,}
    if [[ $response =~ ^(yes|y)$ ]]; then
        docker rmi php:${PHPVERS}
    fi
fi

docker run \
	-v ${PWD}/data/web:/web:ro \
	-v ${PWD}/data/conf/rspamd/dynmaps:/dynmaps:ro \
    -v ${PWD}/data/dkim/:/shared/dkim/ \
	-d --network=${DOCKER_NETWORK} \
	--name ${NAME} \
	-h phpfpm \
	php:${PHPVERS}

echo "Installing intl and mysql pdo extension..."
docker exec ${NAME} /bin/bash -c "apt-get update && apt-get install -y zlib1g-dev libicu-dev g++ libidn11-dev"
docker exec ${NAME} docker-php-ext-configure intl pdo pdo_mysql
docker exec ${NAME} docker-php-ext-install intl pdo pdo_mysql
echo "Restarting container..."
docker restart ${NAME}
