From ubuntu:xenial
MAINTAINER Andre Peters <andre.peters@servercow.de>

# Set noninteractive mode for apt-get
ENV DEBIAN_FRONTEND noninteractive

# Update
RUN apt-get update

# Start editing
# Install package here for cache
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

# Run
CMD /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
