#!/usr/bin/env php
<?php
/*
 +-----------------------------------------------------------------------+
 | bin/dumpschema.sh                                                     |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2009, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Dumps database schema in XML format using MDB2_Schema               |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

define('INSTALL_PATH', realpath(__DIR__ . '/..') . '/' );

require INSTALL_PATH.'program/include/clisetup.php';

/** callback function for schema dump **/
function print_schema($dump)
{
	foreach ((array)$dump as $part)
		echo $dump . "\n";
}

$config = new rcube_config();

// don't allow public access if not in devel_mode
if (!$config->get('devel_mode') && $_SERVER['REMOTE_ADDR']) {
	header("HTTP/1.0 401 Access denied");
	die("Access denied!");
}

$options = array(
	'use_transactions' => false,
	'log_line_break' => "\n",
	'idxname_format' => '%s',
	'debug' => false,
	'quote_identifier' => true,
	'force_defaults' => false,
	'portability' => false,
);

$dsnw = $config->get('db_dsnw');
$dsn_array = MDB2::parseDSN($dsnw);

// set options for postgres databases
if ($dsn_array['phptype'] == 'pgsql') {
	$options['disable_smart_seqname'] = true;
	$options['seqname_format'] = '%s';
}

$schema =& MDB2_Schema::factory($dsnw, $options);
$schema->db->supported['transactions'] = false;


// send as text/xml when opened in browser
if ($_SERVER['REMOTE_ADDR'])
	header('Content-Type: text/xml');


if (PEAR::isError($schema)) {
	$error = $schema->getMessage() . ' ' . $schema->getUserInfo();
}
else {
	$dump_config = array(
		// 'output_mode' => 'file',
		'output' => 'print_schema',
	);
	
	$definition = $schema->getDefinitionFromDatabase();
	$definition['charset'] = 'utf8';

	if (PEAR::isError($definition)) {
		$error = $definition->getMessage() . ' ' . $definition->getUserInfo();
	}
	else {
		$operation = $schema->dumpDatabase($definition, $dump_config, MDB2_SCHEMA_DUMP_STRUCTURE);
		if (PEAR::isError($operation)) {
			$error = $operation->getMessage() . ' ' . $operation->getUserInfo();
		}
	}
}

$schema->disconnect();

if ($error && !$_SERVER['REMOTE_ADDR'])
	fputs(STDERR, $error);

?>
