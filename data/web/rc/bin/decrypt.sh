#!/usr/bin/env php
<?php
/*
 +-----------------------------------------------------------------------+
 | bin/decrypt.sh                                                        |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2009, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Decrypt the encrypted parts of the HTTP Received: headers           |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Tomas Tevesz <ice@extreme.hu>                                 |
 +-----------------------------------------------------------------------+
*/

/**
 * If http_received_header_encrypt is configured, the IP address and the
 * host name of the added Received: header is encrypted with 3DES, to
 * protect information that some could consider sensitve, yet their
 * availability is a must in some circumstances.
 *
 * Such an encrypted Received: header might look like:
 *
 * Received: from DzgkvJBO5+bw+oje5JACeNIa/uSI4mRw2cy5YoPBba73eyBmjtyHnQ==
 *  [my0nUbjZXKtl7KVBZcsvWOxxtyVFxza4]
 *  with HTTP/1.1 (POST); Thu, 14 May 2009 19:17:28 +0200
 *
 * In this example, the two encrypted components are the sender host name
 * (DzgkvJBO5+bw+oje5JACeNIa/uSI4mRw2cy5YoPBba73eyBmjtyHnQ==) and the IP
 * address (my0nUbjZXKtl7KVBZcsvWOxxtyVFxza4).
 *
 * Using this tool, they can be decrypted into plain text:
 *
 * $ bin/decrypt.sh 'my0nUbjZXKtl7KVBZcsvWOxxtyVFxza4' \
 * > 'DzgkvJBO5+bw+oje5JACeNIa/uSI4mRw2cy5YoPBba73eyBmjtyHnQ=='
 * 84.3.187.208
 * 5403BBD0.catv.pool.telekom.hu
 * $
 *
 * Thus it is known that this particular message was sent by 84.3.187.208,
 * having, at the time of sending, the name of 5403BBD0.catv.pool.telekom.hu.
 *
 * If (most likely binary) junk is shown, then
 *  - either the encryption password has, between the time the mail was sent
 *    and 'now', changed, or
 *  - you are dealing with counterfeit header data.
 */

define('INSTALL_PATH', realpath(__DIR__ .'/..') . '/');

require INSTALL_PATH . 'program/include/clisetup.php';

if ($argc < 2) {
	die("Usage: " . basename($argv[0]) . " encrypted-hdr-part [encrypted-hdr-part ...]\n");
}

$RCMAIL = rcube::get_instance();

for ($i = 1; $i < $argc; $i++) {
	printf("%s\n", $RCMAIL->decrypt($argv[$i]));
};
