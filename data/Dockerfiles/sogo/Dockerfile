FROM debian:jessie-slim
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ENV DEBIAN_FRONTEND noninteractive
ENV LC_ALL C
ENV GOSU_VERSION 1.9

RUN apt-get update && apt-get install -y --no-install-recommends \
		apt-transport-https \
		gnupg \
		ca-certificates \
		wget \
		syslog-ng \
		syslog-ng-core \
		supervisor \
		mysql-client \
		cron \
	&& rm -rf /var/lib/apt/lists/* \
	&& dpkgArch="$(dpkg --print-architecture | awk -F- '{ print $NF }')" \
	&& wget -O /usr/local/bin/gosu "https://github.com/tianon/gosu/releases/download/$GOSU_VERSION/gosu-$dpkgArch" \
	&& wget -O /usr/local/bin/gosu.asc "https://github.com/tianon/gosu/releases/download/$GOSU_VERSION/gosu-$dpkgArch.asc" \
	&& export GNUPGHOME="$(mktemp -d)" \
	&& gpg --keyserver ha.pool.sks-keyservers.net --recv-keys B42F6819007F00F88E364FD4036A9C25BF357DD4 \
	&& gpg --batch --verify /usr/local/bin/gosu.asc /usr/local/bin/gosu \
	&& rm -r "$GNUPGHOME" /usr/local/bin/gosu.asc \
	&& chmod +x /usr/local/bin/gosu \
	&& gosu nobody true

RUN mkdir /usr/share/doc/sogo
RUN touch /usr/share/doc/sogo/empty.sh

RUN apt-key adv --keyserver keys.gnupg.net --recv-key 0x810273C4 \
	&& echo "deb http://packages.inverse.ca/SOGo/nightly/3/debian/ jessie jessie" > /etc/apt/sources.list.d/sogo.list \
	&& apt-get update && apt-get install -y --force-yes \
		sogo \
		sogo-activesync \
	&& rm -rf /var/lib/apt/lists/*

RUN sed -i -E 's/^(\s*)system\(\);/\1unix-stream("\/dev\/log");/' /etc/syslog-ng/syslog-ng.conf
RUN echo '* * * * *   sogo   /usr/sbin/sogo-ealarms-notify' > /etc/cron.d/sogo
RUN echo '* * * * *   sogo   /usr/sbin/sogo-tool expire-sessions 60' >> /etc/cron.d/sogo
RUN echo '0 0 * * *   sogo   /usr/sbin/sogo-tool update-autoreply -p /etc/sogo/sieve.creds' >> /etc/cron.d/sogo

COPY ./reconf-domains.sh /
COPY supervisord.conf /etc/supervisor/supervisord.conf

CMD exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf

RUN rm -rf /tmp/* /var/tmp/*
