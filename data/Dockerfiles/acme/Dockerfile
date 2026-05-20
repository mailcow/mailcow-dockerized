ARG AGENT_IMAGE=ghcr.io/mailcow/agent:1.00

FROM ${AGENT_IMAGE} AS mailcow-agent-src

FROM alpine:3.23

LABEL maintainer = "The Infrastructure Company GmbH <info@servercow.de>"

RUN apk upgrade --no-cache \
  && apk add --update --no-cache \
  bash \
  curl \
  openssl \
  bind-tools \
  jq \
  mariadb-client \
  redis \
  tini \
  tzdata \
  python3 \
  acme-tiny \
  git \
  socat \
  && git clone --depth 1 https://github.com/acmesh-official/acme.sh.git /opt/acme.sh \
  && chmod +x /opt/acme.sh/acme.sh \
  && mkdir -p /var/lib/acme/acme-sh

ENV ACME_SH_BIN=/opt/acme.sh/acme.sh \
    ACME_SH_HOME=/opt/acme.sh \
    ACME_SH_CONFIG_HOME=/var/lib/acme/acme-sh

COPY acme.sh /srv/acme.sh
COPY functions.sh /srv/functions.sh
COPY obtain-certificate.sh /srv/obtain-certificate.sh
COPY obtain-certificate-dns.sh /srv/obtain-certificate-dns.sh
COPY load-dns-config.sh /srv/load-dns-config.sh
COPY reload-configurations.sh /srv/reload-configurations.sh
COPY expand6.sh /srv/expand6.sh

RUN chmod +x /srv/*.sh

COPY --from=mailcow-agent-src /out/mailcow-agent /usr/local/bin/mailcow-agent
COPY --from=mailcow-agent-src /out/mailcow-agent-cli /usr/local/bin/mailcow-agent-cli

ENV MAILCOW_AGENT_SERVICE=acme \
    MAILCOW_AGENT_MAIN_CMD="/sbin/tini -g -- /srv/acme.sh"

HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
  CMD ["/usr/local/bin/mailcow-agent", "healthcheck"]

ENTRYPOINT ["/usr/local/bin/mailcow-agent"]
CMD []
