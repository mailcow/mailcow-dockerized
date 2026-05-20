ARG AGENT_IMAGE=ghcr.io/mailcow/agent:1.00

FROM ${AGENT_IMAGE} AS mailcow-agent-src

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

COPY --from=mailcow-agent-src /out/mailcow-agent /usr/local/bin/mailcow-agent
COPY --from=mailcow-agent-src /out/mailcow-agent-cli /usr/local/bin/mailcow-agent-cli

ENV MAILCOW_AGENT_SERVICE=nginx \
    MAILCOW_AGENT_MAIN_CMD="/docker-entrypoint.sh nginx -g 'daemon off;'"

HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
  CMD ["/usr/local/bin/mailcow-agent", "healthcheck"]

ENTRYPOINT ["/usr/local/bin/mailcow-agent"]
CMD []
