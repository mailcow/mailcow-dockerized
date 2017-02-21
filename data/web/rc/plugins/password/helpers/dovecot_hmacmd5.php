<?php

/**
 *
 * dovecot_hmacmd5.php V1.01
 *
 * Generates HMAC-MD5 'contexts' for Dovecot's password files.
 *
 * (C) 2008 Hajo Noerenberg
 *
 * http://www.noerenberg.de/hajo/pub/dovecot_hmacmd5.php.txt
 *
 * Most of the code has been shamelessly stolen from various sources:
 *
 * (C) Paul Johnston 1999 - 2000 / http://pajhome.org.uk/crypt/md5/
 * (C) William K. Cole 2008 / http://www.scconsult.com/bill/crampass.pl
 * (C) Borfast 2002 / http://www.zend.com/code/codex.php?ozid=962&single=1
 * (C) Thomas Weber / http://pajhome.org.uk/crypt/md5/contrib/md5.java.txt
 *
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 3.0 as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program. If not, see <http://www.gnu.org/licenses/gpl-3.0.txt>.
 *
 */

/* Convert a 32-bit number to a hex string with ls-byte first
 */

function rhex($n) {
	$hex_chr = "0123456789abcdef"; $r = '';
	for($j = 0; $j <= 3; $j++)
		$r .= $hex_chr[($n >> ($j * 8 + 4)) & 0x0F] . $hex_chr[($n >> ($j * 8)) & 0x0F];
	return $r;
}

/* zeroFill() is needed because PHP doesn't have a zero-fill
 * right shift operator like JavaScript's >>>
 */

function zeroFill($a, $b) {
	$z = hexdec(80000000);
	if ($z & $a) {
		$a >>= 1;
		$a &= (~$z);
		$a |= 0x40000000;
		$a >>= ($b-1);
	} else {
		$a >>= $b;
	}
	return $a;
}

/* Bitwise rotate a 32-bit number to the left
 */

function bit_rol($num, $cnt) {
	return ($num << $cnt) | (zeroFill($num, (32 - $cnt)));
}

/* Add integers, wrapping at 2^32
 */

function safe_add($x, $y) {
	return (($x&0x7FFFFFFF) + ($y&0x7FFFFFFF)) ^ ($x&0x80000000) ^ ($y&0x80000000);
}

/* These functions implement the four basic operations the algorithm uses.
 */

function md5_cmn($q, $a, $b, $x, $s, $t) {
	return safe_add(bit_rol(safe_add(safe_add($a, $q), safe_add($x, $t)), $s), $b);
}
function md5_ff($a, $b, $c, $d, $x, $s, $t) {
	return md5_cmn(($b & $c) | ((~$b) & $d), $a, $b, $x, $s, $t);
}
function md5_gg($a, $b, $c, $d, $x, $s, $t) {
	return md5_cmn(($b & $d) | ($c & (~$d)), $a, $b, $x, $s, $t);
}
function md5_hh($a, $b, $c, $d, $x, $s, $t) {
	return md5_cmn($b ^ $c ^ $d, $a, $b, $x, $s, $t);
}
function md5_ii($a, $b, $c, $d, $x, $s, $t) {
	return md5_cmn($c ^ ($b | (~$d)), $a, $b, $x, $s, $t);
}

/* Calculate the first round of the MD5 algorithm
 */

function md5_oneround($s, $io) {

	$s = str_pad($s, 64, chr(0x00));

	$x = array_fill(0, 16, 0);

	for($i = 0; $i < 64; $i++)
		$x[$i >> 2] |= (($io ? 0x36 : 0x5c) ^ ord($s[$i])) << (($i % 4) * 8);

	$a = $olda =  1732584193;
	$b = $oldb = -271733879;
	$c = $oldc = -1732584194;
	$d = $oldd =  271733878;

	$a = md5_ff($a, $b, $c, $d, $x[ 0], 7 , -680876936);
	$d = md5_ff($d, $a, $b, $c, $x[ 1], 12, -389564586);
	$c = md5_ff($c, $d, $a, $b, $x[ 2], 17,  606105819);
	$b = md5_ff($b, $c, $d, $a, $x[ 3], 22, -1044525330);
	$a = md5_ff($a, $b, $c, $d, $x[ 4], 7 , -176418897);
	$d = md5_ff($d, $a, $b, $c, $x[ 5], 12,  1200080426);
	$c = md5_ff($c, $d, $a, $b, $x[ 6], 17, -1473231341);
	$b = md5_ff($b, $c, $d, $a, $x[ 7], 22, -45705983);
	$a = md5_ff($a, $b, $c, $d, $x[ 8], 7 ,  1770035416);
	$d = md5_ff($d, $a, $b, $c, $x[ 9], 12, -1958414417);
	$c = md5_ff($c, $d, $a, $b, $x[10], 17, -42063);
	$b = md5_ff($b, $c, $d, $a, $x[11], 22, -1990404162);
	$a = md5_ff($a, $b, $c, $d, $x[12], 7 ,  1804603682);
	$d = md5_ff($d, $a, $b, $c, $x[13], 12, -40341101);
	$c = md5_ff($c, $d, $a, $b, $x[14], 17, -1502002290);
	$b = md5_ff($b, $c, $d, $a, $x[15], 22,  1236535329);

	$a = md5_gg($a, $b, $c, $d, $x[ 1], 5 , -165796510);
	$d = md5_gg($d, $a, $b, $c, $x[ 6], 9 , -1069501632);
	$c = md5_gg($c, $d, $a, $b, $x[11], 14,  643717713);
	$b = md5_gg($b, $c, $d, $a, $x[ 0], 20, -373897302);
	$a = md5_gg($a, $b, $c, $d, $x[ 5], 5 , -701558691);
	$d = md5_gg($d, $a, $b, $c, $x[10], 9 ,  38016083);
	$c = md5_gg($c, $d, $a, $b, $x[15], 14, -660478335);
	$b = md5_gg($b, $c, $d, $a, $x[ 4], 20, -405537848);
	$a = md5_gg($a, $b, $c, $d, $x[ 9], 5 ,  568446438);
	$d = md5_gg($d, $a, $b, $c, $x[14], 9 , -1019803690);
	$c = md5_gg($c, $d, $a, $b, $x[ 3], 14, -187363961);
	$b = md5_gg($b, $c, $d, $a, $x[ 8], 20,  1163531501);
	$a = md5_gg($a, $b, $c, $d, $x[13], 5 , -1444681467);
	$d = md5_gg($d, $a, $b, $c, $x[ 2], 9 , -51403784);
	$c = md5_gg($c, $d, $a, $b, $x[ 7], 14,  1735328473);
	$b = md5_gg($b, $c, $d, $a, $x[12], 20, -1926607734);

	$a = md5_hh($a, $b, $c, $d, $x[ 5], 4 , -378558);
	$d = md5_hh($d, $a, $b, $c, $x[ 8], 11, -2022574463);
	$c = md5_hh($c, $d, $a, $b, $x[11], 16,  1839030562);
	$b = md5_hh($b, $c, $d, $a, $x[14], 23, -35309556);
	$a = md5_hh($a, $b, $c, $d, $x[ 1], 4 , -1530992060);
	$d = md5_hh($d, $a, $b, $c, $x[ 4], 11,  1272893353);
	$c = md5_hh($c, $d, $a, $b, $x[ 7], 16, -155497632);
	$b = md5_hh($b, $c, $d, $a, $x[10], 23, -1094730640);
	$a = md5_hh($a, $b, $c, $d, $x[13], 4 ,  681279174);
	$d = md5_hh($d, $a, $b, $c, $x[ 0], 11, -358537222);
	$c = md5_hh($c, $d, $a, $b, $x[ 3], 16, -722521979);
	$b = md5_hh($b, $c, $d, $a, $x[ 6], 23,  76029189);
	$a = md5_hh($a, $b, $c, $d, $x[ 9], 4 , -640364487);
	$d = md5_hh($d, $a, $b, $c, $x[12], 11, -421815835);
	$c = md5_hh($c, $d, $a, $b, $x[15], 16,  530742520);
	$b = md5_hh($b, $c, $d, $a, $x[ 2], 23, -995338651);

	$a = md5_ii($a, $b, $c, $d, $x[ 0], 6 , -198630844);
	$d = md5_ii($d, $a, $b, $c, $x[ 7], 10,  1126891415);
	$c = md5_ii($c, $d, $a, $b, $x[14], 15, -1416354905);
	$b = md5_ii($b, $c, $d, $a, $x[ 5], 21, -57434055);
	$a = md5_ii($a, $b, $c, $d, $x[12], 6 ,  1700485571);
	$d = md5_ii($d, $a, $b, $c, $x[ 3], 10, -1894986606);
	$c = md5_ii($c, $d, $a, $b, $x[10], 15, -1051523);
	$b = md5_ii($b, $c, $d, $a, $x[ 1], 21, -2054922799);
	$a = md5_ii($a, $b, $c, $d, $x[ 8], 6 ,  1873313359);
	$d = md5_ii($d, $a, $b, $c, $x[15], 10, -30611744);
	$c = md5_ii($c, $d, $a, $b, $x[ 6], 15, -1560198380);
	$b = md5_ii($b, $c, $d, $a, $x[13], 21,  1309151649);
	$a = md5_ii($a, $b, $c, $d, $x[ 4], 6 , -145523070);
	$d = md5_ii($d, $a, $b, $c, $x[11], 10, -1120210379);
	$c = md5_ii($c, $d, $a, $b, $x[ 2], 15,  718787259);
	$b = md5_ii($b, $c, $d, $a, $x[ 9], 21, -343485551);

	$a = safe_add($a, $olda);
	$b = safe_add($b, $oldb);
	$c = safe_add($c, $oldc);
	$d = safe_add($d, $oldd);

	return rhex($a) . rhex($b) . rhex($c) . rhex($d);
}

function dovecot_hmacmd5 ($s) {
	if (strlen($s) > 64) $s=pack("H*", md5($s));
	return md5_oneround($s, 0) . md5_oneround($s, 1);
}
