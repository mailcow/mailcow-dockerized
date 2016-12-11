FROM debian:jessie
MAINTAINER Andre Peters <andre.peters@debinux.de>

ENV DEBIAN_FRONTEND noninteractive

RUN echo 'deb http://repo.powerdns.com/debian jessie-rec-40 main' > /etc/apt/sources.list.d/pdns.list

RUN echo 'Package: pdns-*\n\
Pin: origin repo.powerdns.com\n\
Pin-Priority: 600\n' > /etc/apt/preferences.d/pdns

RUN apt-key adv --fetch-keys http://repo.powerdns.com/FD380FBB-pub.asc \
	&& apt-get update \
	&& apt-get install -y --force-yes pdns-recursor

CMD ["/usr/sbin/pdns_recursor"]

EXPOSE 53/udp
