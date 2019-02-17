FROM alpine:3.9
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

WORKDIR /app

RUN apk add -U --no-cache python2 tzdata openssl \
        python-dev gcc musl-dev openssl-dev libffi-dev py-pip \
    && pip2 install --upgrade pip \
    && pip2 install --upgrade docker flask flask-restful pyOpenSSL \
    && apk del python-dev gcc musl-dev openssl-dev libffi-dev py-pip

COPY server.py /

CMD ["python2", "-u", "/server.py"]
