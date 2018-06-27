FROM alpine:3.6

LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

RUN apk add --update --no-cache \
  bash \
  acme-client \
  curl \
  openssl \
  bind-tools \
  jq \
  mariadb-client \
  redis \
  tini \
  tzdata

COPY docker-entrypoint.sh /srv/docker-entrypoint.sh
COPY expand6.sh /srv/expand6.sh

CMD ["/sbin/tini", "-g", "--", "/srv/docker-entrypoint.sh"]
