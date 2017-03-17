FROM ubuntu:xenial
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
	ca-certificates

RUN sed -i -E 's/^(\s*)system\(\);/\1unix-stream("\/dev\/log");/' /etc/syslog-ng/syslog-ng.conf

COPY supervisord.conf /etc/supervisor/supervisord.conf
COPY postfix.sh /opt/postfix.sh

EXPOSE 588

CMD exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf

RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*
