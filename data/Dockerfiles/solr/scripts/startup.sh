#!/bin/bash
#
# This script starts Solr on localhost, creates a core with "solr create"

set -e
echo "Executing $0 $@"

# allow easier debugging with `docker run -e VERBOSE=yes`
if [[ "$VERBOSE" = "yes" ]]; then
    set -x
fi

# run the optional initdb
. /opt/docker-solr/scripts/run-initdb

# keep a sentinel file so we don't try to create the core a second time
# for example when we restart a container.
sentinel=/opt/docker-solr/core_created
if [ -f $sentinel ]; then
    echo "skipping core creation"
else
    # start a Solr so we can use the Schema API, but only on localhost,
    # so that clients don't see Solr until we have configured it.
    start-local-solr

    echo "Creating core with: ${@:1}"
    /opt/solr/bin/solr create "${@:1}"

    # See https://github.com/docker-solr/docker-solr/issues/27
    echo "Checking core"
    if ! wget -O - 'http://localhost:8983/solr/admin/cores?action=STATUS' | grep -q instanceDir; then
	echo "Could not find any cores"
	exit 1
    fi

    echo "Created core with: ${@:1}"
    echo "Configuring schema"
    curl -X POST -H 'Content-type:application/json' --data-binary '{
    "add-field-type":
    {
	"name": "long",
	"class": "solr.TrieLongField"
    },
    "add-field-type":{
	"name": "text",
	"class": "solr.TextField",
	"positionIncrementGap": 100,
	"indexAnalyser": {
	    "tokenizer": {
		"class": "solr.StandardTokenizerFactory"
	    },
	    "filter": {
		"class": "solr.WordDelimiterFilterFactory",
		"generateWordParts": 1,
		"generateNumberParts": 1,
		"catenateWorks": 1,
		"catenateNumbers": 1,
		"catenateAll": 0
	    },
	    "filter": {
		"class": "solr.LowerCaseFilterFactory"
	    },
	    "filter": {
		"class": "solr.KeywordMarkerFilterFactory",
		"protected": "protwords.txt"
	    }
	},
	"queryAnalyzer":{
	    "tokenizer":{
		"class": "solr.StandardTokenizerFactory"
	    },
	    "filter": {
		"synonyms": "synonyms.txt",
		"ignoreCase": true,
		"expand": true
	    },
	    "filter": {
		"class": "solr.LowerCaseFilterFactory"
	    },
	    "filter": {
		"class": "solr.WordDelimiterFilterFactory",
		"generateWordParts": 1,
		"generateNumberParts": 1,
		"catenateWords": 0,
		"catenateNumbers": 0,
		"catenateAll": 0,
		"splitOnCaseChange": 1
	    }
	}
    },
    "add-field":
    {
	"name": "uid",
	"type": "long",
	"indexed": true,
	"stored": true,
	"required": true
    },
    "add-field":
    {
	"name": "box",
	"type": "string",
	"indexed": true,
	"stored": true,
	"required": true
    },
    "add-field":
    {
	"name": "user",
	"type": "string",
	"indexed": true,
	"stored": true,
	"required": true
    },
    "add-field":
    {
	"name": "hdr",
	"type": "text",
	"indexed": true,
	"stored": false,
    },
    "add-field":
    {
	"name": "body",
	"type": "text",
	"indexed": true,
	"stored": false
    },
    "add-field":
    {
	"name": "from",
	"type": "text",
	"indexed": true,
	"stored": false
    },
    "add-field":
    {
	"name": "to",
	"type": "text",
	"indexed": true,
	"stored": false
    },
    "add-field":
    {
	"name": "cc",
	"type": "text",
	"indexed": true,
	"stored": false
    },
    "add-field":
    {
	"name": "bcc",
	"type": "text",
	"indexed": true,
	"stored": false
    },
    "add-field":
    {
	"name": "subject",
	"type": "text",
	"indexed": true,
	"stored": false
    }
}' http://localhost:8983/solr/dovecot/schema

    curl http://localhost:8983/solr/dovecot/config -H 'Content-type:application/json'  -d '{
"update-requesthandler" : {
    "name": "/select",
    "class": "solr.SearchHandler",
    "defaults": {"wt": "xml"}
  }
}'

    stop-local-solr
    touch $sentinel
fi
