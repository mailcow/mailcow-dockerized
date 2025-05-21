FROM debian:bookworm-slim

LABEL maintainer = "The Infrastructure Company GmbH <info@servercow.de>"

ARG DEBIAN_FRONTEND=noninteractive
ENV LC_ALL C

RUN dpkg-divert --local --rename --add /sbin/initctl \
	&& ln -sf /bin/true /sbin/initctl \
	&& dpkg-divert --local --rename --add /usr/bin/ischroot \
	&& ln -sf /bin/true /usr/bin/ischroot

# Add groups and users before installing Postfix to not break compatibility
RUN groupadd -g 102 postfix \
  && groupadd -g 103 postdrop \
  && useradd -g postfix -u 101 -d /var/spool/postfix -s /usr/sbin/nologin postfix \
  && apt-get update && apt-get install -y --no-install-recommends \
	ca-certificates \
	curl \
	dirmngr \
  	dnsutils \
	gnupg \
	libsasl2-modules \
  	mariadb-client \
	perl \
	postfix \
	postfix-mysql \
	postfix-pcre \
	redis-tools \
	sasl2-bin \
	sudo \
	supervisor \
	syslog-ng \
	syslog-ng-core \
	syslog-ng-mod-redis \
  	tzdata \
	python3 python3-pip \
	&& rm -rf /var/lib/apt/lists/* \
	&& touch /etc/default/locale \
  && printf '#!/bin/bash\n/usr/sbin/postconf -c /opt/postfix/conf "$@"' > /usr/local/sbin/postconf \
  && chmod +x /usr/local/sbin/postconf

RUN pip install  --break-system-packages \
  mysql-connector-python \
  jinja2 \
	redis \
	dnspython

COPY data/Dockerfiles/bootstrap /bootstrap
COPY data/Dockerfiles/postfix/supervisord.conf /etc/supervisor/supervisord.conf
COPY data/Dockerfiles/postfix/syslog-ng.conf /etc/syslog-ng/syslog-ng.conf
COPY data/Dockerfiles/postfix/syslog-ng-redis_slave.conf /etc/syslog-ng/syslog-ng-redis_slave.conf
COPY data/Dockerfiles/postfix/rspamd-pipe-ham /usr/local/bin/rspamd-pipe-ham
COPY data/Dockerfiles/postfix/rspamd-pipe-spam /usr/local/bin/rspamd-pipe-spam
COPY data/Dockerfiles/postfix/whitelist_forwardinghosts.sh /usr/local/bin/whitelist_forwardinghosts.sh
COPY data/Dockerfiles/postfix/stop-supervisor.sh /usr/local/sbin/stop-supervisor.sh
COPY data/Dockerfiles/postfix/docker-entrypoint.sh /docker-entrypoint.sh

RUN chmod +x /usr/local/bin/rspamd-pipe-ham \
	/docker-entrypoint.sh \
  /usr/local/bin/rspamd-pipe-spam \
  /usr/local/bin/whitelist_forwardinghosts.sh \
  /usr/local/sbin/stop-supervisor.sh
RUN rm -rf /tmp/* /var/tmp/*

EXPOSE 588


CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
