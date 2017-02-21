#!/bin/sh
PWD=`dirname "$0"`
JS_DIR="$PWD/../program/js"
JAR_DIR='/tmp'
LANG_IN='ECMASCRIPT5'
CLOSURE_COMPILER_URL='http://dl.google.com/closure-compiler/compiler-latest.zip'

do_shrink() {
	rm -f "$2"
	# copy the first comment block with license information for LibreJS
	grep -q '@lic' $1 && sed -n '/\/\*/,/\*\// { p; /\*\//q; }' $1 > $2
	java -jar $JAR_DIR/compiler.jar --compilation_level=SIMPLE_OPTIMIZATIONS --js="$1" --language_in="$3" >> $2
}

if [ ! -d "$JS_DIR" ]; then
	echo "Directory $JS_DIR not found."
	exit 1
fi

if [ ! -w "$JAR_DIR" ]; then
	JAR_DIR=$PWD
fi

if java -version >/dev/null 2>&1; then
	:
else
	echo "Java not found. Please ensure that the 'java' program is in your PATH."
	exit 1
fi

if [ ! -r "$JAR_DIR/compiler.jar" ]; then
	if which wget >/dev/null 2>&1 && which unzip >/dev/null 2>&1; then
		wget "$CLOSURE_COMPILER_URL" -O "/tmp/$$.zip"
	elif which curl >/dev/null 2>&1 && which unzip >/dev/null 2>&1; then
		curl "$CLOSURE_COMPILER_URL" -o "/tmp/$$.zip"
	else
		echo "Please download $CLOSURE_COMPILER_URL and extract compiler.jar to $JAR_DIR/."
		exit 1
	fi
	(cd $JAR_DIR && unzip -p "/tmp/$$.zip" "*.jar" > "$JAR_DIR/compiler.jar")
	rm -f "/tmp/$$.zip"
fi

# compress single file from argument
if [ $# -gt 0 ]; then
	JS_DIR=`dirname "$1"`
	JS_FILE="$1"

	if [ $# -gt 1 ]; then
		LANG_IN="$2"
	fi

	echo "Shrinking $JS_FILE"
    minfile=`echo $JS_FILE | sed -e 's/\.js$/\.min\.js/'`
	do_shrink "$JS_FILE" "$minfile" "$LANG_IN"
	exit
fi

DIRS="$PWD/../program/js $PWD/../skins/* $PWD/../plugins/* $PWD/../plugins/*/skins/* $PWD/../plugins/managesieve/codemirror/lib"
# default: compress application scripts
for dir in $DIRS; do
    for file in $dir/*.js; do
        echo "$file" | grep -e '.min.js$' >/dev/null
        if [ $? -eq 0 ]; then
            continue
        fi
        if [ ! -f "$file" ]; then
            continue
        fi

        echo "Shrinking $file"
        minfile=`echo $file | sed -e 's/\.js$/\.min\.js/'`
        do_shrink "$file" "$minfile" "$LANG_IN"
    done
done
