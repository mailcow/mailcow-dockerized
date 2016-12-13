From ubuntu:xenial
MAINTAINER Andre Peters <andre.peters@servercow.de>

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update \
	&& apt-get -y install dovecot-common \
		dovecot-core \
		dovecot-imapd \
		dovecot-lmtpd \
		dovecot-managesieved \
		dovecot-sieve \
		dovecot-mysql \
		dovecot-pop3d \
		dovecot-dev \
		wget \
		build-essential \
		autotools-dev \
		automake

WORKDIR /tmp
RUN wget http://hg.dovecot.org/dovecot-antispam-plugin/archive/tip.tar.gz -O - | tar xvz  \
	&& cd /tmp/dovecot-antispam* \
	&& ./autogen.sh \
	&& ./configure --prefix=/usr \
	&& make \
	&& make install

ADD rspamd-pipe /usr/local/bin/rspamd-pipe

RUN chmod +x /usr/local/bin/rspamd-pipe
RUN groupadd -g 5000 vmail
RUN useradd -g vmail -u 5000 vmail -d /var/vmail

EXPOSE 24 10001

CMD ["/usr/sbin/dovecot", "-F"]
