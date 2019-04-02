FROM solr:7.7-alpine
USER root
COPY docker-entrypoint.sh /
COPY solr-config-7.7.0.xml /
COPY solr-schema-7.7.0.xml /


RUN apk --no-cache add su-exec curl tzdata \
  && chmod +x /docker-entrypoint.sh \
  && sync \
  && bash /docker-entrypoint.sh --bootstrap

ENTRYPOINT ["/docker-entrypoint.sh"]
