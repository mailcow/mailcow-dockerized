FROM php:7.1-fpm-alpine
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

RUN apk add -U --no-cache libxml2-dev \
	icu-dev \
	icu-libs \
	redis \
	mysql-client \
	bash \
	autoconf \
	g++ \
	make \
	openssl \
	&& pecl install redis \
	&& pecl clear-cache \
	&& docker-php-ext-configure intl \
	&& docker-php-ext-install intl pdo pdo_mysql xmlrpc \
	&& docker-php-ext-enable redis \
	&& pear install channel://pear.php.net/Net_IDNA2-0.1.1 Auth_SASL Net_IMAP NET_SMTP Net_IDNA2 Mail_mime \
	&& apk del autoconf g++ make libxml2-dev icu-dev

COPY ./docker-entrypoint.sh /

EXPOSE 9000

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["php-fpm"]
