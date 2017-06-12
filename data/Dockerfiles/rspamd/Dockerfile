FROM debian:jessie-slim
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ARG DEBIAN_FRONTEND=noninteractive
ENV LC_ALL C

RUN apt-key adv --fetch-keys http://rspamd.com/apt-stable/gpg.key \
	&& echo "deb http://rspamd.com/apt-stable/ jessie main" > /etc/apt/sources.list.d/rspamd.list \
	&& apt-get update && apt-get install -y \
		ca-certificates \
		rspamd \
	&& rm -rf /var/lib/apt/lists/* \
	&& echo '.include $LOCAL_CONFDIR/local.d/rspamd.conf.local' > /etc/rspamd/rspamd.conf.local \
	&& apt-get autoremove --purge \
	&& apt-get clean \
	&& mkdir -p /run/rspamd \
	&& chown _rspamd:_rspamd /run/rspamd

COPY settings.conf /etc/rspamd/modules.d/settings.conf

CMD /usr/bin/rspamd -f -u _rspamd -g _rspamd

USER _rspamd
