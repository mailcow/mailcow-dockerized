FROM mariadb:10.11

LABEL maintainer = "The Infrastructure Company GmbH <info@servercow.de>"


RUN apt-get update && \
  apt-get install -y --no-install-recommends \
    python3 \
    python3-pip \
    gosu \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

RUN pip install \
  mysql-connector-python \
  jinja2 \
  redis \
  dnspython \
  psutil


COPY data/Dockerfiles/bootstrap /bootstrap
COPY data/Dockerfiles/mariadb/docker-entrypoint.sh /docker-entrypoint.sh

RUN chmod +x /docker-entrypoint.sh


ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["mysqld"]
