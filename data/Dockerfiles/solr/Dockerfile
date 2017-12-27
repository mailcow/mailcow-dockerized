FROM solr:7

ADD solr_core /opt/solr/server/solr/dovecot

USER root

RUN cp -R /opt/solr/example/files/conf/lang /opt/solr/server/solr/dovecot/conf/

RUN ["/bin/bash", "-c", "cp /opt/solr/example/files/conf/{protwords.txt,stopwords.txt,synonyms.txt,currency.xml,elevate.xml} /opt/solr/server/solr/dovecot/conf/"]

RUN chown -R solr:solr /opt/solr/server/solr/dovecot

RUN mkdir "/opt/solr/server/solr/dovecot/data" && \
  chown -R solr:solr "/opt/solr/server/solr/dovecot/data"

# higher heap size to handle large mailboxes
RUN sed -i 's/#SOLR_HEAP="512m"/SOLR_HEAP="4096m"/g' /opt/solr/bin/solr.in.sh

USER solr
