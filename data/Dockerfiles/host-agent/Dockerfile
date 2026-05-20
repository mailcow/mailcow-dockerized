# host-agent: dedicated container that reads /host/proc to publish host-level
# stats and answer exec.df / exec.host-stats commands. Reuses the same agent
# binary; behaviour selected via MAILCOW_AGENT_SERVICE=host.
#
# Requires:
#   volumes:
#     - /proc:/host/proc:ro
#     - /:/host/rootfs:ro

ARG AGENT_IMAGE=ghcr.io/mailcow/agent:1.0

FROM ${AGENT_IMAGE} AS agent

FROM alpine:3.20
RUN apk add --no-cache ca-certificates tzdata
COPY --from=agent /out/mailcow-agent /usr/local/bin/mailcow-agent
COPY --from=agent /out/mailcow-agent-cli /usr/local/bin/mailcow-agent-cli

ENV MAILCOW_AGENT_SERVICE=host

ENTRYPOINT ["/usr/local/bin/mailcow-agent"]
