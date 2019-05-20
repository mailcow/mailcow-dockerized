FROM alpine:3.9
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

WORKDIR /app

RUN apk add -U --no-cache python3 tzdata python3-dev gcc musl-dev openssl-dev libffi-dev openssl\
  && pip3 install --upgrade pip \
  && pip3 install --upgrade docker flask flask-restful pyOpenSSL \
  && apk del python3-dev gcc musl-dev openssl-dev libffi-dev

COPY server.py /

CMD ["python3", "-u", "/server.py"]
