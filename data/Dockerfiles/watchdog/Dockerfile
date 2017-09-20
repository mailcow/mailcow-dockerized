FROM alpine:3.6

LABEL maintainer "André Peters <andre.peters@servercow.de>"

# Installation
RUN apk add --update \
	&& apk add --no-cache nagios-plugins-smtp \
	nagios-plugins-tcp \
	nagios-plugins-http \
	nagios-plugins-ping \
	curl \
	bash \
	jq \
	fcgi \
	nagios-plugins-mysql \
	nagios-plugins-dns \
	bind-tools

COPY watchdog.sh /watchdog.sh

# Less verbose
CMD /watchdog.sh 2> /dev/null
