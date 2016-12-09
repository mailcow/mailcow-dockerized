FROM debian:jessie
MAINTAINER Andre Peters <andre.peters@debinux.de>

RUN apt-get update \
	&& apt-get -y --force-yes install apt-transport-https \
	&& apt-key adv --keyserver keys.gnupg.net --recv-key 0x810273C4 \
	&& echo "deb http://packages.inverse.ca/SOGo/nightly/3/debian/ jessie jessie" > /etc/apt/sources.list.d/sogo.list \
	&& apt-get update \
	&& apt-get -y --force-yes install sogo sogo-activesync

USER sogo

CMD ["/usr/sbin/sogod"]

EXPOSE 20000
