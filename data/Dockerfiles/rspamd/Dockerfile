FROM ubuntu:bionic
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ARG DEBIAN_FRONTEND=noninteractive
ENV LC_ALL C

RUN apt-get update && apt-get install -y \
  tzdata \
	ca-certificates \
	gnupg2 \
	apt-transport-https \
	&& apt-key adv --fetch-keys https://rspamd.com/apt/gpg.key \
	&& echo "deb https://rspamd.com/apt-stable/ bionic main" > /etc/apt/sources.list.d/rspamd.list \
	&& apt-get update && apt-get install -y rspamd dnsutils \
	&& rm -rf /var/lib/apt/lists/* \
	&& apt-get autoremove --purge \
	&& apt-get clean \
	&& mkdir -p /run/rspamd \
	&& chown _rspamd:_rspamd /run/rspamd

COPY settings.conf /etc/rspamd/settings.conf
COPY docker-entrypoint.sh /docker-entrypoint.sh

ENTRYPOINT ["/docker-entrypoint.sh"]

STOPSIGNAL SIGTERM

CMD ["/usr/bin/rspamd", "-f", "-u", "_rspamd", "-g", "_rspamd"]
