FROM debian:stretch-slim
MAINTAINER https://m-ko.de Markus Kosmal <code@cnfg.io>

# Debian Base to use
ENV DEBIAN_VERSION stretch

# initial install of av daemon
RUN echo "deb http://http.debian.net/debian/ $DEBIAN_VERSION main contrib non-free" > /etc/apt/sources.list && \
	echo "deb http://http.debian.net/debian/ $DEBIAN_VERSION-updates main contrib non-free" >> /etc/apt/sources.list && \
	echo "deb http://security.debian.org/ $DEBIAN_VERSION/updates main contrib non-free" >> /etc/apt/sources.list && \
	apt-get update && \
	DEBIAN_FRONTEND=noninteractive apt-get install --no-install-recommends -y -qq \
		clamav-daemon \
		clamav-freshclam \
		libclamunrar7 \
		curl && \
	apt-get clean && \
	rm -rf /var/lib/apt/lists/*

# initial update of av databases
COPY dl_files.sh /dl_files.sh
RUN chmod +x /dl_files.sh
RUN /dl_files.sh

# permission juggling
RUN mkdir /var/run/clamav && \
	chown clamav:clamav /var/run/clamav && \
	chmod 750 /var/run/clamav

# av configuration update
RUN sed -i 's/^Foreground .*$/Foreground true/g' /etc/clamav/clamd.conf && \
	echo "TCPSocket 3310" >> /etc/clamav/clamd.conf && \
	sed -i 's/^Foreground .*$/Foreground true/g' /etc/clamav/freshclam.conf

# port provision
EXPOSE 3310

# av daemon bootstrapping
COPY bootstrap.sh /
CMD ["/bootstrap.sh"]
