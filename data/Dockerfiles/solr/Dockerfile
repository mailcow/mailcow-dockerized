FROM solr:7

ADD ./scripts/ /
ADD https://raw.githubusercontent.com/dovecot/core/master-2.2/doc/solr-schema.xml /opt/solr/conf/schema.xml
USER root
RUN chown solr:solr /opt/solr/conf/schema.xml
# higher heap size to handle large mailboxes
RUN sed -i 's/#SOLR_HEAP="512m"/SOLR_HEAP="'${JAVA_HEAPSIZE:-4096}'m"/g' /opt/solr/bin/solr.in.sh

# make solr config script executablle
RUN chown solr:solr /startup.sh && \
    chmod 744 /startup.sh

USER solr

RUN ["/bin/bash", "-c", "/startup.sh -c dovecot"]
