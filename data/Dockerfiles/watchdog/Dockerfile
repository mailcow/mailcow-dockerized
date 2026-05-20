ARG AGENT_IMAGE=ghcr.io/mailcow/agent:1.00

FROM ${AGENT_IMAGE} AS mailcow-agent-src

FROM alpine:3.23

LABEL maintainer = "The Infrastructure Company GmbH <info@servercow.de>"

# Installation
RUN apk add --update \
  && apk add --no-cache nagios-plugins-smtp \
  nagios-plugins-tcp \
  nagios-plugins-http \
  nagios-plugins-ping \
  mariadb-client \
  curl \
  bash \
  coreutils \
  jq \
  fcgi \
  openssl \
  nagios-plugins-mysql \
  nagios-plugins-disk \
  bind-tools \
  redis \
  perl \
  perl-net-dns \
  perl-io-socket-ssl \
  perl-io-socket-inet6 \
  perl-socket \
  perl-socket6 \
  perl-mime-lite \
  perl-term-readkey \
  tini \
  tzdata \
  whois \
  && curl https://raw.githubusercontent.com/mludvig/smtp-cli/v3.10/smtp-cli -o /smtp-cli \
  && chmod +x smtp-cli \
  && mkdir /usr/lib/mailcow

COPY watchdog.sh /watchdog.sh
COPY check_mysql_slavestatus.sh /usr/lib/nagios/plugins/check_mysql_slavestatus.sh
COPY check_dns.sh /usr/lib/mailcow/check_dns.sh
COPY client.cnf /etc/my.cnf.d/client.cnf

COPY --from=mailcow-agent-src /out/mailcow-agent /usr/local/bin/mailcow-agent
COPY --from=mailcow-agent-src /out/mailcow-agent-cli /usr/local/bin/mailcow-agent-cli

ENV MAILCOW_AGENT_SERVICE=watchdog \
    MAILCOW_AGENT_MAIN_CMD="/watchdog.sh"

HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
  CMD ["/usr/local/bin/mailcow-agent", "healthcheck"]

ENTRYPOINT ["/usr/local/bin/mailcow-agent"]
CMD []
