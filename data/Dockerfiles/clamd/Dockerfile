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
	&& chmod 750 /run/clamav \
	&& sed -i '/Foreground yes/s/^#//g' /etc/clamav/clamd.conf \
	&& sed -i '/TCPSocket 3310/s/^#//g' /etc/clamav/clamd.conf \
  && sed -i 's#LogFile /var/log/clamav/clamd.log#LogFile /tmp/logpipe_clamd#g' /etc/clamav/clamd.conf \
	&& sed -i 's/#PhishingSignatures yes/PhishingSignatures no/g' /etc/clamav/clamd.conf \
	&& sed -i 's/#PhishingScanURLs yes/PhishingScanURLs no/g' /etc/clamav/clamd.conf \
  && sed -i 's#UpdateLogFile /var/log/clamav/freshclam.log#UpdateLogFile /tmp/logpipe_freshclam#g' /etc/clamav/freshclam.conf \
	&& sed -i '/Foreground yes/s/^#//g' /etc/clamav/freshclam.conf

# Port provision
EXPOSE 3310

# AV daemon bootstrapping
CMD ["/sbin/tini", "-g", "--", "/bootstrap.sh"]
