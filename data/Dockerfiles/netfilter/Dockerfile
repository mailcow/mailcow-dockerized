FROM alpine:3.8
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ENV XTABLES_LIBDIR /usr/lib/xtables
ENV PYTHON_IPTABLES_XTABLES_VERSION 12
ENV IPTABLES_LIBDIR /usr/lib

RUN apk add -U python2 python-dev py-pip gcc musl-dev iptables ip6tables tzdata \
  && pip2 install --upgrade python-iptables==0.13.0 redis ipaddress \
  && apk del python-dev py2-pip gcc

COPY server.py /
CMD ["python2", "-u", "/server.py"]
