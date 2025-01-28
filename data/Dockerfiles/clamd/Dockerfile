FROM alpine:3.21 AS builder

WORKDIR /src
ENV CLAMD_VERSION=1.4.2

RUN apk upgrade --no-cache \
  && apk add --update --no-cache \
    g++ \
    gcc \
    gdb \
    make \
    cmake \
    py3-pytest \
    python3 \
    valgrind \
    bzip2-dev \
    check-dev \
    curl-dev \
    json-c-dev \
    libmilter-dev \
    libxml2-dev \
    linux-headers \
    ncurses-dev \
    openssl-dev \
    pcre2-dev \
    zlib-dev \
    cargo \
    rust

RUN mkdir -p /src \
  && wget -P /src https://www.clamav.net/downloads/production/clamav-${CLAMD_VERSION}.tar.gz \
  && tar xzfv /src/clamav-${CLAMD_VERSION}.tar.gz \
  && cd /src/clamav-${CLAMD_VERSION} \
  && mkdir build \
  && cmake . \
  -D CMAKE_BUILD_TYPE="Release"                                                       \
  -D CMAKE_INSTALL_PREFIX="/usr"                                                      \
  -D CMAKE_INSTALL_LIBDIR="/usr/lib"                                                  \
  -D APP_CONFIG_DIRECTORY="/etc/clamav"                                               \
  -D DATABASE_DIRECTORY="/var/lib/clamav"                                             \
  -D ENABLE_CLAMONACC=OFF                                                             \
  -D ENABLE_EXAMPLES=OFF                                                              \
  -D ENABLE_MILTER=ON                                                                 \
  -D ENABLE_MAN_PAGES=OFF                                                             \
  -D ENABLE_STATIC_LIB=OFF                                                            \
  -D ENABLE_JSON_SHARED=ON                                                            \ 
  && cmake --build . \
  && make DESTDIR="/clamav" -j$(($(nproc) - 1)) install \
  && rm -r "/clamav/usr/lib/pkgconfig/" \
  && sed -e "s|^\(Example\)|\# \1|" \
    -e "s|.*\(LocalSocket\) .*|\1 /tmp/clamd.sock|" \
    -e "s|.*\(TCPSocket\) .*|\1 3310|" \
    -e "s|.*\(TCPAddr\) .*|#\1 0.0.0.0|" \
    -e "s|.*\(User\) .*|\1 clamav|" \
    -e "s|^\#\(LogFile\) .*|\1 /var/log/clamav/clamd.log|" \
    -e "s|^\#\(LogTime\).*|\1 yes|" \
    "/clamav/etc/clamav/clamd.conf.sample" > "/clamav/etc/clamav/clamd.conf" \
  && sed -e "s|^\(Example\)|\# \1|" \
    -e "s|.*\(DatabaseOwner\) .*|\1 clamav|" \
    -e "s|^\#\(UpdateLogFile\) .*|\1 /var/log/clamav/freshclam.log|" \
    -e "s|^\#\(NotifyClamd\).*|\1 /etc/clamav/clamd.conf|" \
    -e "s|^\#\(ScriptedUpdates\).*|\1 yes|" \
    "/clamav/etc/clamav/freshclam.conf.sample" > "/clamav/etc/clamav/freshclam.conf" \
  && sed -e "s|^\(Example\)|\# \1|" \
  -e "s|.*\(MilterSocket\) .*|\1 inet:7357|" \
  -e "s|.*\(User\) .*|\1 clamav|" \
  -e "s|^\#\(LogFile\) .*|\1 /var/log/clamav/milter.log|" \
  -e "s|^\#\(LogTime\).*|\1 yes|" \
  -e "s|.*\(\ClamdSocket\) .*|\1 unix:/tmp/clamd.sock|" \
  "/clamav/etc/clamav/clamav-milter.conf.sample" > "/clamav/etc/clamav/clamav-milter.conf" || exit 1


FROM alpine:3.21

LABEL maintainer = "The Infrastructure Company GmbH <info@servercow.de>"

RUN apk upgrade --no-cache \
  && apk add --update --no-cache \
    tzdata \
    rsync \
    bind-tools \
    bash \
    tini \
    json-c \
    libbz2 \
    libcurl \
    libmilter \
    libxml2 \
    ncurses-libs \
    pcre2 \
    zlib \
    libgcc \
  && addgroup -S "clamav" && \
    adduser -D -G "clamav" -h "/var/lib/clamav" -s "/bin/false" -S "clamav" && \
    install -d -m 755 -g "clamav" -o "clamav" "/var/log/clamav" && \
    chown -R clamav:clamav /var/lib/clamav

COPY --from=builder "/clamav" "/"

# init
COPY clamd.sh /clamd.sh
RUN chmod +x /sbin/tini

# healthcheck
COPY healthcheck.sh /healthcheck.sh
COPY clamdcheck.sh /usr/local/bin
RUN chmod +x /healthcheck.sh
RUN chmod +x /usr/local/bin/clamdcheck.sh
HEALTHCHECK --start-period=6m CMD "/healthcheck.sh"

ENTRYPOINT []
CMD ["/sbin/tini", "-g", "--", "/clamd.sh"]