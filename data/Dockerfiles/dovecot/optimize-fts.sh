#!/bin/bash

if [[ "${SKIP_SOLR}" =~ ^([yY][eE][sS]|[yY])+$ && ! "${FLATCURVE_EXPERIMENTAL}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    exit 0
else
    doveadm fts optimize -A
fi