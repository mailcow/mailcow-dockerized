FROM php:7.1-fpm-alpine
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ENV REDIS_PECL 3.1.4
ENV MEMCACHED_PECL 3.0.3
ENV APCU_PECL 5.1.8

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
	&& pecl install redis-${REDIS_PECL} memcached-${MEMCACHED_PECL} APCu-${APCU_PECL} \
	&& docker-php-ext-enable redis apcu memcached \
	&& pecl clear-cache \
	&& docker-php-ext-configure intl \
    && docker-php-ext-install intl pdo pdo_mysql xmlrpc gd zip pcntl opcache \
    && docker-php-ext-configure imap --with-imap --with-imap-ssl \
	&& docker-php-ext-install imap \
	&& pear install channel://pear.php.net/Net_IDNA2-0.1.1 Auth_SASL2 Net_IMAP NET_SMTP Net_IDNA2 Mail_mime \
	&& apk del autoconf g++ make libxml2-dev icu-dev imap-dev openssl-dev cyrus-sasl-dev pcre-dev libpng-dev libpng-dev libjpeg-turbo-dev libwebp-dev zlib-dev \
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
