FROM alpine:3.16

LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

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
  redis 

RUN openssl req -x509 -newkey rsa:4096 -sha256 -days 3650 -nodes \
  -keyout /app/dockerapi_key.pem \
  -out /app/dockerapi_cert.pem \
  -subj /CN=dockerapi/O=mailcow \
  -addext subjectAltName=DNS:dockerapi

COPY dockerapi.py /app/
COPY async-dockerapi.py /app/

CMD ["uvicorn", "--host", "0.0.0.0", "--port", "443", "--ssl-certfile=/app/dockerapi_cert.pem", "--ssl-keyfile=/app/dockerapi_key.pem", "async-dockerapi:app"]
