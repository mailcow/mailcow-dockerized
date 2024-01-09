FROM alpine:3.19

LABEL maintainer "The Infrastructure Company GmbH <info@servercow.de>"

ARG PIP_BREAK_SYSTEM_PACKAGES=1
WORKDIR /app

RUN apk add --update --no-cache python3 \
  py3-pip \
  openssl \
  tzdata \
  py3-psutil \
  py3-redis \
  py3-async-timeout \
&& pip3 install --upgrade pip \
  fastapi \
  uvicorn \
  aiodocker \
  docker
RUN mkdir /app/modules

COPY docker-entrypoint.sh /app/
COPY main.py /app/main.py
COPY modules/ /app/modules/

ENTRYPOINT ["/bin/sh", "/app/docker-entrypoint.sh"]
CMD exec python main.py