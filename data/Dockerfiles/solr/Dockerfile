FROM solr:latest

ADD solr_core /opt/solr/server/solr/dovecot

USER root

RUN chown -R solr:solr /opt/solr/server/solr/dovecot

# higher heap size to handle large mailboxes
RUN sed -i 's/#SOLR_HEAP="512m"/SOLR_HEAP="4096m"/g' /opt/solr/bin/solr.in.sh

USER solr
