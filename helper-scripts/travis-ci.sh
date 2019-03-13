############################################################
# This sh file is used by Travis CI do not use it yourself #
############################################################

function docker_tag_exists() {
    curl --silent -f -lSL https://index.docker.io/v1/repositories/$1/tags/$2 > /dev/null
}

for container in $(grep -oP "image: \Kmailcow.+" docker-compose.yml); do
    REPOSITORY=${container/:*}
    TAG=${container/*:}

    if [ $REPOSITORY == $BUILDCONTAINER ]
    then
      IMAGE=${REPOSITORY}
      IMAGETAG=${TAG}
    fi
done


if docker_tag_exists $IMAGE $IMAGETAG; then
    echo "Image exists"
    exit
else
    echo "Image does not exist build it"
    docker-compose build ${IMAGE#*/}-mailcow
    docker push mailcow/${IMAGE#*/}:${IMAGETAG}
fi
