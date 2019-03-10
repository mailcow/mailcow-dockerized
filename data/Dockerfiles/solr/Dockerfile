FROM solr:7.7-alpine
USER root
COPY docker-entrypoint.sh /

RUN apk --no-cache add su-exec curl tzdata \
  && chmod +x /docker-entrypoint.sh

ENTRYPOINT ["/docker-entrypoint.sh"]
