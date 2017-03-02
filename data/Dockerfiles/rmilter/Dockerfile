FROM ubuntu:xenial
MAINTAINER Andre Peters <andre.peters@servercow.de>

ENV DEBIAN_FRONTEND noninteractive
ENV LC_ALL C

RUN dpkg-divert --local --rename --add /sbin/initctl \
    && ln -sf /bin/true /sbin/initctl \
    && dpkg-divert --local --rename --add /usr/bin/ischroot \
    && ln -sf /bin/true /usr/bin/ischroot

RUN apt-key adv --fetch-keys http://rspamd.com/apt-stable/gpg.key \
	&& echo "deb http://rspamd.com/apt-stable/ xenial main" > /etc/apt/sources.list.d/rspamd.list \
	&& apt-get update \
	&& apt-get --no-install-recommends -y --force-yes install rmilter cron syslog-ng syslog-ng-core supervisor

COPY supervisord.conf /etc/supervisor/supervisord.conf

EXPOSE 9000

RUN sed -i -E 's/^(\s*)system\(\);/\1unix-stream("\/dev\/log");/' /etc/syslog-ng/syslog-ng.conf
RUN touch /var/log/mail.log && chmod 640 /var/log/mail.log && chown root:adm /var/log/mail.log

CMD exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf

RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*
