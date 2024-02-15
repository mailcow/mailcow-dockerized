FROM alpine:3.18

LABEL maintainer "The Infrastructure Company GmbH <info@servercow.de>"

RUN apk add --update --no-cache \
	curl \
	bind-tools \
	unbound \
	bash \
	openssl \
	drill \
	tzdata \
	&& curl -o /etc/unbound/root.hints https://www.internic.net/domain/named.cache \
	&& chown root:unbound /etc/unbound \
  && adduser unbound tty \
	&& chmod 775 /etc/unbound

EXPOSE 53/udp 53/tcp

COPY docker-entrypoint.sh /docker-entrypoint.sh

# healthcheck (dig, ping)
COPY healthcheck.sh /healthcheck.sh
RUN chmod +x /healthcheck.sh
HEALTHCHECK --interval=30s --timeout=30s CMD [ "/healthcheck.sh" ]

ENTRYPOINT ["/docker-entrypoint.sh"]

CMD ["/usr/sbin/unbound"]
