FROM alpine:3.8
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

RUN apk add -U --no-cache python2 python-dev py-pip gcc musl-dev tzdata openssl-dev libffi-dev \
  && pip2 install --upgrade docker==3.0.1 flask flask-restful pyOpenSSL \
  && apk del python-dev py2-pip gcc

COPY server.py /

CMD ["python2", "-u", "/server.py"]
