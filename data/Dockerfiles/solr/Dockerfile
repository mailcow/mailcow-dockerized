FROM solr:latest

ADD solr_core /opt/solr/server/solr/dovecot

USER root

RUN cp -R /opt/solr/example/files/conf/lang /opt/solr/server/solr/dovecot/conf/

RUN ["/bin/bash", "-c", "cp /opt/solr/example/files/conf/{protwords.txt,stopwords.txt,synonyms.txt,currency.xml,elevate.xml} /opt/solr/server/solr/dovecot/conf/"]
RUN mkdir -p /opt/solr/server/solr/dovecot/data/index

RUN chown -R solr:solr /opt/solr/server/solr/dovecot

# higher heap size to handle large mailboxes
RUN sed -i 's/#SOLR_HEAP="512m"/SOLR_HEAP="4096m"/g' /opt/solr/bin/solr.in.sh

USER solr
