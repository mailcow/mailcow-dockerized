FROM nginx:mainline
MAINTAINER Andre Peters <andre.peters@servercow.de>

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update \
        && apt-get install -y zlib1g-dev libicu-dev g++ libidn11-dev

RUN apt-key adv --keyserver keys.gnupg.net --recv-key 0x810273C4 \
	&& apt-get update \
	&& apt-get -y --force-yes install apt-transport-https

RUN echo 'deb http://packages.inverse.ca/SOGo/nightly/3/debian/ jessie jessie' > /etc/apt/sources.list.d/sogo.list \
	&& apt-get update \
	&& apt-get -y --force-yes install sogo

EXPOSE 80 443

CMD ["nginx", "-g", "daemon off;"]
