From ubuntu:xenial
MAINTAINER Andre Peters <andre.peters@servercow.de>

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update

RUN apt-get -y install supervisor \
	postfix \
	sasl2-bin \
	postfix \
	postfix-mysql \
	postfix-pcre \
	rsyslog \
	ca-certificates

COPY supervisord.conf /etc/supervisor/supervisord.conf
COPY postfix.sh /opt/postfix.sh

RUN groupadd -g 5000 vmail
RUN useradd -g vmail -u 5000 vmail -d /var/vmail

EXPOSE 588

CMD /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
