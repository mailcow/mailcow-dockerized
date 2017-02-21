<?php

if (!class_exists('rcmail_install', false) || !is_object($RCI)) {
    die("Not allowed! Please open installer/index.php instead.");
}

?>
<form action="index.php" method="get">
<?php

$required_php_exts = array(
    'PCRE'      => 'pcre',
    'DOM'       => 'dom',
    'Session'   => 'session',
    'XML'       => 'xml',
    'JSON'      => 'json',
    'PDO'       => 'PDO',
    'Multibyte' => 'mbstring',
    'OpenSSL'   => 'openssl',
);

$optional_php_exts = array(
    'FileInfo'  => 'fileinfo',
    'Libiconv'  => 'iconv',
    'Intl'      => 'intl',
    'Exif'      => 'exif',
    'LDAP'      => 'ldap',
);

$required_libs = array(
    'PEAR'      => 'pear.php.net',
    'Auth_SASL' => 'pear.php.net',
    'Net_SMTP'  => 'pear.php.net',
    'Net_IDNA2' => 'pear.php.net',
    'Mail_mime' => 'pear.php.net',
);

$optional_libs = array(
    'Net_LDAP3' => 'git.kolab.org',
);

$ini_checks = array(
    'file_uploads'                  => 1,
    'session.auto_start'            => 0,
    'mbstring.func_overload'        => 0,
    'suhosin.session.encrypt'       => 0,
);

$optional_checks = array(
    // required for utils/modcss.inc, should we require this?
    'allow_url_fopen'  => 1,
    'date.timezone'    => '-VALID-',
);

$source_urls = array(
    'Sockets'   => 'http://www.php.net/manual/en/book.sockets.php',
    'Session'   => 'http://www.php.net/manual/en/book.session.php',
    'PCRE'      => 'http://www.php.net/manual/en/book.pcre.php',
    'FileInfo'  => 'http://www.php.net/manual/en/book.fileinfo.php',
    'Libiconv'  => 'http://www.php.net/manual/en/book.iconv.php',
    'Multibyte' => 'http://www.php.net/manual/en/book.mbstring.php',
    'OpenSSL'   => 'http://www.php.net/manual/en/book.openssl.php',
    'JSON'      => 'http://www.php.net/manual/en/book.json.php',
    'DOM'       => 'http://www.php.net/manual/en/book.dom.php',
    'Intl'      => 'http://www.php.net/manual/en/book.intl.php',
    'Exif'      => 'http://www.php.net/manual/en/book.exif.php',
    'oci8'      => 'http://www.php.net/manual/en/book.oci8.php',
    'PDO'       => 'http://www.php.net/manual/en/book.pdo.php',
    'LDAP'      => 'http://www.php.net/manual/en/book.ldap.php',
    'pdo_mysql'   => 'http://www.php.net/manual/en/ref.pdo-mysql.php',
    'pdo_pgsql'   => 'http://www.php.net/manual/en/ref.pdo-pgsql.php',
    'pdo_sqlite'  => 'http://www.php.net/manual/en/ref.pdo-sqlite.php',
    'pdo_sqlite2' => 'http://www.php.net/manual/en/ref.pdo-sqlite.php',
    'pdo_sqlsrv'  => 'http://www.php.net/manual/en/ref.pdo-sqlsrv.php',
    'pdo_dblib'   => 'http://www.php.net/manual/en/ref.pdo-dblib.php',
    'PEAR'      => 'http://pear.php.net',
    'Net_SMTP'  => 'http://pear.php.net/package/Net_SMTP',
    'Mail_mime' => 'http://pear.php.net/package/Mail_mime',
    'Net_IDNA2' => 'http://pear.php.net/package/Net_IDNA2',
    'Net_LDAP3' => 'https://git.kolab.org/diffusion/PNL',
);

echo '<input type="hidden" name="_step" value="' . ($RCI->configured ? 3 : 2) . '" />';
?>

<h3>Checking PHP version</h3>
<?php

define('MIN_PHP_VERSION', '5.4.0');
if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '>=')) {
    $RCI->pass('Version', 'PHP ' . PHP_VERSION . ' detected');
} else {
    $RCI->fail('Version', 'PHP Version ' . MIN_PHP_VERSION . ' or greater is required ' . PHP_VERSION . ' detected');
}
?>

<h3>Checking PHP extensions</h3>
<p class="hint">The following modules/extensions are <em>required</em> to run Roundcube:</p>
<?php

// get extensions location
$ext_dir = ini_get('extension_dir');

$prefix = (PHP_SHLIB_SUFFIX === 'dll') ? 'php_' : '';
foreach ($required_php_exts as $name => $ext) {
    if (extension_loaded($ext)) {
        $RCI->pass($name);
    } else {
        $_ext = $ext_dir . '/' . $prefix . $ext . '.' . PHP_SHLIB_SUFFIX;
        $msg = @is_readable($_ext) ? 'Could be loaded. Please add in php.ini' : '';
        $RCI->fail($name, $msg, $source_urls[$name]);
    }
    echo '<br />';
}

?>

<p class="hint">The next couple of extensions are <em>optional</em> and recommended to get the best performance:</p>
<?php

foreach ($optional_php_exts as $name => $ext) {
    if (extension_loaded($ext)) {
        $RCI->pass($name);
    }
    else {
        $_ext = $ext_dir . '/' . $prefix . $ext . '.' . PHP_SHLIB_SUFFIX;
        $msg = @is_readable($_ext) ? 'Could be loaded. Please add in php.ini' : '';
        $RCI->na($name, $msg, $source_urls[$name]);
    }
    echo '<br />';
}

?>


<h3>Checking available databases</h3>
<p class="hint">Check which of the supported extensions are installed. At least one of them is required.</p>

<?php

$prefix = (PHP_SHLIB_SUFFIX === 'dll') ? 'php_' : '';
foreach ($RCI->supported_dbs as $database => $ext) {
    if (extension_loaded($ext)) {
        $RCI->pass($database);
        $found_db_driver = true;
    }
    else {
        $_ext = $ext_dir . '/' . $prefix . $ext . '.' . PHP_SHLIB_SUFFIX;
        $msg = @is_readable($_ext) ? 'Could be loaded. Please add in php.ini' : '';
        $RCI->na($database, $msg, $source_urls[$ext]);
    }
    echo '<br />';
}
if (empty($found_db_driver)) {
  $RCI->failures++;
}

?>


<h3>Check for required 3rd party libs</h3>
<p class="hint">This also checks if the include path is set correctly.</p>

<?php

foreach ($required_libs as $classname => $vendor) {
    if (class_exists($classname)) {
        $RCI->pass($classname);
    }
    else {
        $RCI->fail($classname, "Failed to load class $classname from $vendor", $source_urls[$classname]);
    }
    echo "<br />";
}

foreach ($optional_libs as $classname => $vendor) {
    if (class_exists($classname)) {
        $RCI->pass($classname);
    }
    else {
        $RCI->na($classname, "Recommended to install $classname from $vendor", $source_urls[$classname]);
    }
    echo "<br />";
}

?>

<h3>Checking php.ini/.htaccess settings</h3>
<p class="hint">The following settings are <em>required</em> to run Roundcube:</p>

<?php

foreach ($ini_checks as $var => $val) {
    $status = ini_get($var);
    if ($val === '-NOTEMPTY-') {
        if (empty($status)) {
            $RCI->fail($var, "empty value detected");
        }
        else {
            $RCI->pass($var);
        }
    }
    else if (filter_var($status, FILTER_VALIDATE_BOOLEAN) == $val) {
        $RCI->pass($var);
    }
    else {
      $RCI->fail($var, "is '$status', should be '$val'");
    }
    echo '<br />';
}
?>

<p class="hint">The following settings are <em>optional</em> and recommended:</p>

<?php

foreach ($optional_checks as $var => $val) {
    $status = ini_get($var);
    if ($val === '-NOTEMPTY-') {
        if (empty($status)) {
            $RCI->optfail($var, "Could be set");
        } else {
            $RCI->pass($var);
        }
        echo '<br />';
        continue;
    }
    if ($val === '-VALID-') {
        if ($var == 'date.timezone') {
            try {
                $tz = new DateTimeZone($status);
                $RCI->pass($var);
            }
            catch (Exception $e) {
                $RCI->optfail($var, empty($status) ? "not set" : "invalid value detected: $status");
            }
        }
        else {
            $RCI->pass($var);
        }
    }
    else if (filter_var($status, FILTER_VALIDATE_BOOLEAN) == $val) {
        $RCI->pass($var);
    }
    else {
      $RCI->optfail($var, "is '$status', could be '$val'");
    }
    echo '<br />';
}
?>

<?php

if ($RCI->failures) {
  echo '<p class="warning">Sorry but your webserver does not meet the requirements for Roundcube!<br />
            Please install the missing modules or fix the php.ini settings according to the above check results.<br />
            Hint: only checks showing <span class="fail">NOT OK</span> need to be fixed.</p>';
}
echo '<p><br /><input type="submit" value="NEXT" ' . ($RCI->failures ? 'disabled' : '') . ' /></p>';

?>

</form>
