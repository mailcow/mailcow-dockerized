#!/usr/bin/env php
<?php

define('INSTALL_PATH', getcwd() . '/' );

require_once INSTALL_PATH . 'program/include/clisetup.php';

// get arguments
$opts = rcube_utils::get_opt(array(
    'd' => 'dir',
    'p' => 'package',
));

if (empty($opts['dir'])) {
    rcube::raise_error("Database schema directory not specified (--dir).", false, true);
}
if (empty($opts['package'])) {
    rcube::raise_error("Database schema package name not specified (--package).", false, true);
}

// Check if directory exists
if (!file_exists($opts['dir'])) {
    rcube::raise_error("Specified database schema directory doesn't exist.", false, true);
}

$RC = rcube::get_instance();
$DB = rcube_db::factory($RC->config->get('db_dsnw'));

// Connect to database
$DB->db_connect('w');
if (!$DB->is_connected()) {
    rcube::raise_error("Error connecting to database: " . $DB->is_error(), false, true);
}

$opts['dir'] = rtrim($opts['dir'], DIRECTORY_SEPARATOR);
$file = $opts['dir'] . DIRECTORY_SEPARATOR . $DB->db_provider . '.initial.sql';
if (!file_exists($file)) {
    rcube::raise_error("No DDL file found for " . $DB->db_provider . " driver.", false, true);
}

$package = $opts['package'];
$error = false;

// read DDL file
if ($lines = file($file)) {
    $sql = '';
    foreach ($lines as $line) {
        if (preg_match('/^--/', $line) || trim($line) == '')
            continue;

        $sql .= $line . "\n";
        if (preg_match('/(;|^GO)$/', trim($line))) {
            @$DB->query(fix_table_names($sql));
            $sql = '';
            if ($error = $DB->is_error()) {
                break;
            }
        }
    }
}

if (!$error) {
    $version = date('Ymd00');
    $system_table = $DB->quote_identifier($DB->table_name('system'));
    $name_col = $DB->quote_identifier('name');
    $value_col = $DB->quote_identifier('value');
    $package_version = $package . '-version';

    $DB->query("SELECT * FROM $system_table WHERE $name_col=?",
        $package_version);

    if ($DB->fetch_assoc()) {
        $DB->query("UPDATE $system_table SET $value_col=? WHERE $name_col=?",
            $version, $package_version);
    }
    else {
        $DB->query("INSERT INTO $system_table ($name_col, $value_col) VALUES (?, ?)",
            $package_version, $version);
    }

    $error = $DB->is_error();
}

if ($error) {
    echo "[FAILED]\n";
    rcube::raise_error("Error in DDL schema $file: $error", false, true);
}
echo "[OK]\n";


function fix_table_names($sql)
{
    global $DB, $RC;

    $prefix = $RC->config->get('db_prefix');
    $engine = $DB->db_provider;

    if (empty($prefix)) {
        return $sql;
    }

    $tables    = array();
    $sequences = array();

    // find table names
    if (preg_match_all('/CREATE TABLE (\[dbo\]\.|IF NOT EXISTS )?[`"\[\]]*([^`"\[\] \r\n]+)/i', $sql, $matches)) {
        foreach ($matches[2] as $table) {
            $tables[$table] = $prefix . $table;
        }
    }
    // find sequence names
    if ($engine == 'postgres' && preg_match_all('/CREATE SEQUENCE (IF NOT EXISTS )?"?([^" \n\r]+)/i', $sql, $matches)) {
        foreach ($matches[2] as $sequence) {
            $sequences[$sequence] = $prefix . $sequence;
        }
    }

    // replace table names
    foreach ($tables as $table => $real_table) {
        $sql = preg_replace("/([^a-zA-Z0-9_])$table([^a-zA-Z0-9_])/", "\\1$real_table\\2", $sql);
    }
    // replace sequence names
    foreach ($sequences as $sequence => $real_sequence) {
        $sql = preg_replace("/([^a-zA-Z0-9_])$sequence([^a-zA-Z0-9_])/", "\\1$real_sequence\\2", $sql);
    }

    return $sql;
}

?>
