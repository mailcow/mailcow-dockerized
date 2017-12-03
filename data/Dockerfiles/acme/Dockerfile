FROM alpine:3.6

LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

RUN apk add --update --no-cache \
	bash \
	curl \
	openssl \
	bind-tools \
	jq \
  libressl-dev \
  libbsd-dev \
  libseccomp-dev \
	mariadb-client \
  tini \
  make \
  gcc \
  libressl \
  libc-dev \
  linux-headers \
  ca-certificates \
  && curl -s https://kristaps.bsd.lv/acme-client/snapshots/acme-client-portable.tgz | tar xfvz - \
  && cd acme-client-* \
  && sed -i 's/LE-SA-v1.1.1-August-1-2016.pdf/LE-SA-v1.2-November-15-2017.pdf/g' main.c \
  && make \
  && make install \
  && cd .. \
  && rm -rf acme-client-* \
  && apk del libressl-dev libbsd-dev libseccomp-dev libc-dev linux-headers

COPY docker-entrypoint.sh /srv/docker-entrypoint.sh

CMD ["/sbin/tini", "-g", "--", "/srv/docker-entrypoint.sh"]
