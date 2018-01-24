FROM alpine:3.6

LABEL maintainer "André Peters <andre.peters@servercow.de>"

# Add scripts
COPY dl_files.sh bootstrap.sh ./

# Installation
RUN apk add --update \
	&& apk add --no-cache clamav clamav-libunrar curl bash tini \
	&& chmod +x /dl_files.sh \
	&& set -ex; /bin/bash /dl_files.sh \
	&& mkdir /run/clamav \
	&& chown clamav:clamav /run/clamav \
	&& chmod 750 /run/clamav

# Port provision
EXPOSE 3310

# AV daemon bootstrapping
CMD ["/sbin/tini", "-g", "--", "/bootstrap.sh"]
