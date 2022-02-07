#!/bin/sh

# Example attachment decoder script. The attachment comes from stdin, and
# the script is expected to output UTF-8 data to stdout. (If the output isn't
# UTF-8, everything except valid UTF-8 sequences are dropped from it.)

# The attachment decoding is enabled by setting:
#
# plugin {
#   fts_decoder = decode2text
# }
# service decode2text {
#   executable = script /usr/local/libexec/dovecot/decode2text.sh
#   user = dovecot
#   unix_listener decode2text {
#     mode = 0666
#   }
# }

libexec_dir=`dirname $0`
content_type=$1

# The second parameter is the format's filename extension, which is used when
# found from a filename of application/octet-stream. You can also add more
# extensions by giving more parameters.
formats='application/pdf pdf
application/x-pdf pdf
application/msword doc
application/mspowerpoint ppt
application/vnd.ms-powerpoint ppt
application/ms-excel xls
application/x-msexcel xls
application/vnd.ms-excel xls
application/vnd.openxmlformats-officedocument.wordprocessingml.document docx
application/vnd.openxmlformats-officedocument.spreadsheetml.sheet xlsx
application/vnd.openxmlformats-officedocument.presentationml.presentation pptx
application/vnd.oasis.opendocument.text odt
application/vnd.oasis.opendocument.spreadsheet ods
application/vnd.oasis.opendocument.presentation odp
'

if [ "$content_type" = "" ]; then
  echo "$formats"
  exit 0
fi

fmt=`echo "$formats" | grep -w "^$content_type" | cut -d ' ' -f 2`
if [ "$fmt" = "" ]; then
  echo "Content-Type: $content_type not supported" >&2
  exit 1
fi

# most decoders can't handle stdin directly, so write the attachment
# to a temp file
path=`mktemp`
trap "rm -f $path" 0 1 2 3 14 15
cat > $path

xmlunzip() {
  name=$1

  tempdir=`mktemp -d`
  if [ "$tempdir" = "" ]; then 
    exit 1
  fi
  trap "rm -rf $path $tempdir" 0 1 2 3 14 15
  cd $tempdir || exit 1
  unzip -q "$path" 2>/dev/null || exit 0
  find . -name "$name" -print0 | xargs -0 cat |
    $libexec_dir/xml2text
}

wait_timeout() {
  childpid=$!
  trap "kill -9 $childpid; rm -f $path" 1 2 3 14 15
  wait $childpid
}

LANG=en_US.UTF-8
export LANG
if [ $fmt = "pdf" ]; then
  /usr/bin/pdftotext $path - 2>/dev/null&
  wait_timeout 2>/dev/null
elif [ $fmt = "doc" ]; then
  (/usr/bin/catdoc $path; true) 2>/dev/null&
  wait_timeout 2>/dev/null
elif [ $fmt = "ppt" ]; then
  (/usr/bin/catppt $path; true) 2>/dev/null&
  wait_timeout 2>/dev/null
elif [ $fmt = "xls" ]; then
  (/usr/bin/xls2csv $path; true) 2>/dev/null&
  wait_timeout 2>/dev/null
elif [ $fmt = "odt" -o $fmt = "ods" -o $fmt = "odp" ]; then
  xmlunzip "content.xml"
elif [ $fmt = "docx" ]; then
  xmlunzip "document.xml"
elif [ $fmt = "xlsx" ]; then
  xmlunzip "sharedStrings.xml"
elif [ $fmt = "pptx" ]; then
  xmlunzip "slide*.xml"
else
  echo "Buggy decoder script: $fmt not handled" >&2
  exit 1
fi
exit 0
