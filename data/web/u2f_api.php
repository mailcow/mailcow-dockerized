<?php
require_once('inc/prerequisites.inc.php');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

$u2f = new u2flib_server\U2F('https://' . $_SERVER['SERVER_NAME']);

function getRegs($username) {
  global $pdo;
  $sel = $pdo->prepare("select * from tfa where username = ?");
  $sel->execute(array($username));
  return $sel->fetchAll();
}
function addReg($username, $reg) {
  global $pdo;
  $ins = $pdo->prepare("INSERT INTO `tfa` (`username`, `keyHandle`, `publicKey`, `certificate`, `counter`) values (?, ?, ?, ?, ?)");
  $ins->execute(array($username, $reg->keyHandle, $reg->publicKey, $reg->certificate, $reg->counter));
}
function updateReg($reg) {
  global $pdo;
  $upd = $pdo->prepare("update tfa set counter = ? where id = ?");
  $upd->execute(array($reg->counter, $reg->id));
}
?>
<html>
<head>
<script src="js/u2f-api.js"></script>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ((empty($_POST['u2f_username'])) || (!isset($_POST['action']) && !isset($_POST['u2f_register_data']) && !isset($_POST['u2f_auth_data']))) {
    print_r($_POST);
    exit();
  }
  else {
    $username = $_POST['u2f_username'];
    if (isset($_POST['action'])) {
      switch($_POST['action']) {
        case 'register':
          try {
          $data = $u2f->getRegisterData(getRegs($username));
          list($req, $sigs) = $data;
          $_SESSION['regReq'] = json_encode($req);
?>
<script>
var req = <?=json_encode($req);?>;
var sigs = <?=json_encode($sigs);?>;
var username = "<?=$username;?>";
setTimeout(function() {
  console.log("Register: ", req);
  u2f.register([req], sigs, function(data) {
    var form  = document.getElementById('u2f_form');
    var reg   = document.getElementById('u2f_register_data');
    var user  = document.getElementById('u2f_username');
    var status = document.getElementById('u2f_status');
    console.log("Register callback", data);
    if (data.errorCode && data.errorCode != 0) {
      var div = document.getElementById('u2f_return_code');
      div.innerHTML = 'Error code: ' + data.errorCode;
      return;
    }
    reg.value = JSON.stringify(data);
    user.value = username;
    status.value = "1";
    form.submit();
  });
}, 1000);
</script>
<?php
          }
          catch( Exception $e ) {
            echo "U2F error: " . $e->getMessage();
          }
        break;

        case 'authenticate':
        try {
          $reqs = json_encode($u2f->getAuthenticateData(getRegs($username)));
          $_SESSION['authReq']  = $reqs;
?>
<script>
var req = <?=$reqs;?>;
var username = "<?=$username;?>";       
setTimeout(function() {
  console.log("sign: ", req);
  u2f.sign(req, function(data) {
    var form = document.getElementById('u2f_form');
    var auth = document.getElementById('u2f_auth_data');
    var user = document.getElementById('u2f_username');
    console.log("Authenticate callback", data);
    auth.value = JSON.stringify(data);
    user.value = username;
    form.submit();
  });
}, 1000);
</script>
<?php
        }
        catch (Exception $e) {
          echo "U2F error: " . $e->getMessage();
        }
        break;
      }
    }
    if (!empty($_POST['u2f_register_data'])) {
      try {
        $reg = $u2f->doRegister(json_decode($_SESSION['regReq']), json_decode($_POST['u2f_register_data']));
        addReg($username, $reg);
      }
      catch (Exception $e) {
        echo "U2F error: " . $e->getMessage();
      }
      finally {
        echo "Success";
        $_SESSION['regReq'] = null;
      }
    }
    if (!empty($_POST['u2f_auth_data'])) {
      try {
        $reg = $u2f->doAuthenticate(json_decode($_SESSION['authReq']), getRegs($username), json_decode($_POST['u2f_auth_data']));
        updateReg($reg);
      }
      catch (Exception $e) {
        echo "U2F error: " . $e->getMessage();
      }
      finally {
        echo "Success";
        $_SESSION['authReq'] = null;
      }
    }
  }
?>
</head>
<body>
<div id="u2f_return_code"></div>
<form method="POST" id="u2f_form">
<input type="hidden" name="u2f_register_data" id="u2f_register_data"/>
<input type="hidden" name="u2f_auth_data" id="u2f_auth_data"/>
<input type="hidden" name="u2f_username" id="u2f_username"/><br/>
<input type="hidden" name="u2f_status" id="u2f_status"/><br/>
</form>
<?php
}
else {
?>
<form method="POST" id="post_form">
Username: <input name="u2f_username" id="u2f_username"/><br/><hr>
Action: <br />
<input value="register" name="action" type="radio"/> Register<br/>
<input value="authenticate" name="action" type="radio"/> Authenticate<br/>
<button type="submit">Submit!</button>
  </form>
<?php
}
?>
</body>
</html>
