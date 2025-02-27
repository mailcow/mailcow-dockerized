FROM debian:bookworm-slim
LABEL maintainer="The Infrastructure Company GmbH <info@servercow.de>"

ARG DEBIAN_FRONTEND=noninteractive
ARG RSPAMD_VER=rspamd_3.11.0-2~90a175b45
ARG CODENAME=bookworm
ENV LC_ALL=C

RUN apt-get update && apt-get install -y \
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

ENTRYPOINT ["/docker-entrypoint.sh"]

STOPSIGNAL SIGTERM

CMD ["/usr/bin/rspamd", "-f", "-u", "_rspamd", "-g", "_rspamd"]
