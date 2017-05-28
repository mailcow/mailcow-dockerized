FROM alpine:3.6

LABEL maintainer "https://m-ko.de Markus Kosmal <code@cnfg.io>"

# Add scripts
COPY dl_files.sh bootstrap.sh ./

# Install Dependencies
RUN apk add --update \
&& apk add --no-cache clamav clamav-libunrar curl bash \
\
# Update AV databases
&& chmod +x dl_files.sh \
&& set -ex; /dl_files.sh \
\
# Setup run
&& mkdir /run/clamav \
&& chown clamav:clamav /run/clamav \
&& chmod 750 /run/clamav \
\
# AV configuration
&& sed -i '/Foreground yes/s/^#//g' /etc/clamav/clamd.conf \
&& sed -i '/TCPSocket 3310/s/^#//g' /etc/clamav/clamd.conf \
&& sed -i '/Foreground yes/s/^#//g' /etc/clamav/freshclam.conf

# Port provision
EXPOSE 3310

# AV daemon bootstrapping
CMD ["/bootstrap.sh"]
