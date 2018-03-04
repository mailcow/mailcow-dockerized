FROM python:2.7-alpine3.7

LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ENV LC_ALL=C.UTF-8
ENV LANG=C.UTF-8

ENV MAILMAN_CONFIG_FILE /etc/mailman.cfg

RUN set -ex \
  && apk add --no-cache --virtual .build-deps gcc libc-dev linux-headers mariadb-dev \
  && apk add --no-cache --virtual .mailman-rundeps bash sassc su-exec mysql-client py-mysqldb curl \
  && pip install -U \
    mailmanclient==3.1.1 \
    postorius==1.1.2 \
    hyperkitty==1.1.4 \
    django-mailman3==1.1.0 \
    whoosh \
    uwsgi \
    dj-database-url \
    mysqlclient \
    django==1.11 \
    pysha3 \
  && apk del .build-deps \
  && addgroup -S mailman \
  && adduser -S -G mailman mailman

COPY mm_web /opt/mm_web
COPY docker-entrypoint.sh /

WORKDIR /opt/mm_web

ENTRYPOINT ["/docker-entrypoint.sh"]

VOLUME ["/opt/mm_web-data"]

CMD ["uwsgi", "--ini", "/opt/mm_web/uwsgi.ini"]
