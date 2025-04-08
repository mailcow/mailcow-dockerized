FROM nginx:alpine
LABEL maintainer "The Infrastructure Company GmbH <info@servercow.de>"

ENV PIP_BREAK_SYSTEM_PACKAGES=1

RUN apk add --no-cache nginx \
  python3 \
  py3-pip && \
  pip install --upgrade pip && \
  pip install Jinja2

RUN mkdir -p /etc/nginx/includes

COPY ./bootstrap.py /
COPY ./docker-entrypoint.sh /

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]
