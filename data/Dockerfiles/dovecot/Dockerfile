FROM debian:stretch-slim
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ARG DEBIAN_FRONTEND=noninteractive
ENV LC_ALL C
ENV DOVECOT_VERSION 2.3.1
ENV PIGEONHOLE_VERSION 0.5.1

RUN apt-get update && apt-get -y --no-install-recommends install \
  automake \
  autotools-dev \
  build-essential \
  ca-certificates \
  cpanminus \
  curl \
  default-libmysqlclient-dev \
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
  libunicode-string-perl \
  libproc-processtable-perl \
  liburi-perl \
  lzma-dev \
  make \
  procps \
  supervisor \
  cron \
  syslog-ng \
  syslog-ng-core \
  syslog-ng-mod-redis \
  && rm -rf /var/lib/apt/lists/*


RUN curl https://www.dovecot.org/releases/2.3/dovecot-$DOVECOT_VERSION.tar.gz | tar xvz  \
  && cd dovecot-$DOVECOT_VERSION \
  && ./configure --with-solr --with-mysql --with-lzma --with-lz4 --with-ssl=openssl --with-notify=inotify --with-storages=mdbox,sdbox,maildir,mbox,imapc,pop3c --with-bzlib --with-zlib \
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
  && rm -rf dovecot-2.3-pigeonhole-$PIGEONHOLE_VERSION

RUN cpanm Data::Uniqid Mail::IMAPClient String::Util
RUN echo '* * * * *   root   /usr/local/bin/imapsync_cron.pl' > /etc/cron.d/imapsync
RUN echo '30 3 * * *   vmail  /usr/local/bin/doveadm quota recalc -A' > /etc/cron.d/dovecot-sync

COPY syslog-ng.conf /etc/syslog-ng/syslog-ng.conf
COPY imapsync /usr/local/bin/imapsync
COPY postlogin.sh /usr/local/bin/postlogin.sh
COPY imapsync_cron.pl /usr/local/bin/imapsync_cron.pl
COPY report-spam.sieve /usr/local/lib/dovecot/sieve/report-spam.sieve
COPY report-ham.sieve /usr/local/lib/dovecot/sieve/report-ham.sieve
COPY rspamd-pipe-ham /usr/local/lib/dovecot/sieve/rspamd-pipe-ham
COPY rspamd-pipe-spam /usr/local/lib/dovecot/sieve/rspamd-pipe-spam
COPY docker-entrypoint.sh /
COPY supervisord.conf /etc/supervisor/supervisord.conf

RUN chmod +x /usr/local/lib/dovecot/sieve/rspamd-pipe-ham \
  /usr/local/lib/dovecot/sieve/rspamd-pipe-spam \
  /usr/local/bin/imapsync_cron.pl \
  /usr/local/bin/postlogin.sh \
  /usr/local/bin/imapsync

RUN groupadd -g 5000 vmail \
  && groupadd -g 401 dovecot \
  && groupadd -g 402 dovenull \
  && useradd -g vmail -u 5000 vmail -d /var/vmail \
  && useradd -c "Dovecot unprivileged user" -d /dev/null -u 401 -g dovecot -s /bin/false dovecot \
  && useradd -c "Dovecot login user" -d /dev/null -u 402 -g dovenull -s /bin/false dovenull

RUN touch /etc/default/locale
RUN apt-get purge -y build-essential automake autotools-dev \
  && apt-get autoremove --purge -y

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf

RUN rm -rf \
  /tmp/* \
  /var/tmp/*

