ARG AGENT_IMAGE=ghcr.io/mailcow/agent:1.00

FROM ${AGENT_IMAGE} AS mailcow-agent-src

FROM alpine:3.21

LABEL maintainer = "The Infrastructure Company GmbH <info@servercow.de>"

ARG PIP_BREAK_SYSTEM_PACKAGES=1
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

COPY --from=mailcow-agent-src /out/mailcow-agent /usr/local/bin/mailcow-agent
COPY --from=mailcow-agent-src /out/mailcow-agent-cli /usr/local/bin/mailcow-agent-cli

USER nobody

ENV MAILCOW_AGENT_SERVICE=olefy \
    MAILCOW_AGENT_MAIN_CMD="python3 -u /app/olefy.py"

HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
  CMD ["/usr/local/bin/mailcow-agent", "healthcheck"]

ENTRYPOINT ["/usr/local/bin/mailcow-agent"]
CMD []
