FROM alpine:3.7
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

RUN apk add -U python2 python-dev py-pip gcc musl-dev iptables ip6tables \
  && pip2 install --upgrade python-iptables redis ipaddress \
  && apk del python-dev py2-pip gcc

COPY server.py /
CMD ["python2", "-u", "/server.py"]
