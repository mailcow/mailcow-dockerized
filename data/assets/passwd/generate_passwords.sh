#!/bin/bash

echo DBPASS=$(openssl rand -base64 32 | tr -dc _A-Z-a-z-0-9)
echo DBROOT=$(openssl rand -base64 32 | tr -dc _A-Z-a-z-0-9)
