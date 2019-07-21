FROM debian:stretch-slim
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ARG DEBIAN_FRONTEND=noninteractive
ENV LC_ALL C
ENV GOSU_VERSION 1.11

# Prerequisites
RUN apt-get update && apt-get install -y --no-install-recommends \
  apt-transport-https \
  ca-certificates \
  cron \
  gettext \
  gnupg \
  mysql-client \
  rsync \
  supervisor \
  syslog-ng \
  syslog-ng-core \
  syslog-ng-mod-redis \
  dirmngr \
  netcat \
  psmisc \
  wget \
  patch \
  && dpkgArch="$(dpkg --print-architecture | awk -F- '{ print $NF }')" \
  && wget -O /usr/local/bin/gosu "https://github.com/tianon/gosu/releases/download/$GOSU_VERSION/gosu-$dpkgArch" \
  && chmod +x /usr/local/bin/gosu \
  && gosu nobody true \
  && mkdir /usr/share/doc/sogo \
  && touch /usr/share/doc/sogo/empty.sh \
  && apt-key adv --keyserver keyserver.ubuntu.com --recv-key 0x810273C4 \
  && echo "deb http://packages.inverse.ca/SOGo/nightly/4/debian/ stretch stretch" > /etc/apt/sources.list.d/sogo.list \
  && apt-get update && apt-get install -y --no-install-recommends \
    sogo \
    sogo-activesync \
  && apt-get autoclean \
  && rm -rf /var/lib/apt/lists/* \
  && touch /etc/default/locale

COPY ./bootstrap-sogo.sh /bootstrap-sogo.sh
COPY syslog-ng.conf /etc/syslog-ng/syslog-ng.conf
COPY supervisord.conf /etc/supervisor/supervisord.conf
COPY acl.diff /acl.diff
COPY stop-supervisor.sh /usr/local/sbin/stop-supervisor.sh

RUN chmod +x /bootstrap-sogo.sh \
  /usr/local/sbin/stop-supervisor.sh

CMD exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
