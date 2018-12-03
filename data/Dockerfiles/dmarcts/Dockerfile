FROM debian:stretch-slim
LABEL maintainer "Clete Blackwell <clete@blackwell.email>"

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update

RUN apt-get install -y --no-install-recommends apt-utils

RUN apt-get install -y --no-install-recommends \
	libmail-imapclient-perl \
	libmime-tools-perl \
	libxml-simple-perl \
	libclass-dbi-mysql-perl \
	libio-socket-inet6-perl \
	libio-socket-ip-perl \
	libperlio-gzip-perl \
	libmail-mbox-messageparser-perl \
	unzip \
	git \
	ca-certificates

RUN git clone https://github.com/techsneeze/dmarcts-report-parser.git

COPY dmarcts-report-parser.conf /dmarcts-report-parser/dmarcts-report-parser.conf

COPY docker-entrypoint.sh /docker-entrypoint.sh

CMD ["/bin/bash", "/docker-entrypoint.sh"]