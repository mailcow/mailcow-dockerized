FROM alpine:3.9
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

WORKDIR /app

RUN apk add -U --no-cache python3 tzdata openssl \
 && apk add --no-cache --virtual build-dependencies python3-dev gcc musl-dev openssl-dev libffi-dev \
 && python3 -m pip install --upgrade pip \
 && python3 -m pip install --upgrade docker flask flask-restful pyOpenSSL \
 && apk del build-dependencies

COPY server.py /

CMD ["python3", "-u", "/server.py"]
