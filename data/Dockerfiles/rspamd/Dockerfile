FROM debian:jessie-slim
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ARG DEBIAN_FRONTEND=noninteractive
ENV LC_ALL C

RUN apt-key adv --fetch-keys http://rspamd.com/apt/gpg.key \
	&& echo "deb http://rspamd.com/apt/ jessie main" > /etc/apt/sources.list.d/rspamd.list \
	&& apt-get update && apt-get install -y \
		ca-certificates \
		rspamd \
	&& rm -rf /var/lib/apt/lists/*

RUN echo '.include $LOCAL_CONFDIR/local.d/rspamd.conf.local' > /etc/rspamd/rspamd.conf.local

COPY settings.conf /etc/rspamd/modules.d/settings.conf
COPY antivirus.conf /etc/rspamd/modules.d/antivirus.conf
COPY milter_headers.lua /usr/share/rspamd/lua/milter_headers.lua

RUN apt-get autoremove --purge \
	&& apt-get clean

CMD /usr/bin/rspamd -f -u _rspamd -g _rspamd

RUN rm -rf /tmp/* /var/tmp/*

USER _rspamd
