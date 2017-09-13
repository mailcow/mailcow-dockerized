#!/bin/sh

set -e

VERSION=$1
PGP_KEYID=$2

if [ "x$PGP_KEYID" = "x" ]; then
  echo "try with $0 VERSION PGP_KEYID"
  echo "example: $0 0.0.1 B2168C0A"
  exit
fi

if ! head -3 NEWS  | grep -q "Version $VERSION .released `date -I`"; then
  echo "You need to update date/version in NEWS"
  exit
fi

if [ "x$YUBICO_GITHUB_REPO" = "x" ]; then
  echo "you need to define YUBICO_GITHUB_REPO"
  exit
fi

releasename=php-u2flib-server-${VERSION}

git push
git tag -u ${PGP_KEYID} -m $VERSION $VERSION
git push --tags
tmpdir=`mktemp -d /tmp/release.XXXXXX`
releasedir=${tmpdir}/${releasename}
mkdir -p $releasedir
git archive $VERSION --format=tar | tar -xC $releasedir
git2cl > $releasedir/ChangeLog
cd $releasedir
apigen generate
cd -
tar -cz --directory=$tmpdir --file=${releasename}.tar.gz $releasename
gpg --detach-sign --default-key $PGP_KEYID ${releasename}.tar.gz
$YUBICO_GITHUB_REPO/publish php-u2flib-server $VERSION ${releasename}.tar.gz*
rm -rf $tmpdir
