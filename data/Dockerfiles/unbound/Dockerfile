FROM alpine:3.20

LABEL maintainer = "The Infrastructure Company GmbH <info@servercow.de>"

RUN apk add --update --no-cache \
	curl \
	bind-tools \
	coreutils \
	unbound \
	bash \
	openssl \
	drill \
	tzdata \
	syslog-ng \
	supervisor \
	&& curl -o /etc/unbound/root.hints https://www.internic.net/domain/named.cache \
	&& chown root:unbound /etc/unbound \
    && adduser unbound tty \
	&& chmod 775 /etc/unbound

EXPOSE 53/udp 53/tcp

COPY docker-entrypoint.sh /docker-entrypoint.sh

# healthcheck (dig, ping)
COPY healthcheck.sh /healthcheck.sh
COPY syslog-ng.conf /etc/syslog-ng/syslog-ng.conf
COPY supervisord.conf /etc/supervisor/supervisord.conf
COPY stop-supervisor.sh /usr/local/sbin/stop-supervisor.sh

RUN chmod +x /healthcheck.sh
HEALTHCHECK --interval=30s --timeout=10s \
  CMD sh -c '[ -f /tmp/healthcheck_status ] && [ "$(cat /tmp/healthcheck_status)" -eq 0 ] || exit 1'

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
