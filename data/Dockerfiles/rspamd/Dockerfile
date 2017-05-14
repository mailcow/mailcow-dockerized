FROM alpine:edge
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ENV LC_ALL C

RUN echo 'http://dl-cdn.alpinelinux.org/alpine/edge/testing' >> /etc/apk/repositories \
	&& apk add --no-cache rspamd rspamd-controller rsyslog ca-certificates \
	&& adduser -S _rspamd

RUN echo '.include $LOCAL_CONFDIR/local.d/rspamd.conf.local' > /etc/rspamd/rspamd.conf.local

COPY settings.conf /etc/rspamd/modules.d/settings.conf
COPY antivirus.conf /etc/rspamd/modules.d/antivirus.conf
COPY dkim_signing.lua /usr/share/rspamd/lua/dkim_signing.lua

CMD /usr/sbin/rspamd -f -u _rspamd -g _rspamd

RUN rm -rf /tmp/* /var/tmp/*
RUN mkdir /run/rspamd && chown -R _rspamd: /run/rspamd

USER _rspamd

EXPOSE 11333 11334
