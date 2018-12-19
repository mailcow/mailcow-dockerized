FROM debian:stretch-slim
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ARG DEBIAN_FRONTEND=noninteractive
ENV LC_ALL C
ENV GOSU_VERSION 1.9
ENV SOGO_VERSION 4.0.4

# Prerequisites
RUN apt-get update && apt-get install -y --no-install-recommends \
  apt-transport-https \
  ca-certificates \
  cron \
  gettext \
  mysql-client \
  supervisor \
  syslog-ng \
  syslog-ng-core \
  syslog-ng-mod-redis \
  dirmngr \
  netcat \
  psmisc \
  wget \
  patch \
  git \
  devscripts \
  debhelper \
  build-essential \
  gnustep-make \
  gnustep-base-runtime \
  libgnustep-base-dev \
  libgnustep-base1.24 \
  gobjc \
  libxml2-dev \
  libldap2-dev \
  libssl-dev \
  zlib1g-dev \
  libpq-dev \
  default-libmysqlclient-dev \
  liblasso3-dev \
  libmemcached-dev \
  mysql-client \
  libcurl4-openssl-dev \
  && rm -rf /var/lib/apt/lists/* \
  && dpkgArch="$(dpkg --print-architecture | awk -F- '{ print $NF }')" \
  && wget -O /usr/local/bin/gosu "https://github.com/tianon/gosu/releases/download/$GOSU_VERSION/gosu-$dpkgArch" \
  && chmod +x /usr/local/bin/gosu \
  && gosu nobody true \
  && mkdir /tmp/sogo_build \
  && cd /tmp/sogo_build \
  && git clone -b SOPE-${SOGO_VERSION} https://github.com/inverse-inc/sope.git \
  && cd sope \
  && ./configure --enable-xml --enable-mysql --enable-openldap --with-gnustep \
  && make -j4 \
  && make install \
  && cd /tmp/sogo_build \
  && git clone -b SOGo-${SOGO_VERSION} https://github.com/inverse-inc/sogo.git \
  && cd sogo \
  && ./configure --enable-saml2 \
  && make -j4 \
  && make install \
  && groupadd -g 6000 sogo \
  && useradd -g sogo -u 6000 sogo -d /var/lib/sogo \
  && mkdir -p /usr/local/share/doc/sogo \
  && touch /usr/local/share/doc/sogo/empty.sh \
  && rm -rf /var/lib/apt/lists/* \
  && echo '* * * * *   sogo   /usr/local/sbin/sogo-ealarms-notify 2>/dev/null' > /etc/cron.d/sogo \
  && echo '* * * * *   sogo   /usr/local/sbin/sogo-tool expire-sessions 60' >> /etc/cron.d/sogo \
  && echo '0 0 * * *   sogo   /usr/local/sbin/sogo-tool update-autoreply -p /etc/sogo/sieve.creds' >> /etc/cron.d/sogo \
  && touch /etc/default/locale \
  && echo '/usr/local/lib/sogo' > /etc/ld.so.conf.d/sogo.conf \
  && ldconfig \
  && mkdir -p /var/run/sogo /var/spool/sogo \
  && chown sogo:sogo /var/run/sogo /var/spool/sogo \
  && rm -rf /tmp/* /var/tmp/* /usr/bin/mysql_embedded /usr/bin/mariabackup /tmp/sogo_build \
  && apt-get purge -y libxml2-dev \
  libldap2-dev \
  libssl-dev \
  zlib1g-dev \
  libpq-dev \
  default-libmysqlclient-dev \
  liblasso3-dev \
  libmemcached-dev \
  libgnustep-base-dev \
  libcurl4-openssl-dev \
  devscripts \
  debhelper \
  build-essential \
  autoconf \
  automake \
  autopoint \
  autotools-dev \
  binutils \
  cpp \
  cpp-6 \
  dh-python \
  dpkg-dev \
  g++-6 \
  gcc-6 \
  gobjc-6 \
  groff-base \
  icu-devtools \
  libc-dev-bin \
  libc6-dev \
  libgcc-6-dev \
  libgcrypt20-dev \
  libgmp-dev \
  libgpg-error-dev \
  libhashkit-dev \
  libicu-dev \
  libidn11-dev \
  libnspr4-dev \
  libnss3-dev \
  libobjc-6-dev \
  libp11-kit-dev \
  libpcre3-dev \
  libsasl2-dev \
  libtasn1-6-dev \
  linux-libc-dev \
  nettle-dev

COPY ./bootstrap-sogo.sh /bootstrap-sogo.sh
COPY syslog-ng.conf /etc/syslog-ng/syslog-ng.conf
COPY supervisord.conf /etc/supervisor/supervisord.conf
COPY acl.diff /acl.diff
COPY stop-supervisor.sh /usr/local/sbin/stop-supervisor.sh

RUN chmod +x /bootstrap-sogo.sh \
  /usr/local/sbin/stop-supervisor.sh

CMD exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf

VOLUME /usr/local/lib/GNUstep/SOGo/
