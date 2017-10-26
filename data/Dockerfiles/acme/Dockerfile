FROM alpine:3.6

LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

RUN apk add --update --no-cache \
	bash \
	acme-client \
	curl \
	openssl \
	bind-tools \
	jq \
	mariadb-client \
  tini

COPY docker-entrypoint.sh /srv/docker-entrypoint.sh

CMD ["/sbin/tini", "-g", "--", "/srv/docker-entrypoint.sh"]
