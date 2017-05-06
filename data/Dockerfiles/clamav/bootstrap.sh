#!/bin/bash
trap "kill 0" SIGINT

freshclam -d &
clamd &

sleep inf
