FROM php:7.1-fpm-alpine
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ENV REDIS_PECL 3.1.6
ENV MEMCACHED_PECL 3.0.4
ENV APCU_PECL 5.1.9
ENV IMAGICK_PECL 3.4.3
ENV MAILPARSE_PECL 3.0.2

RUN apk add -U --no-cache libxml2-dev \
	icu-dev \
	imap-dev \
	libmemcached-dev \
	cyrus-sasl-dev \
	pcre-dev \
	icu-libs \
	redis \
	mysql-client \
	bash \
	autoconf \
	g++ \
	make \
	openssl \
	openssl-dev \
	samba-client \
	libpng \
	libpng-dev \
	libjpeg-turbo-dev \
	libwebp-dev \
	zlib-dev \
	libxpm-dev \
	c-client \
  imagemagick-dev \
  imagemagick \
  libtool \
  freetype \
  libpng \
  libjpeg-turbo \
  freetype-dev \
  libpng-dev \
  libjpeg-turbo-dev\
  gettext-dev \
  openldap-dev \
  librsvg \
  && pear install channel://pear.php.net/Net_IDNA2-0.2.0 \
    channel://pear.php.net/Auth_SASL-1.1.0 \
    Net_IMAP \
    Net_Sieve \
    NET_SMTP \
    Mail_mime \
	&& pecl install redis-${REDIS_PECL} memcached-${MEMCACHED_PECL} APCu-${APCU_PECL} imagick-${IMAGICK_PECL} mailparse-${MAILPARSE_PECL} \
	&& docker-php-ext-enable redis apcu memcached imagick mailparse \
	&& pecl clear-cache \
	&& docker-php-ext-configure intl \
  && docker-php-ext-configure gd \
      --with-gd \
      --enable-gd-native-ttf \
      --with-freetype-dir=/usr/include/ \
      --with-png-dir=/usr/include/ \
      --with-jpeg-dir=/usr/include/ \
  && docker-php-ext-install -j 4 intl gettext ldap sockets soap pdo pdo_mysql xmlrpc gd zip pcntl opcache \
  && docker-php-ext-configure imap --with-imap --with-imap-ssl \
	&& docker-php-ext-install -j 4 imap \
	&& apk del --purge autoconf \
  g++ \
  make \
  libxml2-dev \
  icu-dev \
  imap-dev \
  openssl-dev \
  cyrus-sasl-dev \
  pcre-dev \
  libpng-dev \
  libpng-dev \
  libjpeg-turbo-dev \
  libwebp-dev \
  zlib-dev \
  imagemagick-dev \
  freetype-dev \
  libpng-dev \
  libjpeg-turbo-dev

COPY ./docker-entrypoint.sh /

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["php-fpm"]
