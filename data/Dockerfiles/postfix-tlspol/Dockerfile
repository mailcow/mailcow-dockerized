FROM golang:1.25-bookworm AS builder
WORKDIR /src

ENV CGO_ENABLED=0 \
    GO111MODULE=on \
    VERSION=1.8.14

RUN wget https://github.com/Zuplu/postfix-tlspol/archive/refs/tags/v${VERSION}.tar.gz -O postfix-tlspol.tar.gz && \
    mkdir /src/postfix-tlspol && \
    tar -xzf postfix-tlspol.tar.gz -C /src/postfix-tlspol --strip-components=1 && \
    cd /src/postfix-tlspol && \
    scripts/build.sh build-only


FROM debian:bookworm-slim
LABEL maintainer="The Infrastructure Company GmbH <info@servercow.de>"

ARG DEBIAN_FRONTEND=noninteractive
ENV LC_ALL=C

RUN apt-get update && apt-get install -y --no-install-recommends \
	ca-certificates \
	dirmngr \
  	dnsutils \
	iputils-ping \
	sudo \
	supervisor \
	redis-tools \
	syslog-ng \
	syslog-ng-core \
	syslog-ng-mod-redis \
  	tzdata \
	&& rm -rf /var/lib/apt/lists/* \
	&& touch /etc/default/locale

COPY supervisord.conf /etc/supervisor/supervisord.conf
COPY syslog-ng.conf /etc/syslog-ng/syslog-ng.conf
COPY syslog-ng-redis_slave.conf /etc/syslog-ng/syslog-ng-redis_slave.conf
COPY postfix-tlspol.sh /opt/postfix-tlspol.sh
COPY stop-supervisor.sh /usr/local/sbin/stop-supervisor.sh
COPY docker-entrypoint.sh /docker-entrypoint.sh
COPY --from=builder /src/postfix-tlspol/build/postfix-tlspol /usr/local/bin/postfix-tlspol

RUN chmod +x /opt/postfix-tlspol.sh \
  /usr/local/sbin/stop-supervisor.sh \
  /docker-entrypoint.sh
RUN rm -rf /tmp/* /var/tmp/*

ENTRYPOINT ["/docker-entrypoint.sh"]

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]