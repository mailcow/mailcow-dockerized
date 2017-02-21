#!/usr/bin/env php
<?php

define('INSTALL_PATH', realpath(__DIR__ . '/..') . '/' );
ini_set('memory_limit', -1);

require_once INSTALL_PATH.'program/include/clisetup.php';

function print_usage()
{
	print "Usage:  msgimport -h imap-host -u user-name -m mailbox -f message-file\n";
	print "--host   IMAP host\n";
	print "--user   IMAP user name\n";
	print "--mbox   Target mailbox\n";
	print "--file   Message file to upload\n";
}


// get arguments
$opts = array('h' => 'host', 'u' => 'user', 'p' => 'pass', 'm' => 'mbox', 'f' => 'file');
$args = rcube_utils::get_opt($opts) + array('host' => 'localhost', 'mbox' => 'INBOX');

if ($_SERVER['argv'][1] == 'help')
{
	print_usage();
	exit;
}
else if (!($args['host'] && $args['file']))
{
	print "Missing required parameters.\n";
	print_usage();
	exit;
}
else if (!is_file($args['file']))
{
	rcube::raise_error("Cannot read message file.", false, true);
}

// prompt for username if not set
if (empty($args['user']))
{
	//fwrite(STDOUT, "Please enter your name\n");
	echo "IMAP user: ";
	$args['user'] = trim(fgets(STDIN));
}

// prompt for password
if (empty($args['pass']))
{
	$args['pass'] = rcube_utils::prompt_silent("Password: ");
}

// parse $host URL
$a_host = parse_url($args['host']);
if ($a_host['host'])
{
	$host = $a_host['host'];
	$imap_ssl = (isset($a_host['scheme']) && in_array($a_host['scheme'], array('ssl','imaps','tls'))) ? TRUE : FALSE;
	$imap_port = isset($a_host['port']) ? $a_host['port'] : ($imap_ssl ? 993 : 143);
}
else
{
	$host = $args['host'];
	$imap_port = 143;
}

// instantiate IMAP class
$IMAP = new rcube_imap(null);

// try to connect to IMAP server
if ($IMAP->connect($host, $args['user'], $args['pass'], $imap_port, $imap_ssl))
{
	print "IMAP login successful.\n";
	print "Uploading messages...\n";
	
	$count = 0;
	$message = $lastline = '';
	
	$fp = fopen($args['file'], 'r');
	while (($line = fgets($fp)) !== false)
	{
		if (preg_match('/^From\s+-/', $line) && $lastline == '')
		{
			if (!empty($message))
			{
				if ($IMAP->save_message($args['mbox'], rtrim($message)))
					$count++;
				else
					rcube::raise_error("Failed to save message to {$args['mbox']}", false, true);
				$message = '';
			}
			continue;
		}

		$message .= $line;
		$lastline = rtrim($line);
	}

	if (!empty($message) && $IMAP->save_message($args['mbox'], rtrim($message)))
		$count++;

	// upload message from file
	if ($count)
		print "$count messages successfully added to {$args['mbox']}.\n";
	else
		print "Adding messages failed!\n";
}
else
{
	rcube::raise_error("IMAP login failed.", false, true);
}

?>
