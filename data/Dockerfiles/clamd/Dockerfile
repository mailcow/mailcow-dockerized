FROM alpine:3.6

LABEL maintainer "André Peters <andre.peters@servercow.de>"

# Add scripts
COPY dl_files.sh bootstrap.sh ./

# Installation
ENV CLAMAV 0.100.0

RUN apk add --no-cache --virtual build-dependencies alpine-sdk ncurses-dev zlib-dev bzip2-dev pcre-dev linux-headers fts-dev libxml2-dev libressl-dev \
  && apk add --no-cache curl bash tini libxml2 libbz2 pcre fts libressl \
  && wget -O - https://www.clamav.net/downloads/production/clamav-${CLAMAV}.tar.gz | tar xfvz - \
  && cd clamav-${CLAMAV} \
  && LIBS=-lfts ./configure \
  --prefix=/usr \
  --libdir=/usr/lib \
  --sysconfdir=/etc/clamav \
  --mandir=/usr/share/man \
  --infodir=/usr/share/info \
  --without-iconv \
  --disable-llvm \
  --with-user=clamav \
  --with-group=clamav \
  --with-dbdir=/var/lib/clamav \
  --enable-clamdtop \
  --enable-bigstack \
  --with-pcre \
  && make -j4 \
  && make install \
  && make clean \
  && cd .. && rm -rf clamav-${CLAMAV} \
  && apk del build-dependencies \
  && addgroup -S clamav \
  && adduser -S -D -h /var/lib/clamav -s /sbin/nologin -G clamav -g clamav clamav \
  && adduser clamav tty \
  && mkdir -p /run/clamav \
  && chown clamav:clamav /run/clamav \
  && chmod +x /dl_files.sh \
  && set -ex; /bin/bash /dl_files.sh \
  && chmod 750 /run/clamav

# Port provision
EXPOSE 3310

# AV daemon bootstrapping
CMD ["/sbin/tini", "-g", "--", "/bootstrap.sh"]
