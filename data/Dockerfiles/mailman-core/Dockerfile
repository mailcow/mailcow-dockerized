FROM python:3.6-alpine

LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ENV LC_ALL=C.UTF-8
ENV LANG=C.UTF-8

ENV MAILMAN_CONFIG_FILE /etc/mailman.cfg

RUN apk update \
    && apk add --virtual build-deps gcc python3-dev musl-dev postgresql-dev \
    && apk add bash su-exec mysql-client curl supervisor \
    && pip install mailman==3.1.1 \
                   mailman-hyperkitty==1.1.0 \
                   pymysql \
    && apk del build-deps \
    && addgroup -S mailman \
    && adduser -S -G mailman mailman

COPY docker-entrypoint.sh /
COPY supervisord.conf /etc/supervisord.conf

WORKDIR /opt/mailman

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD exec /usr/bin/supervisord -c /etc/supervisord.conf
