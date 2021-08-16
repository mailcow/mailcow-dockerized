bind_socket = "*:11333";
task_timeout = 25s;
count = 1;
.include(try=true; priority=30) "$CONFDIR/override.d/worker-normal.custom.inc"
