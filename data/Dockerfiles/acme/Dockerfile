FROM alpine:3.10

LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

RUN apk upgrade --no-cache \
  && apk add --update --no-cache \
  bash \
  curl \
  openssl \
  bind-tools \
  jq \
  mariadb-client \
  redis \
  tini \
  tzdata \
  python3 \
  && python3 -m pip install --upgrade pip \
  && python3 -m pip install acme-tiny

COPY docker-entrypoint.sh /srv/docker-entrypoint.sh
COPY expand6.sh /srv/expand6.sh

CMD ["/sbin/tini", "-g", "--", "/srv/docker-entrypoint.sh"]
