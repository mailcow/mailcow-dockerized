FROM php:8.5-fpm-alpine3.24

LABEL maintainer = "The Infrastructure Company GmbH <info@servercow.de>"

# renovate: datasource=github-tags depName=krakjoe/apcu versioning=semver-coerced extractVersion=^v(?<version>.*)$
ARG APCU_PECL_VERSION=5.1.28
# renovate: datasource=github-tags depName=Imagick/imagick versioning=semver-coerced extractVersion=(?<version>.*)$
ARG IMAGICK_PECL_VERSION=3.8.1
# renovate: datasource=github-tags depName=php/pecl-mail-mailparse versioning=semver-coerced extractVersion=^v(?<version>.*)$
ARG MAILPARSE_PECL_VERSION=3.2.1
# renovate: datasource=github-tags depName=php-memcached-dev/php-memcached versioning=semver-coerced extractVersion=^v(?<version>.*)$
ARG MEMCACHED_PECL_VERSION=3.4.0
# renovate: datasource=github-tags depName=phpredis/phpredis versioning=semver-coerced extractVersion=(?<version>.*)$
ARG REDIS_PECL_VERSION=6.3.0
# renovate: datasource=github-tags depName=composer/composer versioning=semver-coerced extractVersion=(?<version>.*)$
ARG COMPOSER_VERSION=2.10.2

RUN apk add -U --no-cache autoconf \
  bash \
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
  libwebp \
  libwebp-dev \
  libxml2-dev \
  libxpm \
  libxpm-dev \
  libzip \
  libzip-dev \
  linux-headers \
  make \
  mysql-client \
  openldap-dev \
  pcre-dev \
  re2c \
  redis \
  samba-client \
  zlib-dev \
  tzdata \
  && docker-php-source extract \
  && pecl install APCu-${APCU_PECL_VERSION} \
  && pecl install imagick-${IMAGICK_PECL_VERSION} \
  && pecl install mailparse-${MAILPARSE_PECL_VERSION} \
  && pecl install memcached-${MEMCACHED_PECL_VERSION} \
  && pecl install redis-${REDIS_PECL_VERSION} \
  && docker-php-ext-enable apcu imagick memcached mailparse redis \
  && pecl clear-cache \
  && docker-php-ext-configure intl \
  && docker-php-ext-configure exif \
  && docker-php-ext-configure gd --with-freetype=/usr/include/ \  
    --with-jpeg=/usr/include/ \
    --with-webp \
    --with-xpm \
    --with-avif \
  && docker-php-ext-install -j 4 exif gd gettext intl ldap pcntl pdo pdo_mysql soap sockets zip bcmath gmp \
  && curl --silent --show-error https://getcomposer.org/installer | php -- --version=${COMPOSER_VERSION} \
  && mv composer.phar /usr/local/bin/composer \
  && chmod +x /usr/local/bin/composer \
  && docker-php-source delete \
  && apk del --purge autoconf \
    cyrus-sasl-dev \
    freetype-dev \
    g++ \
    gettext-dev \
    icu-dev \
    imagemagick-dev \
    libavif-dev \
    libjpeg-turbo-dev \
    libmemcached-dev \
    libpng-dev \
    libressl-dev \
    libwebp-dev \
    libxml2-dev \
    libxpm-dev \
    libzip-dev \
    linux-headers \
    make \
    openldap-dev \
    pcre-dev \
    zlib-dev

COPY ./docker-entrypoint.sh /

ENTRYPOINT ["/docker-entrypoint.sh"]

CMD ["php-fpm"]
