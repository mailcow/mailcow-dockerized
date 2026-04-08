# SOGo built from source to enable security patch application
# Repository: https://github.com/Alinto/sogo
# Version: SOGo-5.12.4
#
# Applied security patches:
# -
#
# To add new patches, modify SOGO_SECURITY_PATCHES ARG below with space-separated commit hashes

FROM debian:bookworm

LABEL maintainer="The Infrastructure Company GmbH <info@servercow.de>"

ARG DEBIAN_FRONTEND=noninteractive
ARG SOGO_VERSION=SOGo-5.12.5
ARG SOPE_VERSION=SOPE-5.12.5
# Security patches to apply (space-separated commit hashes)
ARG SOGO_SECURITY_PATCHES=""
# renovate: datasource=github-releases depName=tianon/gosu versioning=semver-coerced extractVersion=^(?<version>.*)$
ARG GOSU_VERSION=1.19
ENV LC_ALL=C

# Install dependencies, build SOPE and SOGo, then clean up (all in one layer to minimize image size)
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Build dependencies
    git \
    build-essential \
    gobjc \
    pkg-config \
    gnustep-make \
    gnustep-base-runtime \
    libgnustep-base-dev \
    libxml2-dev \
    libldap2-dev \
    libssl-dev \
    zlib1g-dev \
    libpq-dev \
    libmariadb-dev-compat \
    libmemcached-dev \
    libsodium-dev \
    libcurl4-openssl-dev \
    libzip-dev \
    libytnef0-dev \
    libwbxml2-dev \
    curl \
    ca-certificates \
    # Runtime dependencies
    apt-transport-https \
    gettext \
    gnupg \
    mariadb-client \
    rsync \
    supervisor \
    syslog-ng \
    syslog-ng-core \
    syslog-ng-mod-redis \
    dirmngr \
    netcat-traditional \
    psmisc \
    wget \
    patch \
    libobjc4 \
    libxml2 \
    libldap-2.5-0 \
    libssl3 \
    zlib1g \
    libmariadb3 \
    libmemcached11 \
    libsodium23 \
    libcurl4 \
    libzip4 \
    libytnef0 \
    libwbxml2-1 \
  # Download gosu
  && dpkgArch="$(dpkg --print-architecture | awk -F- '{ print $NF }')" \
  && wget -O /usr/local/bin/gosu "https://github.com/tianon/gosu/releases/download/$GOSU_VERSION/gosu-$dpkgArch" \
  && chmod +x /usr/local/bin/gosu \
  && gosu nobody true \
  # Build SOPE
  && git clone --depth 1 --branch ${SOPE_VERSION} https://github.com/Alinto/sope.git /tmp/sope \
  && cd /tmp/sope \
  && rm -rf .git \
  && . /usr/share/GNUstep/Makefiles/GNUstep.sh \
  && ./configure --prefix=/usr --disable-debug --disable-strip \
  && make -j$(nproc) \
  && make install \
  && cd / \
  && rm -rf /tmp/sope \
  # Build SOGo with security patches
  && git clone --depth 1 --branch ${SOGO_VERSION} https://github.com/Alinto/sogo.git /tmp/sogo \
  && cd /tmp/sogo \
  && git config user.email "builder@mailcow.local" \
  && git config user.name "SOGo Builder" \
  && for patch in ${SOGO_SECURITY_PATCHES}; do \
       echo "Applying security patch: ${patch}"; \
       git fetch origin ${patch} && git cherry-pick ${patch}; \
     done \
  && rm -rf .git \
  && . /usr/share/GNUstep/Makefiles/GNUstep.sh \
  && ./configure --disable-debug --disable-strip \
  && make -j$(nproc) \
  && make install \
  && cd /tmp/sogo/ActiveSync \
  && . /usr/share/GNUstep/Makefiles/GNUstep.sh \
  && make -j$(nproc) install \
  && cd / \
  && rm -rf /tmp/sogo \
  # Strip binaries
  && strip --strip-unneeded /usr/local/sbin/sogod 2>/dev/null || true \
  && strip --strip-unneeded /usr/local/sbin/sogo-tool 2>/dev/null || true \
  && strip --strip-unneeded /usr/local/sbin/sogo-ealarms-notify 2>/dev/null || true \
  && strip --strip-unneeded /usr/local/sbin/sogo-slapd-sockd 2>/dev/null || true \
  # Remove build dependencies and clean up
  && apt-get purge -y --auto-remove \
    git \
    build-essential \
    gobjc \
    gnustep-make \
    libgnustep-base-dev \
    libxml2-dev \
    libldap2-dev \
    libssl-dev \
    zlib1g-dev \
    libpq-dev \
    libmariadb-dev-compat \
    libmemcached-dev \
    libsodium-dev \
    libcurl4-openssl-dev \
    libzip-dev \
    libytnef0-dev \
    curl \
  && apt-get autoremove -y \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/* \
  && rm -rf /usr/share/doc/* \
  && rm -rf /usr/share/man/* \
  && rm -rf /var/cache/debconf/* \
  && rm -rf /tmp/* \
  && rm -rf /root/.cache \
  && find /usr/local/lib -name '*.a' -delete \
  && find /usr/lib -name '*.a' -delete \
  && mkdir -p /usr/share/doc/sogo \
  && touch /usr/share/doc/sogo/empty.sh \
  && touch /etc/default/locale

# Configure library paths
RUN echo "/usr/lib64" > /etc/ld.so.conf.d/sogo.conf \
  && echo "/usr/local/lib/sogo" >> /etc/ld.so.conf.d/sogo.conf \
  && echo "/usr/local/lib/GNUstep/Frameworks/SOGo.framework/Versions/5/sogo" >> /etc/ld.so.conf.d/sogo.conf \
  && ldconfig

# Create sogo user and group
RUN groupadd -r -g 999 sogo \
  && useradd -r -u 999 -g sogo -d /var/lib/sogo -s /bin/bash -c "SOGo Daemon" sogo \
  && mkdir -p /var/lib/sogo /var/run/sogo /var/log/sogo /var/spool/sogo \
  && chown -R sogo:sogo /var/lib/sogo /var/run/sogo /var/log/sogo /var/spool/sogo

# Create symlinks for SOGo binaries
RUN ln -s /usr/local/sbin/sogod /usr/sbin/sogod \
  && ln -s /usr/local/sbin/sogo-tool /usr/sbin/sogo-tool \
  && ln -s /usr/local/sbin/sogo-ealarms-notify /usr/sbin/sogo-ealarms-notify \
  && ln -s /usr/local/sbin/sogo-slapd-sockd /usr/sbin/sogo-slapd-sockd

# Copy configuration files and scripts
COPY ./bootstrap-sogo.sh /bootstrap-sogo.sh
COPY syslog-ng.conf /etc/syslog-ng/syslog-ng.conf
COPY syslog-ng-redis_slave.conf /etc/syslog-ng/syslog-ng-redis_slave.conf
COPY supervisord.conf /etc/supervisor/supervisord.conf
COPY acl.diff /acl.diff
COPY navMailcowBtns.diff /navMailcowBtns.diff
COPY stop-supervisor.sh /usr/local/sbin/stop-supervisor.sh
COPY docker-entrypoint.sh /

RUN chmod +x /bootstrap-sogo.sh \
  /usr/local/sbin/stop-supervisor.sh

ENTRYPOINT ["/docker-entrypoint.sh"]

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
