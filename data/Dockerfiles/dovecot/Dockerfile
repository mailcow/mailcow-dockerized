FROM debian:stretch-slim
#ubuntu:xenial
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ENV DEBIAN_FRONTEND noninteractive
ENV LC_ALL C
ENV DOVECOT_VERSION 2.2.29.1
ENV PIGEONHOLE_VERSION 0.4.18

RUN apt-get update && apt-get -y install \
	libpam-dev \
	default-libmysqlclient-dev \
	lzma-dev \
	liblz-dev \
	libbz2-dev \
	liblz4-dev \
	liblzma-dev \
	build-essential \
	autotools-dev \
	automake \
	syslog-ng \
	syslog-ng-core \
	syslog-ng-mod-redis \
	ca-certificates \
	supervisor \
	wget \
	curl \
	libssl-dev \
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
	cpanminus \
	&& rm -rf /var/lib/apt/lists/*


RUN wget https://www.dovecot.org/releases/2.2/dovecot-$DOVECOT_VERSION.tar.gz -O - | tar xvz  \
	&& cd dovecot-$DOVECOT_VERSION \
	&& ./configure --with-mysql --with-lzma --with-lz4 --with-ssl=openssl --with-notify=inotify --with-storages=mdbox,sdbox,maildir,mbox,imapc,pop3c --with-bzlib --with-zlib \
	&& make -j3 \
	&& make install \
	&& make clean

RUN wget https://pigeonhole.dovecot.org/releases/2.2/dovecot-2.2-pigeonhole-$PIGEONHOLE_VERSION.tar.gz -O - | tar xvz  \
	&& cd dovecot-2.2-pigeonhole-$PIGEONHOLE_VERSION \
	&& ./configure \
	&& make -j3 \
	&& make install \
	&& make clean

RUN cpanm Data::Uniqid Mail::IMAPClient String::Util
RUN echo '* * * * *   root   /usr/local/bin/imapsync_cron.pl' > /etc/cron.d/imapsync
RUN echo '30 3 * * *   vmail  /usr/bin/doveadm quota recalc -A' > /etc/cron.d/dovecot-sync

COPY syslog-ng.conf /etc/syslog-ng/syslog-ng.conf
COPY imapsync /usr/local/bin/imapsync
COPY postlogin.sh /usr/local/bin/postlogin.sh
COPY imapsync_cron.pl /usr/local/bin/imapsync_cron.pl
COPY report-spam.sieve /usr/local/lib/dovecot/sieve/report-spam.sieve
COPY report-ham.sieve /usr/local/lib/dovecot/sieve/report-ham.sieve
COPY rspamd-pipe-ham /usr/local/lib/dovecot/sieve/rspamd-pipe-ham
COPY rspamd-pipe-spam /usr/local/lib/dovecot/sieve/rspamd-pipe-spam
COPY docker-entrypoint.sh /
COPY supervisord.conf /etc/supervisor/supervisord.conf

RUN chmod +x /usr/local/lib/dovecot/sieve/rspamd-pipe-ham \
	/usr/local/lib/dovecot/sieve/rspamd-pipe-spam \
	/usr/local/bin/imapsync_cron.pl \
	/usr/local/bin/postlogin.sh \
	/usr/local/bin/imapsync

RUN groupadd -g 5000 vmail \
	&& groupadd -g 401 dovecot \
	&& groupadd -g 402 dovenull \
	&& useradd -g vmail -u 5000 vmail -d /var/vmail \
	&& useradd -c "Dovecot unprivileged user" -d /dev/null -u 401 -g dovecot -s /bin/false dovecot \
	&& useradd -c "Dovecot login user" -d /dev/null -u 402 -g dovenull -s /bin/false dovenull

RUN touch /etc/default/locale

EXPOSE 24 10001

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf

RUN rm -rf \
	/tmp/* \
	/var/tmp/* \
	/dovecot-2.2-pigeonhole-$PIGEONHOLE_VERSION \
	/dovecot-$DOVECOT_VERSION
