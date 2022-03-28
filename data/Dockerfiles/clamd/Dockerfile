FROM clamav/clamav:0.104.2-2_base

LABEL maintainer "André Peters <andre.peters@servercow.de>"

RUN apk upgrade --no-cache \
  && apk add --update --no-cache \
  rsync \
  bind-tools \
  bash 

COPY clamd.sh ./
RUN chmod +x /sbin/tini

ENTRYPOINT []
CMD ["/sbin/tini", "-g", "--", "/clamd.sh"]