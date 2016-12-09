From ubuntu:xenial
MAINTAINER Andre Peters <andre.peters@servercow.de>

# Set noninteractive mode for apt-get
ENV DEBIAN_FRONTEND noninteractive

# Update
RUN apt-get update

# Start editing
# Install package here for cache
RUN apt-get -y install dovecot-common dovecot-core dovecot-imapd dovecot-lmtpd dovecot-managesieved dovecot-sieve dovecot-mysql dovecot-pop3d

RUN groupadd -g 5000 vmail
RUN useradd -g vmail -u 5000 vmail -d /var/vmail

EXPOSE 24 10001

# Run
CMD ["/usr/sbin/dovecot", "-F"]
