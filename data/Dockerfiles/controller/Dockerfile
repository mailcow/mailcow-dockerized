FROM alpine:3.21

LABEL maintainer = "The Infrastructure Company GmbH <info@servercow.de>"

ARG PIP_BREAK_SYSTEM_PACKAGES=1
WORKDIR /app

RUN apk add --update --no-cache python3 \
  bash \
  py3-pip \
  openssl \
  tzdata \
  py3-psutil \
  py3-redis \
  py3-async-timeout \
  supervisor \
  curl \
&& pip3 install --upgrade pip \
  fastapi \
  uvicorn \
  aiodocker \
  docker

COPY mailcow-adm/ /app/mailcow-adm/
RUN pip3 install -r /app/mailcow-adm/requirements.txt

COPY api/ /app/api/

COPY docker-entrypoint.sh /app/
COPY supervisord.conf /etc/supervisor/supervisord.conf
COPY stop-supervisor.sh /usr/local/sbin/stop-supervisor.sh

ENTRYPOINT ["/bin/sh", "/app/docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
