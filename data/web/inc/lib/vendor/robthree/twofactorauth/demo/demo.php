<!doctype html>
<html>
<head>
    <title>Demo</title>
</head>
<body>
    <ol>
        <?php
            // in practice you would require the composer loader if it was not already part of your framework or project
            spl_autoload_register(function ($className) {
                include_once str_replace(array('RobThree\\Auth', '\\'), array(__DIR__.'/../lib', '/'), $className) . '.php';
            });

            // substitute your company or app name here
            $tfa = new RobThree\Auth\TwoFactorAuth('RobThree TwoFactorAuth');
        ?>
        <li>First create a secret and associate it with a user</li>
        <?php
            $secret = $tfa->createSecret();
        ?>
        <li>
            Next create a QR code and let the user scan it:<br>
            <img src="<?php echo $tfa->getQRCodeImageAsDataUri('Demo', $secret); ?>"><br>
            ...or display the secret to the user for manual entry:
            <?php echo chunk_split($secret, 4, ' '); ?>
        </li>
        <?php
            $code = $tfa->getCode($secret);
        ?>
        <li>Next, have the user verify the code; at this time the code displayed by a 2FA-app would be: <span style="color:#00c"><?php echo $code; ?></span> (but that changes periodically)</li>
        <li>When the code checks out, 2FA can be / is enabled; store (encrypted?) secret with user and have the user verify a code each time a new session is started.</li>
        <li>
            When aforementioned code (<?php echo $code; ?>) was entered, the result would be:
            <?php if ($tfa->verifyCode($secret, $code) === true) { ?>
                <span style="color:#0c0">OK</span>
            <?php } else { ?>
                <span style="color:#c00">FAIL</span>
            <?php } ?>
        </li>
    </ol>
    <p>Note: Make sure your server-time is <a href="http://en.wikipedia.org/wiki/Network_Time_Protocol">NTP-synced</a>! Depending on the $discrepancy allowed your time cannot drift too much from the users' time!</p>
    <?php
        try {
            $tfa->ensureCorrectTime();
            echo 'Your hosts time seems to be correct / within margin';
        } catch (RobThree\Auth\TwoFactorAuthException $ex) {
            echo '<b>Warning:</b> Your hosts time seems to be off: ' . $ex->getMessage();
        }
    ?>
</body>
</html>
