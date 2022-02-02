FROM debian:bullseye-slim

LABEL maintainer "André Peters <andre.peters@servercow.de>"

ARG CLAMAV=0.104.2
ARG TINI_VERSION=v0.19.0

RUN apt-get update && apt-get install -y --no-install-recommends \
  ca-certificates \
  build-essential \
  pkg-config \
  python3 \
  python3-pip \
  valgrind \
  check \
  libbz2-dev \
  libcurl4-openssl-dev \
  libjson-c-dev \
  libmilter-dev \
  libncurses5-dev \
  libpcre2-dev \
  libssl-dev \
  libxml2-dev \
  zlib1g-dev \
  curl \
  bash \
  wget \
  tzdata \
  dnsutils \
  rsync \
  dos2unix \
  netcat \
  && python3 -m pip install cmake \
  && rm -rf /var/lib/apt/lists/* \
  && wget -O - https://www.clamav.net/downloads/production/clamav-${CLAMAV}.tar.gz | tar xfvz - \
  && cd clamav-${CLAMAV} \
  && cmake . \
      -D CMAKE_INSTALL_PREFIX=/usr \
      -D CMAKE_INSTALL_LIBDIR=/usr/lib \
      -D APP_CONFIG_DIRECTORY=/etc/clamav \
      -D CMAKE_INSTALL_MANDIR=/usr/share/man \
      -D CMAKE_INSTALL_INFODIR=/usr/share/info \
      -D CLAMAV_USER=clamav \
      -D CLAMAV_GROUP=clamav \
      -D DATABASE_DIRECTORY=/var/lib/clamav \
      -D ENABLE_APP=ON \
      -D ENABLE_JSON_SHARED=OFF \
      -D CMAKE_BUILD_TYPE=MinSizeRel \
  && cmake --build . -j4 \
  && cmake --build . --target install \
  && cd .. && rm -rf clamav-${CLAMAV} \
  && apt-get -y --auto-remove purge build-essential \
  && apt-get -y purge pkg-config \
  python3 \
  python3-pip \
  valgrind \
  check \
  libbz2-dev \
  libcurl4-openssl-dev \
  libjson-c-dev \
  libmilter-dev \
  libncurses5-dev \
  libpcre2-dev \
  libssl-dev \
  libxml2-dev \
  zlib1g-dev \

  && addgroup --system --gid 700 clamav \
  && adduser --system --no-create-home --home /var/lib/clamav --uid 700 --gid 700 --disabled-login clamav \
  && rm -rf /tmp/* /var/tmp/*

COPY clamd.sh ./
ADD https://github.com/krallin/tini/releases/download/${TINI_VERSION}/tini /sbin/tini
RUN chmod +x /sbin/tini

CMD ["/sbin/tini", "-g", "--", "/clamd.sh"]
