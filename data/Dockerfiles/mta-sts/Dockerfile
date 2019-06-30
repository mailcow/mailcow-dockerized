FROM python:3-slim
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

RUN pip3 install --no-cache-dir uvloop postfix-mta-sts-resolver[redis,sqlite]
COPY config.yml /etc/mta-sts-daemon.yml

VOLUME [ "/var/lib/mta-sts" ]
EXPOSE 8461/tcp
ENTRYPOINT [ "mta-sts-daemon" ]