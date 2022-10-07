FROM alpine:3.16
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

WORKDIR /app

#RUN addgroup -S olefy && adduser -S olefy -G olefy \
RUN apk add --virtual .build-deps gcc musl-dev python3-dev libffi-dev openssl-dev cargo \
  && apk add --update --no-cache python3 py3-pip openssl tzdata libmagic \
  && pip3 install --upgrade pip \
  && pip3 install --upgrade asyncio python-magic \
  && pip3 install --upgrade https://github.com/decalage2/oletools/archive/master.zip \
  && apk del .build-deps
#  && sed -i 's/template_injection_detected = True/template_injection_detected = False/g' /usr/lib/python3.9/site-packages/oletools/olevba.py

ADD olefy.py /app/

RUN chown -R nobody:nobody /app /tmp

USER nobody

CMD ["python3", "-u", "/app/olefy.py"]
