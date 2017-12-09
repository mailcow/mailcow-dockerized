FROM php:7.1-fpm-alpine
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ENV REDIS_PECL 3.1.4
ENV MEMCACHED_PECL 3.0.3
ENV APCU_PECL 5.1.8
ENV IMAGICK_PECL 3.4.3

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
  gettext-dev \
  openldap-dev \
  librsvg \
  && pear install channel://pear.php.net/Net_IDNA2-0.2.0 \
    channel://pear.php.net/Auth_SASL-1.1.0 \
    Net_IMAP \
    Net_Sieve \
    NET_SMTP \
    Mail_mime \
	&& pecl install redis-${REDIS_PECL} memcached-${MEMCACHED_PECL} APCu-${APCU_PECL} imagick-${IMAGICK_PECL} mailparse \
	&& docker-php-ext-enable redis apcu memcached imagick mailparse \
	&& pecl clear-cache \
	&& docker-php-ext-configure intl \
  && docker-php-ext-install -j 4 intl gettext ldap sockets soap pdo pdo_mysql xmlrpc gd zip pcntl opcache \
  && docker-php-ext-configure imap --with-imap --with-imap-ssl \
	&& docker-php-ext-install -j 4 imap \
	&& apk del --purge autoconf g++ make libxml2-dev icu-dev imap-dev openssl-dev cyrus-sasl-dev pcre-dev libpng-dev libpng-dev libjpeg-turbo-dev libwebp-dev zlib-dev imagemagick-dev \
	&& { \
  echo 'opcache.enable=1'; \
  echo 'opcache.enable_cli=1'; \
  echo 'opcache.interned_strings_buffer=8'; \
  echo 'opcache.max_accelerated_files=10000'; \
  echo 'opcache.memory_consumption=128'; \
  echo 'opcache.save_comments=1'; \
  echo 'opcache.revalidate_freq=1'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

COPY ./docker-entrypoint.sh /

EXPOSE 9000

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["php-fpm"]
