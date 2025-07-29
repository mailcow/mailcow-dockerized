#!/bin/bash

docker compose exec -it controller-mailcow python3 /app/mailcow-adm/mailcow-adm.py "$@"
