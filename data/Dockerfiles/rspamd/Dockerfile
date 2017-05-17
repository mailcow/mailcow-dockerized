FROM debian:jessie-slim
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ARG DEBIAN_FRONTEND=noninteractive
ENV LC_ALL C

RUN apt-key adv --fetch-keys http://rspamd.com/apt-stable/gpg.key \
	&& echo "deb http://rspamd.com/apt-stable/ jessie main" > /etc/apt/sources.list.d/rspamd.list \
	&& apt-get update && apt-get install -y \
		ca-certificates \
		rspamd \
	&& rm -rf /var/lib/apt/lists/*

RUN echo '.include $LOCAL_CONFDIR/local.d/rspamd.conf.local' > /etc/rspamd/rspamd.conf.local

COPY settings.conf /etc/rspamd/modules.d/settings.conf
COPY antivirus.conf /etc/rspamd/modules.d/antivirus.conf
COPY dkim_signing.lua /usr/share/rspamd/lua/dkim_signing.lua

CMD /usr/bin/rspamd -f -u _rspamd -g _rspamd

RUN rm -rf /tmp/* /var/tmp/*

USER _rspamd
