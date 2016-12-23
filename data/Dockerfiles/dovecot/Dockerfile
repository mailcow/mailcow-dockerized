From ubuntu:xenial
MAINTAINER Andre Peters <andre.peters@servercow.de>

ENV DEBIAN_FRONTEND noninteractive
ENV LC_ALL C

RUN dpkg-divert --local --rename --add /sbin/initctl \
    && ln -sf /bin/true /sbin/initctl \
    && dpkg-divert --local --rename --add /usr/bin/ischroot \
    && ln -sf /bin/true /usr/bin/ischroot

RUN apt-get update
RUN apt-get -y install dovecot-common \
	dovecot-core \
	dovecot-imapd \
	dovecot-lmtpd \
	dovecot-managesieved \
	dovecot-sieve \
	dovecot-mysql \
	dovecot-pop3d \
	dovecot-dev \
	wget \
	curl \
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

COPY ./rspamd-pipe /usr/local/bin/rspamd-pipe
COPY ./docker-entrypoint.sh /

RUN chmod +x /usr/local/bin/rspamd-pipe

RUN groupadd -g 5000 vmail
RUN useradd -g vmail -u 5000 vmail -d /var/vmail

EXPOSE 24 10001

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["/usr/sbin/dovecot", "-F"]

RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*
