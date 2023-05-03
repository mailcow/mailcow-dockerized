FROM debian:bullseye-slim as build
LABEL maintainer "The Infrastructure Company <info@servercow.de>"

ARG DEBIAN_FRONTEND=noninteractive
ARG GOSU_VERSION=1.16
ARG DOVECOT_VERSION=2.3.20
ARG PIGEONHOLE_VERSION=0.5.20
ENV LC_ALL C


# Add groups and users before installing Dovecot to not break compatibility
RUN touch /etc/default/locale \
  && apt-get update \
  && apt-get -y --no-install-recommends install \
  apt-transport-https \
  ca-certificates \
  curl \
  dirmngr \
  gettext \
  gnupg2 \
  jq \
  libauthen-ntlm-perl \
  libcgi-pm-perl \
  libcrypt-openssl-rsa-perl \
  libcrypt-ssleay-perl \
  libdata-uniqid-perl \
  libdbd-mysql-perl \
  libdbi-perl \
  libdigest-hmac-perl \
  libdist-checkconflicts-perl \
  libencode-imaputf7-perl \
  libfile-copy-recursive-perl \
  libfile-tail-perl \
  libhtml-parser-perl \
  libio-compress-perl \
  libio-socket-inet6-perl \
  libio-socket-ssl-perl \
  libio-tee-perl \
  libipc-run-perl \
  libjson-webtoken-perl \
  liblockfile-simple-perl \
  libmail-imapclient-perl \
  libmodule-implementation-perl \
  libmodule-scandeps-perl \
  libnet-ssleay-perl \
  libpackage-stash-perl \
  libpackage-stash-xs-perl \
  libpar-packer-perl \
  libparse-recdescent-perl \
  libproc-processtable-perl \
  libreadonly-perl \
  libregexp-common-perl \
  libsys-meminfo-perl \
  libterm-readkey-perl \
  libtest-deep-perl \
  libtest-fatal-perl \
  libtest-mock-guard-perl \
  libtest-mockobject-perl \
  libtest-nowarnings-perl \
  libtest-pod-perl \
  libtest-requires-perl \
  libtest-simple-perl \
  libtest-warn-perl \
  libtry-tiny-perl \
  libunicode-string-perl \
  liburi-perl \
  libwww-perl \
  libstemmer-dev \
  libexttextcat-dev \
  libldap-dev \
  libghc-bzlib-dev \
  lua-sql-mysql \
  liblz4-dev \
  libzstd-dev \
  libexpat-dev \
  lua-socket \
  mariadb-client \
  procps \
  wget \
  git \
  bison \
  flex \
  build-essential \
  autoconf \
  automake \
  libtool \
  make \
  default-libmysqlclient-dev \
  libicu-dev \
  zlib1g-dev \
  pkg-config \
  libsqlite3-dev \
  liblua5.3-dev \
  && dpkgArch="$(dpkg --print-architecture | awk -F- '{ print $NF }')" \
  && wget -O /usr/local/bin/gosu "https://github.com/tianon/gosu/releases/download/$GOSU_VERSION/gosu-$dpkgArch" \
  && chmod +x /usr/local/bin/gosu \
  && gosu nobody true

RUN cd /tmp && wget https://github.com/dovecot/core/archive/refs/tags/${DOVECOT_VERSION}.tar.gz && tar -xzf ${DOVECOT_VERSION}.tar.gz && cd core-${DOVECOT_VERSION} \
  && ./autogen.sh \
  && PANDOC=false ./configure --prefix=/usr --sysconfdir=/etc --localstatedir=/var --with-ssldir=/etc/ssl --enable-maintainer-mode --with-sql=yes --with-lua=yes --with-mysql --with-ldap --with-solr --with-zstd --with-lz4 --with-ssl=openssl --with-notify=inotify --with-bzlib --with-zlib --enable-hardening --with-stemmer --with-textcat --with-icu \
  && make -j6 \
  && make install \
  && make clean

RUN cd /tmp && wget https://github.com/dovecot/pigeonhole/archive/refs/tags/${PIGEONHOLE_VERSION}.tar.gz && tar -xzf ${PIGEONHOLE_VERSION}.tar.gz && cd pigeonhole-${PIGEONHOLE_VERSION} \
  && ./autogen.sh \
  && ./configure --with-dovecot=/usr/lib/dovecot --with-managesieve\ 
  && make -j6 \
  && make install \
  && make clean

FROM debian:bullseye-slim
RUN groupadd -g 5000 vmail \
  && groupadd -g 401 dovecot \
  && groupadd -g 402 dovenull \
  && groupadd -g 999 sogo \
  && usermod -a -G sogo nobody \
  && useradd -g vmail -u 5000 vmail -d /var/vmail \
  && useradd -c "Dovecot unprivileged user" -d /dev/null -u 401 -g dovecot -s /bin/false dovecot \
  && useradd -c "Dovecot login user" -d /dev/null -u 402 -g dovenull -s /bin/false dovenull \
  && apt update && apt install lua-socket \
  mariadb-client \
  libstemmer-dev \
  libexttextcat-dev \
  libicu-dev \
  libsqlite3-dev \
  liblua5.3-dev \
  lua-sql-mysql \
  libldap-dev \
  libssl-dev \
  procps \
  python3-pip \
  redis-server \
  supervisor \
  syslog-ng \
  syslog-ng-core \
  syslog-ng-mod-redis \
  cpanminus \
  curl \
  libauthen-ntlm-perl \
  libcgi-pm-perl \
  libcrypt-openssl-rsa-perl \
  libcrypt-ssleay-perl \
  libdata-uniqid-perl \
  libdbd-mysql-perl \
  libdbi-perl \
  libdigest-hmac-perl \
  libdist-checkconflicts-perl \
  libencode-imaputf7-perl \
  libfile-copy-recursive-perl \
  libfile-tail-perl \
  libhtml-parser-perl \
  libio-compress-perl \
  libio-socket-inet6-perl \
  libio-socket-ssl-perl \
  libio-tee-perl \
  libipc-run-perl \
  libjson-webtoken-perl \
  liblockfile-simple-perl \
  libmail-imapclient-perl \
  libmodule-implementation-perl \
  libmodule-scandeps-perl \
  libnet-ssleay-perl \
  libpackage-stash-perl \
  libpackage-stash-xs-perl \
  libpar-packer-perl \
  libparse-recdescent-perl \
  libproc-processtable-perl \
  libreadonly-perl \
  libregexp-common-perl \
  libsys-meminfo-perl \
  libterm-readkey-perl \
  libtest-deep-perl \
  libtest-fatal-perl \
  libtest-mock-guard-perl \
  libtest-mockobject-perl \
  libtest-nowarnings-perl \
  libtest-pod-perl \
  libtest-requires-perl \
  libtest-simple-perl \
  libtest-warn-perl \
  libtry-tiny-perl \
  libunicode-string-perl \
  liburi-perl \
  libwww-perl \
  dnsutils \
  build-essential \
  gettext-base -y --no-install-recommends \
  && pip3 install mysql-connector-python html2text jinja2 redis
# imapsync dependencies
RUN cpan Crypt::OpenSSL::PKCS12
COPY --from=build /usr/lib/dovecot /usr/lib/dovecot
COPY --from=build /usr/bin/doveconf /usr/bin/doveconf
COPY --from=build /usr/bin/doveadm /usr/bin/doveadm
COPY --from=build /usr/bin/dovecot-sysreport /usr/bin/dovecot-sysreport
COPY --from=build /usr/sbin/dovecot /usr/sbin/dovecot
COPY --from=build /usr/libexec/dovecot/ /usr/libexec/dovecot/
COPY --from=build /usr/local/bin /usr/local/bin
COPY trim_logs.sh /usr/local/bin/trim_logs.sh
COPY clean_q_aged.sh /usr/local/bin/clean_q_aged.sh
COPY syslog-ng.conf /etc/syslog-ng/syslog-ng.conf
COPY syslog-ng-redis_slave.conf /etc/syslog-ng/syslog-ng-redis_slave.conf
COPY imapsync /usr/local/bin/imapsync
COPY imapsync_runner.pl /usr/local/bin/imapsync_runner.pl
COPY report-spam.sieve /usr/lib/dovecot/sieve/report-spam.sieve
COPY report-ham.sieve /usr/lib/dovecot/sieve/report-ham.sieve
COPY rspamd-pipe-ham /usr/lib/dovecot/sieve/rspamd-pipe-ham
COPY rspamd-pipe-spam /usr/lib/dovecot/sieve/rspamd-pipe-spam
COPY sa-rules.sh /usr/local/bin/sa-rules.sh
COPY maildir_gc.sh /usr/local/bin/maildir_gc.sh
COPY docker-entrypoint.sh /
COPY supervisord.conf /etc/supervisor/supervisord.conf
COPY stop-supervisor.sh /usr/local/sbin/stop-supervisor.sh
COPY quarantine_notify.py /usr/local/bin/quarantine_notify.py
COPY quota_notify.py /usr/local/bin/quota_notify.py
COPY repl_health.sh /usr/local/bin/repl_health.sh

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf