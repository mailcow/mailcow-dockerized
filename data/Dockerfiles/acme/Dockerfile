FROM alpine:3.6

LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

RUN apk add --update --no-cache \
      acme-client \
      curl \
      openssl

COPY docker.sh /srv/docker-entrypoint.sh

ENTRYPOINT ["/srv/docker-entrypoint.sh"]
