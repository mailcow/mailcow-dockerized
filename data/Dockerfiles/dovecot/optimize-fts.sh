#!/bin/bash

if [[ "${SKIP_FLATCURVE}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    exit 0
else
    doveadm fts optimize -A
fi
