FROM alpine:3.9
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ENV XTABLES_LIBDIR /usr/lib/xtables
ENV PYTHON_IPTABLES_XTABLES_VERSION 12
ENV IPTABLES_LIBDIR /usr/lib

RUN apk add --virtual .build-deps gcc python3-dev musl-dev libffi-dev openssl-dev \
  && apk add -U python3 iptables ip6tables tzdata \
  && pip3 install --upgrade python-iptables==0.13.0 redis ipaddress dnspython \
  && apk del .build-deps

COPY server.py /
CMD ["python3", "-u", "/server.py"]
