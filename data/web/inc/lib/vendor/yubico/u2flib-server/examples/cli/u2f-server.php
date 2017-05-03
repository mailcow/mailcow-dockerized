#!/usr/bin/php
<?php

 /* Copyright (c) 2015 Yubico AB
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are
 * met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above
 *     copyright notice, this list of conditions and the following
 *     disclaimer in the documentation and/or other materials provided
 *     with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * This is a basic example of a u2f-server command line that can be used 
 * with the u2f-host binary to perform regitrations and authentications.
 */ 

require_once('../../src/u2flib_server/U2F.php');

$options = getopt("rao:R:");
$mode;
$challenge;
$response;
$result;
$regs;

if(array_key_exists('r', $options)) {
  $mode = "register";
} elseif(array_key_exists('a', $options)) {
  if(!array_key_exists('R', $options)) {
    print "a registration must be supplied with -R";
    exit(1);
  }
  $regs = json_decode('[' . $options['R'] . ']');
  $mode = "authenticate";
} else {
  print "-r or -a must be used\n";
  exit(1);
}
if(!array_key_exists('o', $options)) {
  print "origin must be supplied with -o\n";
  exit(1);
}

$u2f = new u2flib_server\U2F($options['o']);

if($mode === "register") {
  $challenge = $u2f->getRegisterData();
} elseif($mode === "authenticate") {
  $challenge = $u2f->getAuthenticateData($regs);
}

print json_encode($challenge[0]) . "\n";
$response = fgets(STDIN);

if($mode === "register") {
  $result = $u2f->doRegister($challenge[0], json_decode($response));
} elseif($mode === "authenticate") {
  $result = $u2f->doAuthenticate($challenge, $regs, json_decode($response));
}

print json_encode($result) . "\n";

?>
