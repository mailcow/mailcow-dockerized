FROM alpine:3.9
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ENV XTABLES_LIBDIR /usr/lib/xtables
ENV PYTHON_IPTABLES_XTABLES_VERSION 12
ENV IPTABLES_LIBDIR /usr/lib

RUN apk add -U python3 python3-dev gcc musl-dev iptables ip6tables tzdata \
  && pip3 install --upgrade python-iptables==0.13.0 redis ipaddress dnspython \
  && apk del python3-dev gcc

COPY server.py /
CMD ["python2", "-u", "/server.py"]
