bind_socket = "*:11333";
task_timeout = 12s;
.include(try=true; priority=20) "$CONFDIR/override.d/worker-normal.custom.inc"
