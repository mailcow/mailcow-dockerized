FROM debian:stretch-slim
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ARG DEBIAN_FRONTEND=noninteractive
ENV LC_ALL C
ENV DOVECOT_VERSION 2.3.4
ENV PIGEONHOLE_VERSION 0.5.4

RUN apt-get update && apt-get -y --no-install-recommends install \
  automake \
  autotools-dev \
  build-essential \
  ca-certificates \
  cpanminus \
  curl \
  default-libmysqlclient-dev \
  dnsutils \
  gettext \
  jq \
  libjson-webtoken-perl \
  libcgi-pm-perl \
  libcrypt-openssl-rsa-perl \
  libdata-uniqid-perl \
  libhtml-parser-perl \
  libmail-imapclient-perl \
  libparse-recdescent-perl \
  libsys-meminfo-perl \
  libtest-mockobject-perl \
  libwww-perl \
  libauthen-ntlm-perl \
  libbz2-dev \
  libcrypt-ssleay-perl \
  libcurl4-openssl-dev \
  libdbd-mysql-perl \
  libdbi-perl \
  libdigest-hmac-perl \
  libexpat1-dev \
  libfile-copy-recursive-perl \
  libio-compress-perl \
  libio-socket-inet6-perl \
  libio-socket-ssl-perl \
  libio-tee-perl \
  libipc-run-perl \
  libldap2-dev \
  liblockfile-simple-perl \
  liblz-dev \
  liblz4-dev \
  liblzma-dev \
  libmodule-scandeps-perl \
  libnet-ssleay-perl \
  libpam-dev \
  libpar-packer-perl \
  libreadonly-perl \
  libssl-dev \
  libterm-readkey-perl \
  libtest-pod-perl \
  libtest-simple-perl \
  libtry-tiny-perl \
  libunicode-string-perl \
  libproc-processtable-perl \
  libtest-nowarnings-perl \
  libtest-deep-perl \
  libtest-warn-perl \
  libregexp-common-perl \
  liburi-perl \
  lzma-dev \
  python-html2text \
  python-jinja2 \
  python-mysql.connector \
  python-redis \
  make \
  mysql-client \
  procps \
  supervisor \
  cron \
  redis-server \
  syslog-ng \
  syslog-ng-core \
  syslog-ng-mod-redis \
  && rm -rf /var/lib/apt/lists/* \
  && curl https://www.dovecot.org/releases/2.3/dovecot-$DOVECOT_VERSION.tar.gz | tar xvz  \
  && cd dovecot-$DOVECOT_VERSION \
  && ./configure --with-solr --with-mysql --with-ldap --with-lzma --with-lz4 --with-ssl=openssl --with-notify=inotify --with-storages=mdbox,sdbox,maildir,mbox,imapc,pop3c --with-bzlib --with-zlib --enable-hardening \
  && make -j3 \
  && make install \
  && make clean \
  && cd .. && rm -rf dovecot-$DOVECOT_VERSION \
  && curl https://pigeonhole.dovecot.org/releases/2.3/dovecot-2.3-pigeonhole-$PIGEONHOLE_VERSION.tar.gz | tar xvz  \
  && cd dovecot-2.3-pigeonhole-$PIGEONHOLE_VERSION \
  && ./configure \
  && make -j3 \
  && make install \
  && make clean \
  && cd .. \
  && rm -rf dovecot-2.3-pigeonhole-$PIGEONHOLE_VERSION \
  && cpanm Data::Uniqid Mail::IMAPClient String::Util \
  && groupadd -g 5000 vmail \
  && groupadd -g 401 dovecot \
  && groupadd -g 402 dovenull \
  && useradd -g vmail -u 5000 vmail -d /var/vmail \
  && useradd -c "Dovecot unprivileged user" -d /dev/null -u 401 -g dovecot -s /bin/false dovecot \
  && useradd -c "Dovecot login user" -d /dev/null -u 402 -g dovenull -s /bin/false dovenull \
  && touch /etc/default/locale \
  && apt-get purge -y build-essential automake autotools-dev default-libmysqlclient-dev libbz2-dev libcurl4-openssl-dev libexpat1-dev liblz-dev liblz4-dev liblzma-dev libpam-dev libssl-dev lzma-dev \
  && apt-get autoremove --purge -y \
  && rm -rf /tmp/* /var/tmp/*

COPY trim_logs.sh /usr/local/bin/trim_logs.sh
COPY syslog-ng.conf /etc/syslog-ng/syslog-ng.conf
COPY imapsync /usr/local/bin/imapsync
COPY postlogin.sh /usr/local/bin/postlogin.sh
COPY imapsync_cron.pl /usr/local/bin/imapsync_cron.pl
COPY report-spam.sieve /usr/local/lib/dovecot/sieve/report-spam.sieve
COPY report-ham.sieve /usr/local/lib/dovecot/sieve/report-ham.sieve
COPY rspamd-pipe-ham /usr/local/lib/dovecot/sieve/rspamd-pipe-ham
COPY rspamd-pipe-spam /usr/local/lib/dovecot/sieve/rspamd-pipe-spam
COPY sa-rules.sh /usr/local/bin/sa-rules.sh
COPY maildir_gc.sh /usr/local/bin/maildir_gc.sh
COPY docker-entrypoint.sh /
COPY supervisord.conf /etc/supervisor/supervisord.conf
COPY stop-supervisor.sh /usr/local/sbin/stop-supervisor.sh
COPY quarantine_notify.py /usr/local/bin/quarantine_notify.py
COPY quota_notify.py /usr/local/bin/quota_notify.py

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
