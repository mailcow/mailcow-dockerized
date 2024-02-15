FROM alpine:3.19

LABEL maintainer "The Infrastructure Company GmbH <info@servercow.de>"

RUN apk upgrade --no-cache \
  && apk add --update --no-cache \
  rsync \
  clamav \
  bind-tools \
  bash \
  tini

# init
COPY clamd.sh /clamd.sh
RUN chmod +x /sbin/tini

# healthcheck
COPY healthcheck.sh /healthcheck.sh
COPY clamdcheck.sh /usr/local/bin
RUN chmod +x /healthcheck.sh
RUN chmod +x /usr/local/bin/clamdcheck.sh
HEALTHCHECK --start-period=6m CMD "/healthcheck.sh"

ENTRYPOINT []
CMD ["/sbin/tini", "-g", "--", "/clamd.sh"]