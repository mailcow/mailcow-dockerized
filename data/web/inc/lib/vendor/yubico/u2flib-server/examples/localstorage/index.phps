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
 * This is a minimal example of U2F registration and authentication.
 * The data that has to be stored between registration and authentication
 * is stored in browser localStorage, so there's nothing real-world
 * about this.
 */
require_once('../../src/u2flib_server/U2F.php');
$scheme = isset($_SERVER['HTTPS']) ? "https://" : "http://";
$u2f = new u2flib_server\U2F($scheme . $_SERVER['HTTP_HOST']);
?>
<html>
<head>
    <title>PHP U2F Demo</title>

    <script src="../assets/u2f-api.js"></script>

    <script>
        function addRegistration(reg) {
            var existing = localStorage.getItem('u2fregistration');
            var regobj = JSON.parse(reg);
            var data = null;
            if(existing) {
                data = JSON.parse(existing);
                if(Array.isArray(data)) {
                    for (var i = 0; i < data.length; i++) {
                        if(data[i].keyHandle === regobj.keyHandle) {
                            data.splice(i,1);
                            break;
                        }
                    }
                    data.push(regobj);
                } else {
                    data = null;
                }
            }
            if(data == null) {
                data = [regobj];
            }
            localStorage.setItem('u2fregistration', JSON.stringify(data));
        }
        <?php
        function fixupArray($data) {
            $ret = array();
            $decoded = json_decode($data);
            foreach ($decoded as $d) {
                $ret[] = json_encode($d);
            }
            return $ret;
        }
        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            if(isset($_POST['startRegister'])) {
                $regs = json_decode($_POST['registrations']) ? : array();
                list($data, $reqs) = $u2f->getRegisterData($regs);
                echo "var request = " . json_encode($data) . ";\n";
                echo "var signs = " . json_encode($reqs) . ";\n";
        ?>
        setTimeout(function() {
            console.log("Register: ", request);
            u2f.register([request], signs, function(data) {
                var form = document.getElementById('form');
                var reg = document.getElementById('doRegister');
                var req = document.getElementById('request');
                console.log("Register callback", data);
                if(data.errorCode && data.errorCode != 0) {
                    alert("registration failed with errror: " + data.errorCode);
                    return;
                }
                reg.value=JSON.stringify(data);
                req.value=JSON.stringify(request);
                form.submit();
            });
        }, 1000);
        <?php
            } else if($_POST['doRegister']) {
                try {
                    $data = $u2f->doRegister(json_decode($_POST['request']), json_decode($_POST['doRegister']));
                    echo "var registration = '" . json_encode($data) . "';\n";
        ?>
        addRegistration(registration);
        alert("registration successful!");
        <?php
                } catch(u2flib_server\Error $e) {
                    echo "alert('error:" . $e->getMessage() . "');\n";
                }
            } else if(isset($_POST['startAuthenticate'])) {
                $regs = json_decode($_POST['registrations']);
                $data = $u2f->getAuthenticateData($regs);
                echo "var registrations = " . $_POST['registrations'] . ";\n";
                echo "var request = " . json_encode($data) . ";\n";
        ?>
        setTimeout(function() {
            console.log("sign: ", request);
            u2f.sign(request, function(data) {
                var form = document.getElementById('form');
                var reg = document.getElementById('doAuthenticate');
                var req = document.getElementById('request');
                var regs = document.getElementById('registrations');
                console.log("Authenticate callback", data);
                reg.value=JSON.stringify(data);
                req.value=JSON.stringify(request);
                regs.value=JSON.stringify(registrations);
                form.submit();
            });
        }, 1000);
        <?php
            } else if($_POST['doAuthenticate']) {
                $reqs = json_decode($_POST['request']);
                $regs = json_decode($_POST['registrations']);
                try {
                    $data = $u2f->doAuthenticate($reqs, $regs, json_decode($_POST['doAuthenticate']));
                    echo "var registration = '" . json_encode($data) . "';\n";
                    echo "addRegistration(registration);\n";
                    echo "alert('Authentication successful, counter:" . $data->counter . "');\n";
                } catch(u2flib_server\Error $e) {
                    echo "alert('error:" . $e->getMessage() . "');\n";
                }
            }
        }
        ?>
    </script>

</head>
<body>
<form method="POST" id="form">
    <button name="startRegister" type="submit">Register</button>
    <input type="hidden" name="doRegister" id="doRegister"/>
    <button name="startAuthenticate" type="submit" id="startAuthenticate">Authenticate</button>
    <input type="hidden" name="doAuthenticate" id="doAuthenticate"/>
    <input type="hidden" name="request" id="request"/>
    <input type="hidden" name="registrations" id="registrations"/>
</form>

<p>
    <span id="registered">0</span> Authenticators currently registered.
</p>

<script>
    var reg = localStorage.getItem('u2fregistration');
    var auth = document.getElementById('startAuthenticate');
    if(reg == null) {
        auth.disabled = true;
    } else {
        var regs = document.getElementById('registrations');
        decoded = JSON.parse(reg);
        if(!Array.isArray(decoded)) {
            auth.disabled = true;
        } else {
            regs.value = reg;
            console.log("set the registrations to : ", reg);
            var regged = document.getElementById('registered');
            regged.innerHTML = decoded.length;
        }
    }
</script>
</body>
</html>
