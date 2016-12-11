FROM debian:jessie
MAINTAINER Andre Peters <andre.peters@debinux.de>

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update \
	&& apt-get install -y wget \
	&& wget -O- https://rspamd.com/apt-stable/gpg.key | apt-key add - \
	&& echo "deb http://rspamd.com/apt-stable/ jessie main" > /etc/apt/sources.list.d/rspamd.list \
	&& echo "deb-src http://rspamd.com/apt-stable/ jessie main" >> /etc/apt/sources.list.d/rspamd.list \
	&& apt-get update \
	&& apt-get --no-install-recommends -y --force-yes install rspamd

RUN echo '.include $LOCAL_CONFDIR/local.d/rspamd.conf.local' > /etc/rspamd/rspamd.conf.local

CMD ["/usr/bin/rspamd","-f", "-u", "_rspamd", "-g", "_rspamd"]

USER _rspamd

EXPOSE 11333 11334
