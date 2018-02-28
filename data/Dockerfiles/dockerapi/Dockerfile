FROM python:2-alpine
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

RUN apk add -U --no-cache iptables ip6tables
RUN pip install docker==3.0.1 flask flask-restful

COPY server.py /
CMD ["python2", "-u", "/server.py"]
