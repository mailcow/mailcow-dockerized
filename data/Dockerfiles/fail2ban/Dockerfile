FROM python:2-alpine
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

RUN apk add -U --no-cache iptables ip6tables
RUN pip install docker

COPY logwatch.py /
CMD ["python2", "-u", "/logwatch.py"]
