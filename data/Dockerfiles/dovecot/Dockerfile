FROM alpine:3.19
LABEL maintainer "The Infrastructure Company GmbH <info@servercow.de>"

# renovate: datasource=github-releases depName=tianon/gosu versioning=semver-coerced extractVersion=^(?<version>.*)$
ARG GOSU_VERSION=1.16

ENV LANG C.UTF-8
ENV LC_ALL C.UTF-8

# Add groups and users before installing Dovecot to not break compatibility
RUN addgroup -g 5000 vmail \
  && addgroup -g 401 dovecot \
  && addgroup -g 402 dovenull \
  && sed -i "s/999/99/" /etc/group \
  && addgroup -g 999 sogo \
  && addgroup nobody sogo \
  && adduser -D -u 5000 -G vmail -h /var/vmail vmail \
  && adduser -D -G dovecot -u 401 -h /dev/null -s /sbin/nologin dovecot \
  && adduser -D -G dovenull -u 402 -h /dev/null -s /sbin/nologin dovenull \
  && apk add --no-cache --update \
  bash \
  bind-tools \
  findutils \
  envsubst \
  ca-certificates \
  curl \
  jq \
  lua \
  lua-cjson \
  lua-socket \
  lua-sql-mysql \
  lua5.3-sql-mysql \
  icu-data-full \
  mariadb-connector-c \
  gcompat \
  mariadb-client \
  perl \
  perl-ntlm \
  perl-cgi \
  perl-crypt-openssl-rsa \
  perl-utils \
  perl-crypt-ssleay \
  perl-data-uniqid \
  perl-dbd-mysql \
  perl-dbi \
  perl-digest-hmac \
  perl-dist-checkconflicts \
  perl-encode-imaputf7 \
  perl-file-copy-recursive \
  perl-file-tail \
  perl-io-socket-inet6 \
  perl-io-gzip \
  perl-io-socket-ssl \
  perl-io-tee \
  perl-ipc-run \
  perl-json-webtoken \
  perl-mail-imapclient \
  perl-module-implementation \
  perl-module-scandeps \
  perl-net-ssleay \
  perl-package-stash \
  perl-package-stash-xs \
  perl-par-packer \
  perl-parse-recdescent \
  perl-lockfile-simple --repository=http://dl-cdn.alpinelinux.org/alpine/edge/community/ \
  libproc \
  perl-readonly \
  perl-regexp-common \
  perl-sys-meminfo \
  perl-term-readkey \
  perl-test-deep \
  perl-test-fatal \
  perl-test-mockobject \
  perl-test-mock-guard \
  perl-test-pod \
  perl-test-requires \
  perl-test-simple \
  perl-test-warn \
  perl-try-tiny \
  perl-unicode-string \
  perl-proc-processtable \
  perl-app-cpanminus \
  procps \
  python3 \
  py3-mysqlclient \
  py3-html2text \
  py3-jinja2 \
  py3-redis \
  redis \
  syslog-ng \
  syslog-ng-redis \
  syslog-ng-json \
  supervisor \
  tzdata \
  wget \
  dovecot \
  dovecot-dev \
  dovecot-lmtpd \
  dovecot-lua \
  dovecot-ldap \
  dovecot-mysql \
  dovecot-sql \
  dovecot-submissiond \
  dovecot-pigeonhole-plugin \
  dovecot-pop3d \
  dovecot-fts-solr \
  && arch=$(arch | sed s/aarch64/arm64/ | sed s/x86_64/amd64/) \
  && wget -O /usr/local/bin/gosu "https://github.com/tianon/gosu/releases/download/$GOSU_VERSION/gosu-$arch" \
  && chmod +x /usr/local/bin/gosu \
  && gosu nobody true

# RUN cpan LockFile::Simple

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
