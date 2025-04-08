FROM alpine:3.20

LABEL maintainer = "The Infrastructure Company GmbH <info@servercow.de>"

WORKDIR /app

ARG PIP_BREAK_SYSTEM_PACKAGES=1
ENV XTABLES_LIBDIR /usr/lib/xtables
ENV PYTHON_IPTABLES_XTABLES_VERSION 12
ENV IPTABLES_LIBDIR /usr/lib

RUN apk add --virtual .build-deps \
  gcc \
  python3-dev \
  libffi-dev \
  openssl-dev \
&& apk add -U python3 \
  iptables \
  iptables-dev \
  ip6tables \
  xtables-addons \
  nftables \
  tzdata \
  py3-pip \
  py3-nftables \
  musl-dev \
&& pip3 install --ignore-installed --upgrade pip \
  jsonschema \
  python-iptables \
  redis \
  ipaddress \
  dnspython \
&& apk del .build-deps

#  && pip3 install --upgrade pip python-iptables==0.13.0 redis ipaddress dnspython \

COPY modules /app/modules
COPY main.py /app/
COPY ./docker-entrypoint.sh /app/

RUN chmod +x /app/docker-entrypoint.sh

CMD ["/bin/sh", "-c", "/app/docker-entrypoint.sh"]