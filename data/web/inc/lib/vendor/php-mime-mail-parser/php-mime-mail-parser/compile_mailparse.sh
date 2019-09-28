#!/bin/sh

git clone https://github.com/php/pecl-mail-mailparse.git
cd pecl-mail-mailparse
phpize
./configure
sed -i 's/#if\s!HAVE_MBSTRING/#ifndef MBFL_MBFILTER_H/' ./mailparse.c
make
sudo mv modules/mailparse.so /home/travis/.phpenv/versions/7.3.2/lib/php/extensions/no-debug-zts-20180731/
echo 'extension=mailparse.so' >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini