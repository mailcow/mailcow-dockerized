FROM alpine:3.9
LABEL maintainer "Andre Peters <andre.peters@servercow.de>"

WORKDIR /app

RUN apk add --virtual .build-deps gcc python3-dev musl-dev libffi-dev openssl-dev \
  && apk add --update --no-cache python3 openssl tzdata libmagic \
  && pip3 install --upgrade pip \
  && pip3 install --upgrade oletools asyncio python-magic \
  && apk del .build-deps

COPY olefy.py /app/

CMD ["python3", "-u", "/app/olefy.py"]
