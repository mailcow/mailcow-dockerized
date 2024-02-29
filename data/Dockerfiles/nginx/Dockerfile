FROM nginx:alpine
LABEL maintainer "The Infrastructure Company GmbH <info@servercow.de>"


RUN apk add --no-cache nginx \
  python3 \
  py3-pip && \
  pip install --upgrade pip && \
  pip install Jinja2

RUN mkdir -p /etc/nginx/includes

COPY bootstrap-nginx.py /bootstrap-nginx.py

CMD ["python3", "-u", "/bootstrap-nginx.py"]
