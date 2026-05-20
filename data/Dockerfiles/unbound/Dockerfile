ARG AGENT_IMAGE=ghcr.io/mailcow/agent:1.00

FROM ${AGENT_IMAGE} AS mailcow-agent-src

FROM alpine:3.23

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

COPY --from=mailcow-agent-src /out/mailcow-agent /usr/local/bin/mailcow-agent
COPY --from=mailcow-agent-src /out/mailcow-agent-cli /usr/local/bin/mailcow-agent-cli

ENV MAILCOW_AGENT_SERVICE=unbound \
    MAILCOW_AGENT_MAIN_CMD="/docker-entrypoint.sh /usr/bin/supervisord -c /etc/supervisor/supervisord.conf"

HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
  CMD ["/usr/local/bin/mailcow-agent", "healthcheck"]

ENTRYPOINT ["/usr/local/bin/mailcow-agent"]
CMD []
