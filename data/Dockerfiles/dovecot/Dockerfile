# Alpine >= 3.22 only ships Dovecot 2.4, Dovecot 2.3 is built from the Alpine 3.21 packaging
FROM alpine:3.24 AS builder

# Last commit of the aports 3.21-stable branch
ARG APORTS_COMMIT=55126a11a4b9dcd14de3068e3348e9951d144ca6

RUN apk add --no-cache alpine-sdk git \
  && git init -q /aports \
  && cd /aports \
  && git remote add origin https://github.com/alpinelinux/aports.git \
  && git sparse-checkout set main/dovecot community/dovecot-fts-flatcurve \
  && git fetch -q --depth 1 --filter=blob:none origin ${APORTS_COMMIT} \
  && git checkout -q FETCH_HEAD \
  && abuild-keygen -a -n \
  && cp /root/.abuild/*.rsa.pub /etc/apk/keys/ \
  && apk update \
  && cd /aports/main/dovecot \
  # newer gettext does not overwrite the shipped config.rpath without -f
  && sed -i 's/autoreconf -vi/autoreconf -fvi/' APKBUILD \
  && abuild -F -r \
  # flatcurve must be built against the Dovecot 2.3 headers
  && apk add --no-cache /root/packages/main/*/dovecot-2*.apk /root/packages/main/*/dovecot-dev-*.apk \
  && cd /aports/community/dovecot-fts-flatcurve \
  && abuild -F -r \
  && mkdir /packages \
  && cp /root/.abuild/*.rsa.pub /packages/ \
  && for pkg in dovecot dovecot-dev dovecot-lmtpd dovecot-lua dovecot-ldap dovecot-mysql dovecot-sql \
    dovecot-submissiond dovecot-pigeonhole-plugin dovecot-pop3d dovecot-fts-flatcurve; do \
    cp /root/packages/*/*/${pkg}-[0-9]*.apk /packages/; \
  done


FROM alpine:3.24

LABEL maintainer="The Infrastructure Company GmbH <info@servercow.de>"

# renovate: datasource=github-releases depName=tianon/gosu versioning=semver-coerced extractVersion=^(?<version>.*)$
ARG GOSU_VERSION=1.20

ENV LANG=C.UTF-8
ENV LC_ALL=C.UTF-8
# MariaDB Connector/C >= 3.4 refuses DB servers without TLS, this covers Perl DBI, client.cnf covers Dovecot
ENV MARIADB_TLS_DISABLE_PEER_VERIFICATION=1

COPY --from=builder /packages /tmp/packages

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
  && cp /tmp/packages/*.rsa.pub /etc/apk/keys/ \
  && apk add --no-cache --update \
  bash \
  bind-tools \
  findutils \
  envsubst \
  ca-certificates \
  curl \
  coreutils \
  jq \
  lua \
  lua-cjson \
  lua-socket \
  lua-sql-mysql \
  lua5.3-sql-mysql \
  icu-data-full \
  mariadb-connector-c \
  lua-sec \
  mariadb-dev \
  glib-dev \
  gcompat \
  mariadb-client \
  perl \
  perl-dev \
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
  libproc2 \
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
  /tmp/packages/*.apk \
  && rm -rf /tmp/packages \
  && arch=$(arch | sed s/aarch64/arm64/ | sed s/x86_64/amd64/) \
  && wget -O /usr/local/bin/gosu "https://github.com/tianon/gosu/releases/download/$GOSU_VERSION/gosu-$arch" \
  && chmod +x /usr/local/bin/gosu \
  && gosu nobody true

# Dovecot's MySQL driver reads [client] from /etc/my.cnf, Connector/C ignores the !includedir there
COPY client.cnf /etc/my.cnf
COPY trim_logs.sh /usr/local/bin/trim_logs.sh
COPY clean_q_aged.sh /usr/local/bin/clean_q_aged.sh
COPY syslog-ng.conf /etc/syslog-ng/syslog-ng.conf
COPY syslog-ng-redis_slave.conf /etc/syslog-ng/syslog-ng-redis_slave.conf
COPY imapsync /usr/local/bin/imapsync
COPY imapsync_runner.py /usr/local/bin/imapsync_runner.py
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
COPY optimize-fts.sh /usr/local/bin/optimize-fts.sh

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
