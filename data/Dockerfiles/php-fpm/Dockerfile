FROM alpine:3.6

LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

# Add script
COPY docker-entrypoint.sh /

# Add group + user - 82 is the standard uid/gid for "www-data" in Alpine
RUN set -x \
&& addgroup -g 82 -S www-data \
&& adduser -u 82 -D -S -G www-data www-data \
\
# Install Dependencies 
&& apk update \
&& apk add --no-cache \
  bash \
  curl \
  libressl \
  mariadb-client \
  redis \
  php7-fpm \
  php7-common \
  php7-ctype \
  php7-curl \
  php7-intl \
  php7-json \
  php7-openssl \
  php7-pdo \
  php7-pdo_mysql \
  php7-pear \
  php7-pear-auth_sasl \
  php7-pear-mail_mime \
  php7-pear-net_smtp \
  php7-pear-net_idna2 \
  php7-redis \
  php7-xmlrpc \
&& pear install Net_IMAP \
# MISSING apk for php7-pear-net_imap - can be installed once https://github.com/alpinelinux/aports/pull/1359 is merged.
\
# Configuring php-fpm
&& set -ex \
&& cd /etc/php7/ \
# Change the setting so the daemon runs in the foreground and as www-data:www-data
&& sed -i 's/^user = .*/user = www-data/' php-fpm.d/www.conf \
&& sed -i 's/^group = .*/group = www-data/' php-fpm.d/www.conf \
# Include PEAR and other libs
&& sed -i 's/;include_path = ".:\/php\/includes"/include_path = ".:\/usr\/share\/php7"/g' php.ini \
# Uncomment line bebelow for diagnostics capabilities
#&& sed -i 's/^;daemonize .*$/daemonize = no/g' php-fpm.conf \
#&& sed -i 's/^display_errors = .*/display_errors = On/' php.ini \
#&& sed -i 's/^display_startup_errors = .*/display_startup_errors = On/' php.ini \
#&& sed -i 's/^short_open_tag = .*/short_open_tag = On/' php.ini \
#&& sed -i 's/^register_argc_argv = .*/register_argc_argv = On/' php.ini \
#&& sed -i 's/^error_reporting = .*/error_reporting = -1/' php.ini \
&& { \
    echo '[global]'; \
    echo 'error_log = /proc/self/fd/2'; \
    echo; \
    echo '[www]'; \
    echo '; if we send this to /proc/self/fd/1, it never appears'; \
    echo 'access.log = /proc/self/fd/2'; \
    echo; \
    echo 'clear_env = no'; \
    echo; \
    echo '; Ensure worker stdout and stderr are sent to the main error log.'; \
    echo 'catch_workers_output = yes'; \
} | tee php-fpm.d/docker.conf \
&& { \
    echo '[global]'; \
    echo 'daemonize = no'; \
    echo; \
    echo '[www]'; \
    echo 'listen = [::]:9000'; \
} | tee php-fpm.d/zz-docker.conf

EXPOSE 9000

# Time to milk the cows ;)
ENTRYPOINT ["/docker-entrypoint.sh"]
WORKDIR /var/www/html
CMD ["php-fpm7"]
