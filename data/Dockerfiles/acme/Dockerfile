FROM alpine:3.18

LABEL maintainer "The Infrastructure Company GmbH <info@servercow.de>"

ARG PIP_BREAK_SYSTEM_PACKAGES=1
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
  py3-pip \
  && pip3 install --upgrade pip \
  && pip3 install acme-tiny

COPY acme.sh /srv/acme.sh
COPY functions.sh /srv/functions.sh
COPY obtain-certificate.sh /srv/obtain-certificate.sh
COPY reload-configurations.sh /srv/reload-configurations.sh
COPY expand6.sh /srv/expand6.sh

RUN chmod +x /srv/*.sh

CMD ["/sbin/tini", "-g", "--", "/srv/acme.sh"]
