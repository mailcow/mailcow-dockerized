FROM index.docker.io/library/alpine:latest AS builder
ENV CLAMD_VERSION=1.1.0

WORKDIR /src

# hadolint ignore=DL3008  We want the latest stable versions
RUN apk update && apk upgrade \
    && \
    apk add --no-cache \
        bsd-compat-headers \
        cmake \
        file \
        g++ \
        libtool \
        linux-headers \
        make \
        musl-fts-dev \
        # Clamav dependencies provided by alpine
        bzip2-dev \
        check-dev \
        curl-dev \
        json-c-dev \
        libmilter-dev \
        libxml2-dev \
        ncurses-dev \
        ncurses-dev \
        openssl-dev \
        pcre2-dev \
        zlib-dev \
        # For the tests
        python3 \
        py3-pytest \
        # For Rust/Cargo
        cargo \
        rust \
    && \
    wget https://github.com/Cisco-Talos/clamav/releases/download/clamav-${CLAMD_VERSION}/clamav-${CLAMD_VERSION}.tar.gz \
    && tar -xzf clamav-${CLAMD_VERSION}.tar.gz && cd clamav-${CLAMD_VERSION} && mkdir build && cd build\
    && cmake .. \
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
    && \
    make DESTDIR="/clamav" -j$(($(nproc) - 1)) install && \
    rm -r \
       "/clamav/usr/lib/pkgconfig/" \
    && \
    sed -e "s|^\(Example\)|\# \1|" \
        -e "s|.*\(PidFile\) .*|\1 /tmp/clamd.pid|" \
        -e "s|.*\(LocalSocket\) .*|\1 /tmp/clamd.sock|" \
        -e "s|.*\(TCPSocket\) .*|\1 3310|" \
        -e "s|.*\(TCPAddr\) .*|#\1 0.0.0.0|" \
        -e "s|.*\(User\) .*|\1 clamav|" \
        -e "s|^\#\(LogFile\) .*|\1 /var/log/clamav/clamd.log|" \
        -e "s|^\#\(LogTime\).*|\1 yes|" \
        "/clamav/etc/clamav/clamd.conf.sample" > "/clamav/etc/clamav/clamd.conf" && \
    sed -e "s|^\(Example\)|\# \1|" \
        -e "s|.*\(PidFile\) .*|\1 /tmp/freshclam.pid|" \
        -e "s|.*\(DatabaseOwner\) .*|\1 clamav|" \
        -e "s|^\#\(UpdateLogFile\) .*|\1 /var/log/clamav/freshclam.log|" \
        -e "s|^\#\(NotifyClamd\).*|\1 /etc/clamav/clamd.conf|" \
        -e "s|^\#\(ScriptedUpdates\).*|\1 yes|" \
        "/clamav/etc/clamav/freshclam.conf.sample" > "/clamav/etc/clamav/freshclam.conf" && \
    sed -e "s|^\(Example\)|\# \1|" \
        -e "s|.*\(PidFile\) .*|\1 /tmp/clamav-milter.pid|" \
        -e "s|.*\(MilterSocket\) .*|\1 inet:7357|" \
        -e "s|.*\(User\) .*|\1 clamav|" \
        -e "s|^\#\(LogFile\) .*|\1 /var/log/clamav/milter.log|" \
        -e "s|^\#\(LogTime\).*|\1 yes|" \
        -e "s|.*\(\ClamdSocket\) .*|\1 unix:/tmp/clamd.sock|" \
        "/clamav/etc/clamav/clamav-milter.conf.sample" > "/clamav/etc/clamav/clamav-milter.conf" || \
    exit 1 \
    && \
    ctest -V

FROM index.docker.io/library/alpine:latest

LABEL maintainer "The Infrastructure Company GmbH <info@servercow.de>"

EXPOSE 3310
EXPOSE 7357

ENV TZ Europe/Berlin

RUN apk add --no-cache \
        fts \
        libstdc++ \
        tini \
        tzdata \
        # Clamav dependencies provided by alpine
        json-c \
        libbz2 \
        libcurl \
        libmilter \
        libxml2 \
        ncurses-libs \
        pcre2 \
        zlib \
        rsync \
        bind-tools \
        bash \
    && \
    addgroup -S "clamav" && \
    adduser -D -G "clamav" -h "/var/lib/clamav" -s "/bin/false" -u 100 -S "clamav" && \
    install -d -m 755 -g "clamav" -o "clamav" "/var/log/clamav" && \
    chown -R clamav:clamav /var/lib/clamav

COPY --from=builder "/clamav" "/"

# init
COPY clamd.sh /clamd.sh
RUN chmod +x /sbin/tini

# healthcheck
COPY healthcheck.sh /healthcheck.sh
RUN chmod +x /healthcheck.sh
HEALTHCHECK --start-period=6m CMD "/healthcheck.sh"

CMD ["/sbin/tini", "-g", "--", "/clamd.sh"]