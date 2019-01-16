FROM solr:7-alpine
USER root
COPY docker-entrypoint.sh /

RUN apk --no-cache add su-exec curl \
  && chmod +x /docker-entrypoint.sh \
  && /docker-entrypoint.sh --bootstrap

ENTRYPOINT ["/docker-entrypoint.sh"]
