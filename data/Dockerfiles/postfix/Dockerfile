FROM debian:stretch-slim
MAINTAINER Andre Peters <andre.peters@servercow.de>

ENV DEBIAN_FRONTEND noninteractive
ENV LC_ALL C

RUN dpkg-divert --local --rename --add /sbin/initctl \
	&& ln -sf /bin/true /sbin/initctl \
	&& dpkg-divert --local --rename --add /usr/bin/ischroot \
	&& ln -sf /bin/true /usr/bin/ischroot

RUN apt-get update
RUN apt-get install -y --no-install-recommends supervisor \
	postfix \
	sasl2-bin \
	libsasl2-modules \
	postfix \
	postfix-mysql \
	postfix-pcre \
	syslog-ng \
	syslog-ng-core \
	syslog-ng-mod-redis \
	ca-certificates \
	gnupg \
	python-gpgme \
	sudo \
	curl \
	dirmngr

RUN addgroup --system --gid 600 zeyple
RUN adduser --system --home /var/lib/zeyple --no-create-home --uid 600 --gid 600 --disabled-login zeyple
RUN touch /var/log/zeyple.log && chown zeyple: /var/log/zeyple.log

RUN touch /etc/default/locale

COPY zeyple.py /usr/local/bin/zeyple.py
COPY zeyple.conf /etc/zeyple.conf
COPY supervisord.conf /etc/supervisor/supervisord.conf
COPY syslog-ng.conf /etc/syslog-ng/syslog-ng.conf
COPY postfix.sh /opt/postfix.sh
COPY whitelist_forwardinghosts.sh /usr/local/bin/whitelist_forwardinghosts.sh

EXPOSE 588

CMD exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf

RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*
