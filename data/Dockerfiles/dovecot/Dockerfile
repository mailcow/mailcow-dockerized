FROM ubuntu:xenial
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
	syslog-ng \
	syslog-ng-core \
	ca-certificates \
	supervisor \
	wget \
	curl \
	build-essential \
	autotools-dev \
	automake \
	libauthen-ntlm-perl \
	libcrypt-ssleay-perl \
	libdigest-hmac-perl \
	libfile-copy-recursive-perl \
	libio-compress-perl \
	libio-socket-inet6-perl \
	libio-socket-ssl-perl \
	libio-tee-perl \
	libmodule-scandeps-perl \
	libnet-ssleay-perl \
	libpar-packer-perl \
	libreadonly-perl \
	libterm-readkey-perl \
	libtest-pod-perl \
	libtest-simple-perl \
	libunicode-string-perl \
	liburi-perl \
	libdbi-perl \
	liblockfile-simple-perl \
	libdbd-mysql-perl \
	libipc-run-perl \
	make \
	cpanminus

RUN sed -i -E 's/^(\s*)system\(\);/\1unix-stream("\/dev\/log");/' /etc/syslog-ng/syslog-ng.conf
RUN cpanm Data::Uniqid Mail::IMAPClient String::Util
RUN echo '* * * * *   root   /usr/local/bin/imapsync_cron.pl' > /etc/cron.d/imapsync
RUN echo '30 3 * * *   vmail  /usr/bin/doveadm quota recalc -A' > /etc/cron.d/dovecot-sync

WORKDIR /tmp

RUN wget http://hg.dovecot.org/dovecot-antispam-plugin/archive/tip.tar.gz -O - | tar xvz  \
	&& cd /tmp/dovecot-antispam* \
	&& ./autogen.sh \
	&& ./configure --prefix=/usr \
	&& make \
	&& make install

COPY ./imapsync /usr/local/bin/imapsync
COPY ./postlogin.sh /usr/local/bin/postlogin.sh
COPY ./imapsync_cron.pl /usr/local/bin/imapsync_cron.pl
COPY ./rspamd-pipe /usr/local/bin/rspamd-pipe
COPY ./docker-entrypoint.sh /
COPY ./supervisord.conf /etc/supervisor/supervisord.conf

RUN chmod +x /usr/local/bin/rspamd-pipe
RUN chmod +x /usr/local/bin/imapsync_cron.pl

RUN groupadd -g 5000 vmail
RUN useradd -g vmail -u 5000 vmail -d /var/vmail

EXPOSE 24 10001

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf

RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*
