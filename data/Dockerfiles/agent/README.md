# mailcow-agent

Each mailcow service container (postfix, dovecot, …) runs `mailcow-agent` as
ENTRYPOINT. It supervises the original service main process and exposes its
control commands over a Redis Pub/Sub bus:

- `mailcow.control.<service>` — request channel (Backend → Agent)
- `mailcow.reply.<request_id>` — per-request reply channel
- `mailcow.events.<topic>` — broadcast events
- `mailcow.nodes.<service>` (ZSET) + `mailcow.node.<service>.<node_id>` (HASH) — heartbeat registry
- `mailcow.stats.<service>.<node_id>` (HASH) — per-node cpu/memory stats

Service behaviour is selected via `MAILCOW_AGENT_SERVICE=<service>`. The main
process command is configured via `MAILCOW_AGENT_MAIN_CMD` (string, executed via
`sh -c` so existing entrypoints/supervisord commands keep working).

