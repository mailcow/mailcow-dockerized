FROM alpine:3.17

LABEL maintainer "The Infrastructure Company GmbH <info@servercow.de>"

WORKDIR /app

RUN apk add --update --no-cache python3 \
  py3-pip \
  openssl \
  tzdata \
  py3-psutil \
&& pip3 install --upgrade pip \
  fastapi \
  uvicorn \
  aiodocker \
  docker \
  redis 

COPY docker-entrypoint.sh /app/
COPY dockerapi.py /app/

ENTRYPOINT ["/bin/sh", "/app/docker-entrypoint.sh"]
