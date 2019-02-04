FROM alpine:3.9

LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

RUN apk add --update --no-cache \
  bash \
  curl \
  openssl \
  bind-tools \
  jq \
  mariadb-client \
  redis \
  tini \
  tzdata \
  py-pip \
  && pip install --upgrade pip \
  && pip install acme-tiny

COPY docker-entrypoint.sh /srv/docker-entrypoint.sh
COPY expand6.sh /srv/expand6.sh

CMD ["/sbin/tini", "-g", "--", "/srv/docker-entrypoint.sh"]
