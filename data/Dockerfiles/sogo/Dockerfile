FROM debian:stretch-slim
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ARG DEBIAN_FRONTEND=noninteractive
ENV LC_ALL C
ENV GOSU_VERSION 1.9

# Prerequisites
RUN apt-get update && apt-get install -y --no-install-recommends \
		apt-transport-https \
		ca-certificates \
		cron \
		gnupg \
		mysql-client \
		supervisor \
		syslog-ng \
		syslog-ng-core \
		syslog-ng-mod-redis \
		dirmngr \
		netcat \
		psmisc \
		wget \
	&& rm -rf /var/lib/apt/lists/* \
	&& dpkgArch="$(dpkg --print-architecture | awk -F- '{ print $NF }')" \
	&& wget -O /usr/local/bin/gosu "https://github.com/tianon/gosu/releases/download/$GOSU_VERSION/gosu-$dpkgArch" \
	&& chmod +x /usr/local/bin/gosu \
	&& gosu nobody true

RUN mkdir /usr/share/doc/sogo \
	&& touch /usr/share/doc/sogo/empty.sh \
	&& apt-key adv --keyserver keyserver.ubuntu.com --recv-key 0x810273C4 \
	&& echo "deb http://packages.inverse.ca/SOGo/nightly/4/debian/ stretch stretch" > /etc/apt/sources.list.d/sogo.list \
	&& apt-get update && apt-get install -y --force-yes \
		sogo \
		sogo-activesync \
	&& rm -rf /var/lib/apt/lists/* \
	&& echo '* * * * *   sogo   /usr/sbin/sogo-ealarms-notify 2>/dev/null' > /etc/cron.d/sogo \
	&& echo '* * * * *   sogo   /usr/sbin/sogo-tool expire-sessions 60' >> /etc/cron.d/sogo \
	&& echo '0 0 * * *   sogo   /usr/sbin/sogo-tool update-autoreply -p /etc/sogo/sieve.creds' >> /etc/cron.d/sogo \
	&& touch /etc/default/locale

COPY ./bootstrap-sogo.sh /
COPY syslog-ng.conf /etc/syslog-ng/syslog-ng.conf
COPY supervisord.conf /etc/supervisor/supervisord.conf
COPY theme-blue.js /usr/lib/GNUstep/SOGo/WebServerResources/js/theme-blue.js
COPY theme-green.js /usr/lib/GNUstep/SOGo/WebServerResources/js/theme-green.js
COPY sogo-full.svg /usr/lib/GNUstep/SOGo/WebServerResources/img/sogo-full.svg

CMD exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf

RUN rm -rf /tmp/* /var/tmp/*
