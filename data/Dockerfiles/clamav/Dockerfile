FROM alpine
LABEL maintainer "https://m-ko.de Markus Kosmal <code@cnfg.io>"

RUN apk add -U --no-cache clamav clamav-libunrar clamav-daemon bash curl

# initial update of av databases
COPY dl_files.sh /dl_files.sh
RUN chmod +x /dl_files.sh

# pre-download defintions
RUN set -ex; \
	/dl_files.sh && \
	apk del curl

# permission juggling
RUN mkdir -p /run/clamav && \
	chown clamav:clamav /run/clamav && \
	chmod 750 /run/clamav

# av configuration update
RUN sed -i 's/^Foreground .*$/Foreground true/g' /etc/clamav/clamd.conf && \
	echo "TCPSocket 3310" >> /etc/clamav/clamd.conf && \
	sed -i 's/^Foreground .*$/Foreground true/g' /etc/clamav/freshclam.conf

# port provision
EXPOSE 3310

# av daemon bootstrapping
COPY bootstrap.sh /
CMD ["/bootstrap.sh"]
