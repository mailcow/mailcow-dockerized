# Builder image for mailcow-agent. Each service Dockerfile pulls the static
# binary from here via:
#
#   COPY --from=ghcr.io/mailcow/agent:VERSION /out/mailcow-agent /usr/local/bin/mailcow-agent
#
# For local development: build this image first.
#
#   docker build -t ghcr.io/mailcow/agent:dev data/Dockerfiles/agent/
#
# CI publishes a versioned tag and the service Dockerfiles pin against it via
# ARG AGENT_IMAGE.

FROM golang:1.22-alpine AS build

ENV CGO_ENABLED=0 \
    GOOS=linux

WORKDIR /src

COPY go.mod go.sum* ./
RUN go mod download

COPY . .

RUN mkdir -p /out \
 && go build -trimpath -ldflags="-s -w" \
    -o /out/mailcow-agent ./cmd/mailcow-agent \
 && cp mailcow-agent-cli /out/mailcow-agent-cli \
 && chmod +x /out/mailcow-agent-cli

# Final stage: tiny image whose only purpose is to be a COPY --from source.
FROM scratch
COPY --from=build /out/mailcow-agent /out/mailcow-agent
COPY --from=build /out/mailcow-agent-cli /out/mailcow-agent-cli
