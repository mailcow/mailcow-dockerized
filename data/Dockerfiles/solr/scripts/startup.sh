#!/bin/bash
#
# This script starts Solr on localhost, creates a core with "solr create"

set -e
echo "Executing $0 $@"

# allow easier debugging with `docker run -e VERBOSE=yes`
if [[ "$VERBOSE" = "yes" ]]; then
    set -x
fi

# run the optional initdb
. /opt/docker-solr/scripts/run-initdb

# keep a sentinel file so we don't try to create the core a second time
# for example when we restart a container.
sentinel=/opt/docker-solr/core_created
if [ -f $sentinel ]; then
    echo "skipping core creation"
else
    # start a Solr so we can use the Schema API, but only on localhost,
    # so that clients don't see Solr until we have configured it.
    start-local-solr

    echo "Creating core with: ${@:1}"
    /opt/solr/bin/solr create "${@:1}"

    # See https://github.com/docker-solr/docker-solr/issues/27
    echo "Checking core"
    if ! wget -O - 'http://localhost:8983/solr/admin/cores?action=STATUS' | grep -q instanceDir; then
      echo "Could not find any cores"
      exit 1
    fi

    echo "Created core with: ${@:1}"
    echo "Copying solrconfig file"
    cp /opt/solr/conf/schema.xml /opt/solr/server/solr/dovecot/conf/schema.xml
    stop-local-solr
    touch $sentinel
fi
