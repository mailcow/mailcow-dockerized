FROM ubuntu:xenial
MAINTAINER Andre Peters <andre.peters@debinux.de>

ENV DEBIAN_FRONTEND noninteractive
ENV LC_ALL C

RUN dpkg-divert --local --rename --add /sbin/initctl \
    && ln -sf /bin/true /sbin/initctl \
    && dpkg-divert --local --rename --add /usr/bin/ischroot \
    && ln -sf /bin/true /usr/bin/ischroot

RUN echo 'deb http://repo.powerdns.com/ubuntu xenial-rec-40 main' > /etc/apt/sources.list.d/pdns.list

RUN echo 'Package: pdns-*\n\
Pin: origin repo.powerdns.com\n\
Pin-Priority: 600\n' > /etc/apt/preferences.d/pdns

RUN apt-key adv --fetch-keys http://repo.powerdns.com/FD380FBB-pub.asc \
	&& apt-get update \
	&& apt-get install -y --force-yes pdns-recursor

CMD ["/usr/sbin/pdns_recursor"]

EXPOSE 53/udp

RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*
