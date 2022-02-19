FROM debian:bullseye-slim
LABEL maintainer "Andre Peters <andre.peters@tinc.gmbh>"

ARG DEBIAN_FRONTEND=noninteractive
ARG CODENAME=bullseye
ENV LC_ALL C

RUN apt-get update && apt-get install -y \
  tzdata \
  ca-certificates \
  gnupg2 \
  apt-transport-https \
  dnsutils \
  netcat \
  && apt-key adv --fetch-keys https://rspamd.com/apt-stable/gpg.key \
  && echo "deb [arch=amd64] https://rspamd.com/apt-stable/ $CODENAME main" > /etc/apt/sources.list.d/rspamd.list \
  && apt-get update \
  && apt-get --no-install-recommends -y install rspamd redis-tools procps nano \
  && rm -rf /var/lib/apt/lists/* \
  && apt-get autoremove --purge \
  && apt-get clean \
  && mkdir -p /run/rspamd \
  && chown _rspamd:_rspamd /run/rspamd \
  && echo 'alias ll="ls -la --color"' >> ~/.bashrc \
  && sed -i 's/#analysis_keyword_table > 0/analysis_cat_table.macro_exist == "M"/g' /usr/share/rspamd/lualib/lua_scanners/oletools.lua

COPY settings.conf /etc/rspamd/settings.conf
COPY metadata_exporter.lua /usr/share/rspamd/plugins/metadata_exporter.lua
COPY docker-entrypoint.sh /docker-entrypoint.sh

ENTRYPOINT ["/docker-entrypoint.sh"]

STOPSIGNAL SIGTERM

CMD ["/usr/bin/rspamd", "-f", "-u", "_rspamd", "-g", "_rspamd"]
