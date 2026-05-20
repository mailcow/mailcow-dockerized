ARG AGENT_IMAGE=ghcr.io/mailcow/agent:1.00

FROM ${AGENT_IMAGE} AS mailcow-agent-src

FROM debian:trixie-slim
LABEL maintainer="The Infrastructure Company GmbH <info@servercow.de>"

ARG DEBIAN_FRONTEND=noninteractive
ARG RSPAMD_VER=rspamd_3.14.3-1~236eb65
ARG CODENAME=trixie
ENV LC_ALL=C

RUN apt-get update && apt-get install -y --no-install-recommends \
  tzdata \
  ca-certificates \
  gnupg2 \
  apt-transport-https \
  dnsutils \
  netcat-traditional \
  wget \
  redis-tools \
  procps \
  nano \
  lua-cjson \
  && arch=$(arch | sed s/aarch64/arm64/ | sed s/x86_64/amd64/) \
  && wget -P /tmp https://rspamd.com/apt-stable/pool/main/r/rspamd/${RSPAMD_VER}~${CODENAME}_${arch}.deb\
  && apt install -y /tmp/${RSPAMD_VER}~${CODENAME}_${arch}.deb \
  && rm -rf /var/lib/apt/lists/* /tmp/*\
  && apt-get autoremove --purge \
  && apt-get clean \
  && mkdir -p /run/rspamd \
  && chown _rspamd:_rspamd /run/rspamd \
  && echo 'alias ll="ls -la --color"' >> ~/.bashrc \
  && sed -i 's/#analysis_keyword_table > 0/analysis_cat_table.macro_exist == "M"/g' /usr/share/rspamd/lualib/lua_scanners/oletools.lua

COPY settings.conf /etc/rspamd/settings.conf
COPY set_worker_password.sh /set_worker_password.sh
COPY docker-entrypoint.sh /docker-entrypoint.sh

STOPSIGNAL SIGTERM

COPY --from=mailcow-agent-src /out/mailcow-agent /usr/local/bin/mailcow-agent
COPY --from=mailcow-agent-src /out/mailcow-agent-cli /usr/local/bin/mailcow-agent-cli

ENV MAILCOW_AGENT_SERVICE=rspamd \
    MAILCOW_AGENT_MAIN_CMD="/docker-entrypoint.sh /usr/bin/rspamd -f -u _rspamd -g _rspamd"

HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
  CMD ["/usr/local/bin/mailcow-agent", "healthcheck"]

ENTRYPOINT ["/usr/local/bin/mailcow-agent"]
CMD []
