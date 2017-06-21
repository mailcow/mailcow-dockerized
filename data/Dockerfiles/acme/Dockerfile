FROM alpine:3.6

LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

RUN apk add --update --no-cache \
	bash \
	acme-client \
	curl \
	openssl \
	bind-tools \
	mariadb-client

COPY docker-entrypoint.sh /srv/docker-entrypoint.sh

ENTRYPOINT ["/srv/docker-entrypoint.sh"]
