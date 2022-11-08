FROM php:8.1-fpm-alpine3.16
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ENV APCU_PECL 5.1.22
ENV IMAGICK_PECL 3.7.0
ENV MAILPARSE_PECL 3.1.4
ENV MEMCACHED_PECL 3.2.0
ENV REDIS_PECL 5.3.7
ENV COMPOSER 2.4.4

RUN apk add -U --no-cache autoconf \
  aspell-dev \
  aspell-libs \
  bash \
  c-client \
  cyrus-sasl-dev \
  freetype \
  freetype-dev \
  g++ \
  git \
  gettext \
  gettext-dev \
  gmp-dev \
  gnupg \
  icu-dev \
  icu-libs \
  imagemagick \
  imagemagick-dev \
  imap-dev \
  jq \
  libavif \
  libavif-dev \
  libjpeg-turbo \
  libjpeg-turbo-dev \
  libmemcached \
  libmemcached-dev \
  libpng \
  libpng-dev \
  libressl \
  libressl-dev \
  librsvg \
  libtool \
  libwebp-dev \
  libxml2-dev \
  libxpm \
  libxpm-dev \
  libzip \
  libzip-dev \
  make \
  mysql-client \
  openldap-dev \
  pcre-dev \
  re2c \
  redis \
  samba-client \
  zlib-dev \
  tzdata \
  && pecl install mailparse-${MAILPARSE_PECL} \
  && pecl install redis-${REDIS_PECL} \
  && pecl install memcached-${MEMCACHED_PECL} \
  && pecl install APCu-${APCU_PECL} \
  && pecl install imagick-${IMAGICK_PECL} \
  && docker-php-ext-enable apcu imagick memcached mailparse redis \
  && pecl clear-cache \
  && docker-php-ext-configure intl \
  && docker-php-ext-configure exif \
  && docker-php-ext-configure gd --with-freetype=/usr/include/ \  
    --with-jpeg=/usr/include/ \
    --with-webp \
    --with-xpm \
    --with-avif \
  && docker-php-ext-install -j 4 exif gd gettext intl ldap opcache pcntl pdo pdo_mysql pspell soap sockets zip bcmath gmp \
  && docker-php-ext-configure imap --with-imap --with-imap-ssl \
  && docker-php-ext-install -j 4 imap \
  && curl --silent --show-error https://getcomposer.org/installer | php -- --version=${COMPOSER} \
  && mv composer.phar /usr/local/bin/composer \
  && chmod +x /usr/local/bin/composer \
  && apk del --purge autoconf \
    aspell-dev \
    cyrus-sasl-dev \
    freetype-dev \
    g++ \
    gettext-dev \
    icu-dev \
    imagemagick-dev \
    imap-dev \
    libavif-dev \
    libjpeg-turbo-dev \
    libmemcached-dev \
    libpng-dev \
    libressl-dev \
    libwebp-dev \
    libxml2-dev \
    libxpm-dev \
    libzip-dev \
    make \
    openldap-dev \
    pcre-dev \
    zlib-dev

COPY ./docker-entrypoint.sh /

ENTRYPOINT ["/docker-entrypoint.sh"]

CMD ["php-fpm"]
