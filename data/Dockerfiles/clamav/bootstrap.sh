#!/bin/bash
# bootstrap clam av service and clam av database updater shell script
# presented by mko (Markus Kosmal<code@cnfg.io>)
set -m

# start clam service itself and the updater in background as daemon
freshclam -d &
clamd &

# recognize PIDs
pidlist=`jobs -p`

# initialize latest result var
latest_exit=0

# define shutdown helper
function shutdown() {
    trap "" SUBS

    for single in $pidlist; do
        if ! kill -0 $pidlist 2>/dev/null; then
            wait $pidlist
            exitcode=$?
        fi
    done

    kill $pidlist 2>/dev/null
}

# run shutdown
trap terminate SUBS
wait

# return received result
exit $latest_exit
