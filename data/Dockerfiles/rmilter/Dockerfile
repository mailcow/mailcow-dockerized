FROM debian:jessie
MAINTAINER Andre Peters <andre.peters@servercow.de>

RUN apt-get update \
	&& apt-get install -y wget \
	&& wget -O- https://rspamd.com/apt-stable/gpg.key | apt-key add - \
	&& echo "deb http://rspamd.com/apt-stable/ jessie main" > /etc/apt/sources.list.d/rspamd.list \
	&& echo "deb-src http://rspamd.com/apt-stable/ jessie main" >> /etc/apt/sources.list.d/rspamd.list \
	&& apt-get update \
	&& apt-get --no-install-recommends -y --force-yes install rmilter

CMD ["/usr/sbin/rmilter","-n", "-c", "/etc/rmilter.conf.d/rmilter.conf"]

USER _rmilter

EXPOSE 9000
