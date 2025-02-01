# This dockerfile builds a custom caddy process, configured with optional 
# modules for caddy-uwsgi-transport (for mailman) and caddy-events-exec for 
# reloading dovecot/postfix when new certificates are issued
# to build, do 'docker compose build' before running 'docker compose up -d'
ARG CADDY_VERSION=2.9.1
FROM caddy:${CADDY_VERSION}-builder AS builder

RUN xcaddy build \
    --with github.com/BadAimWeeb/caddy-uwsgi-transport \
    --with github.com/mholt/caddy-events-exec

FROM caddy:${CADDY_VERSION}-alpine
RUN apk add jq
COPY --from=builder /usr/bin/caddy /usr/bin/caddy
