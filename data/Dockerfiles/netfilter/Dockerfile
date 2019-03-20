FROM alpine:3.9
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

ENV F2B_BAN_TIME_DEFAULT 1800
ENV F2B_MAX_ATTEMPTS_DEFAULT 10
ENV F2B_RETRY_WINDOW_DEFAULT 600
ENV F2B_NETBAN_IPV4_DEFAULT 24
ENV F2B_NETBAN_IPV6_DEFAULT 64
ENV F2B_WHITELIST_DEFAULT ""
ENV F2B_BLACKLIST_DEFAULT ""

ENV XTABLES_LIBDIR /usr/lib/xtables
ENV PYTHON_IPTABLES_XTABLES_VERSION 12
ENV IPTABLES_LIBDIR /usr/lib

RUN apk add -U python2 python-dev py-pip gcc musl-dev iptables ip6tables tzdata \
  && pip2 install --upgrade python-iptables==0.13.0 redis ipaddress \
  && apk del python-dev py2-pip gcc

COPY server.py /
CMD ["python2", "-u", "/server.py"]
