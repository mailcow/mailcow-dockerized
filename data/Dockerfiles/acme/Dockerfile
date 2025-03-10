FROM alpine:3.21

LABEL maintainer = "The Infrastructure Company GmbH <info@servercow.de>"

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
  acme-tiny

COPY acme.sh /srv/acme.sh
COPY functions.sh /srv/functions.sh
COPY obtain-certificate.sh /srv/obtain-certificate.sh
COPY reload-configurations.sh /srv/reload-configurations.sh
COPY expand6.sh /srv/expand6.sh

RUN chmod +x /srv/*.sh

CMD ["/sbin/tini", "-g", "--", "/srv/acme.sh"]
