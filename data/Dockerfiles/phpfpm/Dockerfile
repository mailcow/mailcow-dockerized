FROM php:7.2-fpm-alpine3.7
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ENV APCU_PECL 5.1.12
ENV IMAGICK_PECL 3.4.3
ENV MAILPARSE_PECL 3.0.2
ENV MEMCACHED_PECL 3.0.4
ENV REDIS_PECL 4.1.1

RUN apk add -U --no-cache autoconf \
  bash \
  c-client \
  cyrus-sasl-dev \
  freetype \
  freetype-dev \
  g++ \
  gettext-dev \
  icu-dev \
  icu-libs \
  imagemagick \
  imagemagick-dev \
  imap-dev \
  libjpeg-turbo \
  libjpeg-turbo-dev \
  libmemcached-dev \
  libpng \
  libpng-dev \
  libressl \
  libressl-dev \
  librsvg \
  libtool \
  libwebp-dev \
  libxml2-dev \
  libxpm-dev \
  make \
  mysql-client \
  openldap-dev \
  pcre-dev \
  re2c \
  redis \
  samba-client \
  zlib-dev \
  tzdata \
  && pear install channel://pear.php.net/Net_IDNA2-0.2.0 \
    channel://pear.php.net/Auth_SASL-1.1.0 \
    Net_IMAP \
    Net_Sieve \
    NET_SMTP \
    Mail_mime \
  && pecl install redis-${REDIS_PECL} memcached-${MEMCACHED_PECL} APCu-${APCU_PECL} imagick-${IMAGICK_PECL} mailparse-${MAILPARSE_PECL} \
  && docker-php-ext-enable apcu imagick mailparse memcached redis \
  && pecl clear-cache \
  && docker-php-ext-configure intl \
  && docker-php-ext-configure gd \
    --with-gd \
    --enable-gd-native-ttf \
    --with-freetype-dir=/usr/include/ \
    --with-png-dir=/usr/include/ \
    --with-jpeg-dir=/usr/include/ \
  && docker-php-ext-install -j 4 gd gettext intl ldap opcache pcntl pdo pdo_mysql soap sockets xmlrpc zip \
  && docker-php-ext-configure imap --with-imap --with-imap-ssl \
  && docker-php-ext-install -j 4 imap \
  && apk del --purge autoconf \
    cyrus-sasl-dev \
    freetype-dev \
    g++ \
    icu-dev \
    imagemagick-dev \
    imap-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libressl-dev \
    libwebp-dev \
    libxml2-dev \
    make \
    pcre-dev \
    zlib-dev

COPY ./docker-entrypoint.sh /

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["php-fpm"]
