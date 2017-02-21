#!/bin/sh
PWD=`dirname "$0"`
JAR_DIR='/tmp'
VERSION='2.4.8'
COMPILER_URL="https://github.com/yui/yuicompressor/releases/download/v${VERSION}/yuicompressor-${VERSION}.zip"

do_shrink() {
	rm -f "$2"
	java -jar $JAR_DIR/yuicompressor.jar -v -o "$2" "$1"
}

if [ ! -w "$JAR_DIR" ]; then
	JAR_DIR=$PWD
fi

if java -version >/dev/null 2>&1; then
	:
else
	echo "Java not found. Please ensure that the 'java' program is in your PATH."
	exit 1
fi

if [ ! -r "$JAR_DIR/yuicompressor.jar" ]; then
	if which wget >/dev/null 2>&1 && which unzip >/dev/null 2>&1; then
		wget "$COMPILER_URL" -O "/tmp/$$.zip"
	elif which curl >/dev/null 2>&1 && which unzip >/dev/null 2>&1; then
		curl "$COMPILER_URL" -o "/tmp/$$.zip"
	else
		echo "Please download $COMPILER_URL and extract compiler.jar to $JAR_DIR/."
		exit 1
	fi
	(cd $JAR_DIR && unzip "/tmp/$$.zip" && mv "yuicompressor-${VERSION}.jar" "yuicompressor.jar")
	rm -f "/tmp/$$.zip"
fi

# compress single file from argument
if [ $# -gt 0 ]; then
	CSS_FILE="$1"

	echo "Shrinking $CSS_FILE"
    minfile=`echo $CSS_FILE | sed -e 's/\.css$/\.min\.css/'`
	do_shrink "$CSS_FILE" "$minfile"
	exit
fi

DIRS="$PWD/../skins/* $PWD/../plugins/* $PWD/../plugins/*/skins/*"
# default: compress application scripts
for dir in $DIRS; do
    for file in $dir/*.css; do
        echo "$file" | grep -e '.min.css$' >/dev/null
        if [ $? -eq 0 ]; then
            continue
        fi
        if [ ! -f "$file" ]; then
            continue
        fi

        echo "Shrinking $file"
        minfile=`echo $file | sed -e 's/\.css$/\.min\.css/'`
        do_shrink "$file" "$minfile"
    done
done
