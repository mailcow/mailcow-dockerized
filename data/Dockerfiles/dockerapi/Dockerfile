FROM alpine:3.9
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

WORKDIR /app

RUN apk add --update --no-cache python3 openssl tzdata \
 && apk add --update --no-cache --virtual .build-dependencies gcc python3-dev musl-dev libffi-dev openssl-dev \
 && pip3 install --upgrade pip \
 && pip3 install --upgrade docker flask flask-restful pyOpenSSL \
 && apk del .build-dependencies

COPY server.py /app/

CMD ["python3", "-u", "/app/server.py"]
