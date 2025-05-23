FROM nginx:alpine
LABEL maintainer "The Infrastructure Company GmbH <info@servercow.de>"

ENV PIP_BREAK_SYSTEM_PACKAGES=1

RUN apk add --no-cache nginx \
  python3 py3-pip \
  supervisor

RUN apk add --no-cache --virtual .build-deps \
      gcc \
      musl-dev \
      python3-dev \
      linux-headers \
  && pip install --break-system-packages psutil \
  && apk del .build-deps

RUN pip install  --break-system-packages \
  mysql-connector-python \
  jinja2 \
  redis \
  dnspython

RUN mkdir -p /etc/nginx/includes


COPY data/Dockerfiles/bootstrap /bootstrap
COPY data/Dockerfiles/nginx/docker-entrypoint.sh /
COPY data/Dockerfiles/nginx/supervisord.conf /etc/supervisor/supervisord.conf
COPY data/Dockerfiles/nginx/stop-supervisor.sh /usr/local/sbin/stop-supervisor.sh

RUN chmod +x /docker-entrypoint.sh
RUN chmod +x /usr/local/sbin/stop-supervisor.sh


CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
