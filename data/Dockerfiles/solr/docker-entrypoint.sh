#!/bin/bash

if [[ "${SKIP_SOLR}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
  echo "SKIP_SOLR=y, skipping Solr..."
  sleep 365d
  exit 0
fi

MEM_TOTAL=$(awk '/MemTotal/ {print $2}' /proc/meminfo)

if [[ "${1}" != "--bootstrap" ]]; then
  if [ ${MEM_TOTAL} -lt "2097152" ]; then
    echo "System memory less than 2 GB, skipping Solr..."
    sleep 365d
    exit 0
  fi
fi

set -e

# allow easier debugging with `docker run -e VERBOSE=yes`
if [[ "$VERBOSE" = "yes" ]]; then
  set -x
fi

# run the optional initdb
. /opt/docker-solr/scripts/run-initdb

function solr_config() {
  curl -XPOST http://localhost:8983/solr/dovecot/schema -H 'Content-type:application/json' -d '{
    "add-field-type":{
      "name":"long",
      "class":"solr.TrieLongField"
    },
    "add-field-type":{
      "name":"dovecot_text",
      "class":"solr.TextField",
      "autoGeneratePhraseQueries":true,
      "positionIncrementGap":100,
      "indexAnalyser":{
        "charFilter":{
          "class":"solr.MappingCharFilterFactory",
          "mapping":"mapping-FoldToASCII.txt"
        },
        "charFilter":{
          "class":"solr.MappingCharFilterFactory",
          "mapping":"mapping-ISOLatin1Accent.txt"
        },
        "charFilter":{
          "class":"solr.HTMLStripCharFilterFactory"
        },
        "tokenizer":{
          "class":"solr.StandardTokenizerFactory"
        },
        "filter":{
          "class":"solr.StopFilterFactory",
          "words":"stopwords.txt",
          "ignoreCase":true
        },
        "filter":{
          "class":"solr.WordDelimiterGraphFilterFactory",
          "generateWordParts":1,
          "generateNumberParts":1,
          "splitOnCaseChange":1,
          "splitOnNumerics":1,
          "catenateWords":1,
          "catenateNumbers":1,
          "catenateAll":1
        },
        "filter":{
          "class":"solr.FlattenGraphFilterFactory"
        },
        "filter":{
          "class":"solr.LowerCaseFilterFactory"
        },
        "filter":{
          "class":"solr.KeywordMarkerFilterFactory",
          "protected":"protwords.txt"
        },
        "filter":{
          "class":"solr.PorterStemFilterFactory"
        }
      },
      "queryAnalyzer":{
        "tokenizer":{
          "class":"solr.StandardTokenizerFactory"
        },
        "filter":{
          "class":"solr.SynonymGraphFilterFactory",
          "expand":true,
          "ignoreCase":true,
          "synonyms":synonyms.txt
        },
        "filter":{
          "class":"solr.FlattenGraphFilterFactory"
        },
        "filter":{
          "class":"solr.StopFilterFactory",
          "words":"stopwords.txt",
          "ignoreCase":true
        },
        "filter":{
          "class":"solr.WordDelimiterGraphFilterFactory",
          "generateWordParts":1,
          "generateNumberParts":1,
          "splitOnCaseChange":1,
          "splitOnNumerics":1,
          "catenateWords":1,
          "catenateNumbers":1,
          "catenateAll":1
        },
        "filter":{
          "class":"solr.LowerCaseFilterFactory"
        },
        "filter":{
          "class":"solr.KeywordMarkerFilterFactory",
          "protected":"protwords.txt"
        },
        "filter":{
          "class":"solr.PorterStemFilterFactory"
        }
      }
    },
    "add-field":{
      "name":"uid",
      "type":"long",
      "indexed":true,
      "stored":true,
      "required":true
    },
    "add-field":{
      "name":"box",
      "type":"string",
      "indexed":true,
      "stored":true,
      "required":true
    },
    "add-field":{
      "name":"user",
      "type":"string",
      "indexed":true,
      "stored":true,
      "required":true
    },
    "add-field":{
      "name":"hdr",
      "type":"dovecot_text",
      "indexed":true,
      "stored":false

    },
    "add-field":{
      "name":"body",
      "type":"dovecot_text",
      "indexed":true,
      "stored":false
    },
    "add-field":{
      "name":"from",
      "type":"dovecot_text",
      "indexed":true,
      "stored":false
    },
    "add-field":{
      "name":"to",
      "type":"dovecot_text",
      "indexed":true,
      "stored":false
    },
    "add-field":{
      "name":"cc",
      "type":"dovecot_text",
      "indexed":true,
      "stored":false
    },
    "add-field":{
      "name":"bcc",
      "type":"dovecot_text",
      "indexed":true,
      "stored":false
    },
    "add-field":{
      "name":"subject",
      "type":"dovecot_text",
      "indexed":true,
      "stored":false
    }
  }'

  curl -XPOST http://localhost:8983/solr/dovecot/schema -H 'Content-type:application/json' -d '{
    "replace-field-type":{
      "name":"long",
      "class":"solr.TrieLongField"
    },
    "replace-field-type":{
      "name":"dovecot_text",
      "class":"solr.TextField",
      "autoGeneratePhraseQueries":true,
      "positionIncrementGap":100,
      "indexAnalyser":{
        "charFilter":{
          "class":"solr.MappingCharFilterFactory",
          "mapping":"mapping-FoldToASCII.txt"
        },
        "charFilter":{
          "class":"solr.MappingCharFilterFactory",
          "mapping":"mapping-ISOLatin1Accent.txt"
        },
        "charFilter":{
          "class":"solr.HTMLStripCharFilterFactory"
        },
        "tokenizer":{
          "class":"solr.StandardTokenizerFactory"
        },
        "filter":{
          "class":"solr.StopFilterFactory",
          "words":"stopwords.txt",
          "ignoreCase":true
        },
        "filter":{
          "class":"solr.WordDelimiterGraphFilterFactory",
          "generateWordParts":1,
          "generateNumberParts":1,
          "splitOnCaseChange":1,
          "splitOnNumerics":1,
          "catenateWords":1,
          "catenateNumbers":1,
          "catenateAll":1
        },
        "filter":{
          "class":"solr.FlattenGraphFilterFactory"
        },
        "filter":{
          "class":"solr.LowerCaseFilterFactory"
        },
        "filter":{
          "class":"solr.KeywordMarkerFilterFactory",
          "protected":"protwords.txt"
        },
        "filter":{
          "class":"solr.PorterStemFilterFactory"
        }
      },
      "queryAnalyzer":{
        "tokenizer":{
          "class":"solr.StandardTokenizerFactory"
        },
        "filter":{
          "class":"solr.SynonymGraphFilterFactory",
          "expand":true,
          "ignoreCase":true,
          "synonyms":synonyms.txt
        },
        "filter":{
          "class":"solr.FlattenGraphFilterFactory"
        },
        "filter":{
          "class":"solr.StopFilterFactory",
          "words":"stopwords.txt",
          "ignoreCase":true
        },
        "filter":{
          "class":"solr.WordDelimiterGraphFilterFactory",
          "generateWordParts":1,
          "generateNumberParts":1,
          "splitOnCaseChange":1,
          "splitOnNumerics":1,
          "catenateWords":1,
          "catenateNumbers":1,
          "catenateAll":1
        },
        "filter":{
          "class":"solr.LowerCaseFilterFactory"
        },
        "filter":{
          "class":"solr.KeywordMarkerFilterFactory",
          "protected":"protwords.txt"
        },
        "filter":{
          "class":"solr.PorterStemFilterFactory"
        }
      }
    },
    "replace-field":{
      "name":"uid",
      "type":"long",
      "indexed":true,
      "stored":true,
      "required":true
    },
    "replace-field":{
      "name":"box",
      "type":"string",
      "indexed":true,
      "stored":true,
      "required":true
    },
    "replace-field":{
      "name":"user",
      "type":"string",
      "indexed":true,
      "stored":true,
      "required":true
    },
    "replace-field":{
      "name":"hdr",
      "type":"dovecot_text",
      "indexed":true,
      "stored":false

    },
    "replace-field":{
      "name":"body",
      "type":"dovecot_text",
      "indexed":true,
      "stored":false
    },
    "replace-field":{
      "name":"from",
      "type":"dovecot_text",
      "indexed":true,
      "stored":false
    },
    "replace-field":{
      "name":"to",
      "type":"dovecot_text",
      "indexed":true,
      "stored":false
    },
    "replace-field":{
      "name":"cc",
      "type":"dovecot_text",
      "indexed":true,
      "stored":false
    },
    "replace-field":{
      "name":"bcc",
      "type":"dovecot_text",
      "indexed":true,
      "stored":false
    },
    "replace-field":{
      "name":"subject",
      "type":"dovecot_text",
      "indexed":true,
      "stored":false
    }
  }'

  curl -XPOST http://localhost:8983/solr/dovecot/config -H 'Content-type:application/json' -d '{
    "update-requesthandler":{
      "name":"/select",
      "class":"solr.SearchHandler",
      "defaults":{
        "wt":"xml"
      }
    }
  }'

  curl -XPOST http://localhost:8983/solr/dovecot/config/updateHandler -d '{
    "set-property": {
      "updateHandler.autoSoftCommit.maxDocs":500,
      "updateHandler.autoSoftCommit.maxTime":120000,
      "updateHandler.autoCommit.maxDocs":200,
      "updateHandler.autoCommit.maxTime":1800000,
      "updateHandler.autoCommit.openSearcher":false
    }
  }'
}

# fixing volume permission

[[ -d /opt/solr/server/solr/dovecot/data ]] && chown -R solr:solr /opt/solr/server/solr/dovecot/data
if [[ "${1}" != "--bootstrap" ]]; then
  sed -i '/SOLR_HEAP=/c\SOLR_HEAP="'${SOLR_HEAP:-1024}'m"' /opt/solr/bin/solr.in.sh
else
  sed -i '/SOLR_HEAP=/c\SOLR_HEAP="256m"' /opt/solr/bin/solr.in.sh
fi

# start a Solr so we can use the Schema API, but only on localhost,
# so that clients don't see Solr until we have configured it.

echo "Starting local Solr instance to setup configuration"
su-exec solr start-local-solr

# keep a sentinel file so we don't try to create the core a second time
# for example when we restart a container.

SENTINEL=/opt/docker-solr/core_created

if [[ -f ${SENTINEL} ]]; then
  echo "skipping core creation"
else
  echo "Creating core \"dovecot\""
  su-exec solr /opt/solr/bin/solr create -c "dovecot"

  # See https://github.com/docker-solr/docker-solr/issues/27
  echo "Checking core"
  while ! wget -O - 'http://localhost:8983/solr/admin/cores?action=STATUS' | grep -q instanceDir; do
    echo "Could not find any cores, waiting..."
    sleep 5
  done
  echo "Created core \"dovecot\""
  touch ${SENTINEL}
fi

echo "Starting configuration"
while ! wget -O - 'http://localhost:8983/solr/admin/cores?action=STATUS' | grep -q instanceDir; do
  echo "Waiting for Solr..."
  sleep 5
done
solr_config
echo "Stopping local Solr"
su-exec solr stop-local-solr

if [[ "${1}" == "--bootstrap" ]]; then
  exit 0
else
  exec su-exec solr solr-foreground
fi
