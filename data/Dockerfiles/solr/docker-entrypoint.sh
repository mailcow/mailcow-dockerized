#!/bin/bash

if [[ "${SKIP_SOLR}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
  echo "SKIP_SOLR=y, skipping Solr..."
  sleep 365d
  exit 0
fi

MEM_TOTAL=$(awk '/MemTotal/ {print $2}' /proc/meminfo)

if [[ "${1}" != "--bootstrap" ]]; then
  if [ ${MEM_TOTAL} -lt "2097152" ]; then
    echo "System memory less than 2 GB, skipping Solr..."
    sleep 365d
    exit 0
  fi
fi

set -e

# allow easier debugging with `docker run -e VERBOSE=yes`
if [[ "$VERBOSE" = "yes" ]]; then
  set -x
fi

# run the optional initdb
. /opt/docker-solr/scripts/run-initdb

# fixing volume permission

[[ -d /opt/solr/server/solr/dovecot-fts/data ]] && chown -R solr:solr /opt/solr/server/solr/dovecot-fts/data
if [[ "${1}" != "--bootstrap" ]]; then
  sed -i '/SOLR_HEAP=/c\SOLR_HEAP="'${SOLR_HEAP:-1024}'m"' /opt/solr/bin/solr.in.sh
else
  sed -i '/SOLR_HEAP=/c\SOLR_HEAP="256m"' /opt/solr/bin/solr.in.sh
fi

# keep a sentinel file so we don't try to create the core a second time
# for example when we restart a container.
# todo: check if a core exists without sentinel file

SENTINEL=/opt/docker-solr/fts_core_created

if [[ -f ${SENTINEL} ]]; then
  echo "skipping core creation"
else
  echo "Starting local Solr instance to setup configuration"
  su-exec solr start-local-solr

  echo "Creating core \"dovecot-fts\""
  su-exec solr /opt/solr/bin/solr create -c "dovecot-fts"

  # See https://github.com/docker-solr/docker-solr/issues/27
  echo "Checking core"
  while ! wget -O - 'http://localhost:8983/solr/admin/cores?action=STATUS' | grep -q instanceDir; do
    echo "Could not find any cores, waiting..."
    sleep 3
  done

  echo "Created core \"dovecot-fts\""
  touch ${SENTINEL}

  echo "Stopping local Solr"
  su-exec solr stop-local-solr
fi

rm -f /opt/solr/server/solr/dovecot-fts/conf/schema.xml
rm -f /opt/solr/server/solr/dovecot-fts/conf/managed-schema
rm -f /opt/solr/server/solr/dovecot-fts/conf/solrconfig.xml

cp /etc/solr/solr-config-7.7.0.xml /opt/solr/server/solr/dovecot-fts/conf/solrconfig.xml
cp /etc/solr/solr-schema-7.7.0.xml /opt/solr/server/solr/dovecot-fts/conf/schema.xml

chown -R solr:solr /opt/solr/server/solr/dovecot-fts/conf/{schema.xml,solrconfig.xml}

exec su-exec solr solr-foreground
