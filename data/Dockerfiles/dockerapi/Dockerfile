FROM alpine:3.9
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

WORKDIR /app

RUN apk add --update --no-cache python3 openssl tzdata \
 && pip3 install --upgrade pip \
 && pip3 install --upgrade docker flask flask-restful

COPY server.py /app/

CMD ["python3", "-u", "/app/server.py"]
