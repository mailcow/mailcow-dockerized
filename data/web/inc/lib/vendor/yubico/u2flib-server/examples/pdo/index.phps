<?php
/**
 * Copyright (c) 2014 Yubico AB
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
 * This is a simple example using PDO and a sqlite database for storing
 * registrations. It supports multiple registrations associated with each user.
 */

require_once('../../src/u2flib_server/U2F.php');

$dbfile = '/var/tmp/u2f-pdo.sqlite';

$pdo = new PDO("sqlite:$dbfile");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

$pdo->exec("create table if not exists users (id integer primary key, name varchar(255))");
$pdo->exec("create table if not exists registrations (id integer primary key, user_id integer, keyHandle varchar(255), publicKey varchar(255), certificate text, counter integer)");

$scheme = isset($_SERVER['HTTPS']) ? "https://" : "http://";
$u2f = new u2flib_server\U2F($scheme . $_SERVER['HTTP_HOST']);

session_start();

function createAndGetUser($name) {
    global $pdo;
    $sel = $pdo->prepare("select * from users where name = ?");
    $sel->execute(array($name));
    $user = $sel->fetch();
    if(!$user) {
        $ins = $pdo->prepare("insert into users (name) values(?)");
        $ins->execute(array($name));
        $sel->execute(array($name));
        $user = $sel->fetch();
    }
    return $user;
}

function getRegs($user_id) {
    global $pdo;
    $sel = $pdo->prepare("select * from registrations where user_id = ?");
    $sel->execute(array($user_id));
    return $sel->fetchAll();
}

function addReg($user_id, $reg) {
    global $pdo;
    $ins = $pdo->prepare("insert into registrations (user_id, keyHandle, publicKey, certificate, counter) values (?, ?, ?, ?, ?)");
    $ins->execute(array($user_id, $reg->keyHandle, $reg->publicKey, $reg->certificate, $reg->counter));
}

function updateReg($reg) {
    global $pdo;
    $upd = $pdo->prepare("update registrations set counter = ? where id = ?");
    $upd->execute(array($reg->counter, $reg->id));
}

?>

<html>
<head>
    <title>PHP U2F example</title>

    <script src="../assets/u2f-api.js"></script>

    <script>
        <?php

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
          if(!$_POST['username']) {
            echo "alert('no username provided!');";
          } else if(!isset($_POST['action']) && !isset($_POST['register2']) && !isset($_POST['authenticate2'])) {
            echo "alert('no action provided!');";
          } else {
            $user = createAndGetUser($_POST['username']);

            if(isset($_POST['action'])) {
              switch($_POST['action']):
                case 'register':
                  try {
                    $data = $u2f->getRegisterData(getRegs($user->id));

                    list($req,$sigs) = $data;
                    $_SESSION['regReq'] = json_encode($req);
                    echo "var req = " . json_encode($req) . ";";
                    echo "var sigs = " . json_encode($sigs) . ";";
                    echo "var username = '" . $user->name . "';";
        ?>
        setTimeout(function() {
            console.log("Register: ", req);
            u2f.register([req], sigs, function(data) {
                var form = document.getElementById('form');
                var reg = document.getElementById('register2');
                var user = document.getElementById('username');
                console.log("Register callback", data);
                if(data.errorCode && errorCode != 0) {
                    alert("registration failed with errror: " + data.errorCode);
                    return;
                }
                reg.value = JSON.stringify(data);
                user.value = username;
                form.submit();
            });
        }, 1000);
        <?php
                  } catch( Exception $e ) {
                    echo "alert('error: " . $e->getMessage() . "');";
                  }

                  break;

                case 'authenticate':
                  try {
                    $reqs = json_encode($u2f->getAuthenticateData(getRegs($user->id)));

                    $_SESSION['authReq'] = $reqs;
                    echo "var req = $reqs;";
                    echo "var username = '" . $user->name . "';";
        ?>
        setTimeout(function() {
            console.log("sign: ", req);
            u2f.sign(req, function(data) {
                var form = document.getElementById('form');
                var auth = document.getElementById('authenticate2');
                var user = document.getElementById('username');
                console.log("Authenticate callback", data);
                auth.value=JSON.stringify(data);
                user.value = username;
                form.submit();
            });
        }, 1000);
        <?php
                  } catch( Exception $e ) {
                    echo "alert('error: " . $e->getMessage() . "');";
                  }

                  break;

              endswitch;
            } else if($_POST['register2']) {
              try {
                $reg = $u2f->doRegister(json_decode($_SESSION['regReq']), json_decode($_POST['register2']));
                addReg($user->id, $reg);
              } catch( Exception $e ) {
                echo "alert('error: " . $e->getMessage() . "');";
              } finally {
                $_SESSION['regReq'] = null;
              }
            } else if($_POST['authenticate2']) {
              try {
                $reg = $u2f->doAuthenticate(json_decode($_SESSION['authReq']), getRegs($user->id), json_decode($_POST['authenticate2']));
                updateReg($reg);
                echo "alert('success: " . $reg->counter . "');";
              } catch( Exception $e ) {
                echo "alert('error: " . $e->getMessage() . "');";
              } finally {
                $_SESSION['authReq'] = null;
              }
            }
          }
        }
        ?>
    </script>
</head>
<body>

<form method="POST" id="form">
    username: <input name="username" id="username"/><br/>
    register: <input value="register" name="action" type="radio"/><br/>
    authenticate: <input value="authenticate" name="action" type="radio"/><br/>
    <input type="hidden" name="register2" id="register2"/>
    <input type="hidden" name="authenticate2" id="authenticate2"/>
    <button type="submit">Submit!</button>
</form>

</body>
</html>
