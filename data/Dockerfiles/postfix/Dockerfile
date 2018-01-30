FROM debian:stretch-slim
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ARG DEBIAN_FRONTEND=noninteractive
ENV LC_ALL C

RUN dpkg-divert --local --rename --add /sbin/initctl \
	&& ln -sf /bin/true /sbin/initctl \
	&& dpkg-divert --local --rename --add /usr/bin/ischroot \
	&& ln -sf /bin/true /usr/bin/ischroot

RUN apt-get update && apt-get install -y --no-install-recommends \
	ca-certificates \
	curl \
	dirmngr \
	gnupg \
	libsasl2-modules \
	postfix \
	postfix-mysql \
	postfix-pcre \
	python-gpgme \
	sasl2-bin \
	sudo \
	supervisor \
	syslog-ng \
	syslog-ng-core \
	syslog-ng-mod-redis \
	&& rm -rf /var/lib/apt/lists/* \
	&& touch /etc/default/locale \
  && printf '#!/bin/bash\n/usr/sbin/postconf -c /opt/postfix/conf "$@"' > /usr/local/sbin/postconf \
  && chmod +x /usr/local/sbin/postconf

RUN addgroup --system --gid 600 zeyple
RUN adduser --system --home /var/lib/zeyple --no-create-home --uid 600 --gid 600 --disabled-login zeyple
RUN touch /var/log/zeyple.log && chown zeyple: /var/log/zeyple.log

COPY zeyple.py /usr/local/bin/zeyple.py
COPY zeyple.conf /etc/zeyple.conf
COPY supervisord.conf /etc/supervisor/supervisord.conf
COPY syslog-ng.conf /etc/syslog-ng/syslog-ng.conf
COPY postfix.sh /opt/postfix.sh
COPY whitelist_forwardinghosts.sh /usr/local/bin/whitelist_forwardinghosts.sh

EXPOSE 588

CMD exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf

RUN rm -rf /tmp/* /var/tmp/*
