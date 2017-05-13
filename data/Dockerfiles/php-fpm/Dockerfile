FROM php:7.1-fpm
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
	g++ \
	libicu-dev \
	libidn11-dev \
	libxml2-dev \
	mysql-client \
	redis-tools \
	zlib1g-dev \
	&& rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure intl
RUN docker-php-ext-install intl pdo pdo_mysql xmlrpc
RUN pear install channel://pear.php.net/Net_IDNA2-0.1.1 Auth_SASL Net_IMAP NET_SMTP Net_IDNA2 Mail_mime
RUN pecl install -o -f redis \
	&& rm -rf /tmp/pear \
	&& docker-php-ext-enable redis

COPY ./docker-entrypoint.sh /

EXPOSE 9000

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["php-fpm"]
