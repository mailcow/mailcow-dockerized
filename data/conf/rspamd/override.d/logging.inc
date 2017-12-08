type = "console";
systemd = false;
.include "$CONFDIR/logging.inc"
.include(try=true; priority=20) "$LOCAL_CONFDIR/override.d/logging.custom.inc"
