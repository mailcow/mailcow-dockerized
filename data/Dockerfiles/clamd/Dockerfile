FROM alpine:3.17

LABEL maintainer "The Infrastructure Company <info@servercow.de>"

RUN apk update && apk add clamav tini bash

COPY clamd.sh ./

CMD ["/sbin/tini", "-g", "--", "/clamd.sh"]