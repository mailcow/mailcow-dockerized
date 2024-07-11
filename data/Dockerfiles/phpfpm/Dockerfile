FROM php:8.2-fpm-bookworm
LABEL maintainer "The Infrastructure Company GmbH <info@servercow.de>"

# renovate: datasource=github-tags depName=krakjoe/apcu versioning=semver-coerced extractVersion=^v(?<version>.*)$
ARG APCU_PECL_VERSION=5.1.23
# renovate: datasource=github-tags depName=Imagick/imagick versioning=semver-coerced extractVersion=(?<version>.*)$
ARG IMAGICK_PECL_VERSION=3.7.0
# renovate: datasource=github-tags depName=php/pecl-mail-mailparse versioning=semver-coerced extractVersion=^v(?<version>.*)$
ARG MAILPARSE_PECL_VERSION=3.1.6
# renovate: datasource=github-tags depName=php-memcached-dev/php-memcached versioning=semver-coerced extractVersion=^v(?<version>.*)$
ARG MEMCACHED_PECL_VERSION=3.2.0
# renovate: datasource=github-tags depName=phpredis/phpredis versioning=semver-coerced extractVersion=(?<version>.*)$
ARG REDIS_PECL_VERSION=6.0.2
# renovate: datasource=github-tags depName=composer/composer versioning=semver-coerced extractVersion=(?<version>.*)$
ARG COMPOSER_VERSION=2.6.6

RUN arch=$(arch | sed s/aarch64/arm64/ | sed s/x86_64/amd64/) && \
  apt-get update && apt-get install --no-install-recommends -y \
  aspell \
  aspell-en \
  autoconf \
  bash \
  default-mysql-client \
  dnsutils \
  g++ \
  gettext \
  git \
  gnupg \
  imagemagick \
  jq \
  libc-client-dev \
  libc-client2007e \
  libfreetype6-dev \
  libgettextpo-dev \
  libgmp-dev \
  libicu-dev \
  libjpeg62-turbo-dev \
  libkrb5-3 \
  libkrb5-dev \
  libldap2-dev \
  libmagickcore-dev \
  libmagickwand-dev \
  libmemcached-dev \
  libmemcached11 \
  libpcre3-dev \
  libpng-dev \
  libpspell-dev \
  librsvg2-dev \
  libsasl2-dev \
  libssl-dev \
  libwebp-dev \
  libxml2-dev \
  libxpm-dev \
  libxpm4 \
  libzip-dev \
  libzip4 \
  make \
  re2c \
  redis-tools \
  smbclient \
  tzdata \
  zlib1g-dev

  RUN pecl install APCu-${APCU_PECL_VERSION} \
  && pecl install imagick-${IMAGICK_PECL_VERSION} \
  && pecl install mailparse-${MAILPARSE_PECL_VERSION} \
  && pecl install memcached-${MEMCACHED_PECL_VERSION} \
  && pecl install redis-${REDIS_PECL_VERSION} \
  && docker-php-ext-enable apcu imagick memcached mailparse redis \
  && pecl clear-cache \
  && docker-php-ext-configure intl \
  && docker-php-ext-configure exif \
  && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-xpm \
  && docker-php-ext-install -j 4 exif gd gettext intl ldap opcache pcntl pdo pdo_mysql pspell soap sockets sysvsem zip bcmath gmp \
  && docker-php-ext-configure imap --with-imap --with-imap-ssl --with-kerberos \
  && docker-php-ext-install -j 4 imap  \
  && curl --silent --show-error https://getcomposer.org/installer | php -- --version=${COMPOSER_VERSION} \
  && mv composer.phar /usr/local/bin/composer \
  && chmod +x /usr/local/bin/composer

  RUN apt-get purge -y --auto-remove \
    autoconf \
    g++ \
    libc-client-dev \
    libgettextpo-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libkrb5-dev \
    libldap2-dev \
    libmagickcore-dev \
    libmagickwand-dev \
    libmemcached-dev \
    libpcre3-dev \
    libpng-dev \
    libpspell-dev \
    libsasl2-dev \
    libssl-dev \
    libwebp-dev \
    libxml2-dev \
    libxpm-dev \
    libzip-dev \
    make \
    zlib1g-dev

COPY ./docker-entrypoint.sh /

ENTRYPOINT ["/docker-entrypoint.sh"]

CMD ["php-fpm"]
