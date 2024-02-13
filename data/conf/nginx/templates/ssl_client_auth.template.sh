apk add mariadb-client

# List client CA of all domains
CA_LIST="/etc/nginx/conf.d/client_cas.crt"
> "$CA_LIST"

# Define your SQL query
query="SELECT DISTINCT ssl_client_ca FROM domain WHERE ssl_client_ca IS NOT NULL;"
result=$(mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "$query" -B -N)
if [ -n "$result" ]; then
    echo "$result" | while IFS= read -r line; do
        echo -e "$line"
    done > $CA_LIST
    #tail -n 1 "$CA_LIST" | wc -c | xargs -I {} truncate "$CA_LIST" -s -{}
    echo "
ssl_verify_client      optional;
ssl_client_certificate /etc/nginx/conf.d/client_cas.crt;
" > /etc/nginx/conf.d/includes/ssl_client_auth.conf
    echo "SSL client CAs have been appended to $CA_LIST"
else
    > /etc/nginx/conf.d/includes/ssl_client_auth.conf
    echo "No SSL client CAs found"
fi
