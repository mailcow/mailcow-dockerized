FROM alpine:3.24

LABEL maintainer="The Infrastructure Company GmbH <info@servercow.de>"

ENV LANG=C.UTF-8
ENV LC_ALL=C.UTF-8
# MariaDB Connector/C >= 3.4 refuses DB servers without TLS, the runner connects through the socket
ENV MARIADB_TLS_DISABLE_PEER_VERIFICATION=1

RUN apk add --no-cache --update \
  ca-certificates \
  git \
  mariadb-client \
  openssh-client \
  py3-mysqlclient \
  py3-pynacl \
  python3 \
  restic \
  tini \
  tzdata \
  # dump, export, manifest and the mailcow directory are below /mailcow (paths of the snapshot), only root may read them
  && mkdir -m 700 /mailcow

COPY restic_runner.py /usr/local/bin/restic_runner.py
COPY sogo_restore.py /usr/local/bin/sogo_restore.py

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["/usr/bin/python3", "-u", "/usr/local/bin/restic_runner.py"]
