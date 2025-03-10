#!/bin/bash

SCRIPT_DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
source "${SCRIPT_DIR}/../mailcow.conf"

VOLUME="${COMPOSE_PROJECT_NAME}_redis-vol-1"
if ! docker volume inspect "$VOLUME" &>/dev/null; then
    echo "Error: Docker volume '$VOLUME' does not exist. Nothing to migrate."
    exit 1
fi

read -p "Do you want to proceed with the migration of your old redis data to valkey? (y/n) " CONFIRM
if [[ ! "$CONFIRM" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    echo "Migration aborted."
    exit 0
fi

# Run the old Redis container
docker run -d --name redis-old-mailcow \
    --restart always \
    --network ${COMPOSE_PROJECT_NAME}_mailcow-network \
    --hostname redis-old \
    --volume ${VOLUME}:/data/ \
    --volume ${SCRIPT_DIR}/../data/conf/valkey/valkey-conf.sh:/valkey-conf.sh:z \
    --entrypoint "/bin/sh" \
    -e VALKEYPASS="${VALKEYPASS}" \
    redis:7.4.2-alpine -c "/valkey-conf.sh && redis-server /valkey.conf"


# Wait for old Redis to be ready
echo "Waiting for redis-old-mailcow to be ready..."
until docker exec redis-old-mailcow redis-cli -a "$VALKEYPASS" ping | grep -q "PONG"; do
    echo "Redis not ready yet..."
    sleep 2
done
echo "redis-old-mailcow is ready!"

# Run the migrate container
docker run --rm --name valkeymigrator-mailcow \
    --network ${COMPOSE_PROJECT_NAME}_mailcow-network \
    -e VALKEYPASS="${VALKEYPASS}" \
    mailcow/valkeymigrator:0.1

echo "Migration completed!"
docker stop redis-old-mailcow
docker rm redis-old-mailcow

read -p "Do you want to delete the old Redis volume? (y/n) " CONFIRM
if [[ "$CONFIRM" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    docker volume rm "$VOLUME"
    echo "Docker volume '$VOLUME' has been deleted."
fi
