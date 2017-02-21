<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/mail/import.inc                                         |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2014, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Save the uploaded file(s) as messages to the current IMAP folder    |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

// clear all stored output properties (like scripts and env vars)
$OUTPUT->reset();

if (is_array($_FILES['_file'])) {
    $imported = 0;
    $folder   = $RCMAIL->storage->get_folder();

    foreach ((array)$_FILES['_file']['tmp_name'] as $i => $filepath) {
        // Process uploaded file if there is no error
        $err = $_FILES['_file']['error'][$i];

        if (!$err) {
            // check file content type first
            $ctype = rcube_mime::file_content_type($filepath, $_FILES['_file']['name'][$i], $_FILES['_file']['type'][$i]);
            list($mtype_primary, $mtype_secondary) = explode('/', $ctype);

            if (in_array($ctype, array('application/zip', 'application/x-zip'))) {
                $filepath = rcmail_zip_extract($filepath);
                if (empty($filepath)) {
                    continue;
                }
            }
            else if (!in_array($mtype_primary, array('text', 'message'))) {
                continue;
            }

            foreach ((array) $filepath as $file) {
                // read the first few lines to detect header-like structure
                $fp = fopen($file, 'r');
                do {
                    $line = fgets($fp);
                }
                while ($line !== false && trim($line) == '');

                if (!preg_match('/^From .+/', $line) && !preg_match('/^[a-z-_]+:\s+.+/i', $line)) {
                    continue;
                }

                $message = $lastline = '';
                fseek($fp, 0);

                while (($line = fgets($fp)) !== false) {
                    // importing mbox file, split by From - lines
                    if ($lastline === '' && strncmp($line, 'From ', 5) === 0 && strlen($line) > 5) {
                        if (!empty($message)) {
                            $imported += (int) rcmail_save_message($folder, $message);
                        }

                        $message  = $line;
                        $lastline = '';
                        continue;
                    }

                    $message .= $line;
                    $lastline = rtrim($line);
                }

                if (!empty($message)) {
                    $imported += (int) rcmail_save_message($folder, $message);
                }

                // remove temp files extracted from zip
                if (is_array($filepath)) {
                    unlink($file);
                }
            }
        }
        else if ($err == UPLOAD_ERR_INI_SIZE || $err == UPLOAD_ERR_FORM_SIZE) {
            $size = $RCMAIL->show_bytes(rcube_utils::max_upload_size());
            $msg  = $RCMAIL->gettext(array('name' => 'filesizeerror', 'vars' => array('size' => $size)));

            $OUTPUT->command('display_message', $msg, 'error');
        }
        else if ($err) {
            $OUTPUT->show_message('fileuploaderror', 'error');
        }
    }

    if ($imported) {
        $OUTPUT->show_message($RCMAIL->gettext(array('name' => 'importmessagesuccess', 'nr' => $imported, 'vars' => array('nr' => $imported))), 'confirmation');
        $OUTPUT->command('command', 'list');
    }
    else {
        $OUTPUT->show_message('importmessageerror', 'error');
    }
}
else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // if filesize exceeds post_max_size then $_FILES array is empty,
    // show filesizeerror instead of fileuploaderror
    if ($maxsize = ini_get('post_max_size'))
        $msg = $RCMAIL->gettext(array('name' => 'filesizeerror', 'vars' => array('size' => $RCMAIL->show_bytes(parse_bytes($maxsize)))));
    else
        $msg = $RCMAIL->gettext('fileuploaderror');

    $OUTPUT->command('display_message', $msg, 'error');
}

// send html page with JS calls as response
$OUTPUT->send('iframe');


function rcmail_zip_extract($path)
{
    if (!class_exists('ZipArchive', false)) {
        return;
    }

    $rcmail   = rcmail::get_instance();
    $temp_dir = $rcmail->config->get('temp_dir');
    $zip      = new ZipArchive;
    $files    = array();

    if ($zip->open($path)) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry    = $zip->getNameIndex($i);
            $tmpfname = tempnam($temp_dir, 'zipimport');

            if (copy("zip://$path#$entry", $tmpfname)) {
                $ctype = rcube_mime::file_content_type($tmpfname, $entry);
                list($mtype_primary, $mtype_secondary) = explode('/', $ctype);

                if (in_array($mtype_primary, array('text', 'message'))) {
                    $files[] = $tmpfname;
                }
                else {
                    unlink($tmpfname);
                }
            }
        }

        $zip->close();
    }

    return $files;
}

function rcmail_save_message($folder, &$message)
{
    if (strncmp($message, 'From ', 5) === 0) {
        // Extract the mbox from_line
        $pos     = strpos($message, "\n");
        $from    = substr($message, 0, $pos);
        $message = substr($message, $pos + 1);

        // Read the received date, support only known date formats

        // RFC4155: "Sat Jan  3 01:05:34 1996"
        $mboxdate_rx = '/^([a-z]{3} [a-z]{3} [0-9 ][0-9] [0-9]{2}:[0-9]{2}:[0-9]{2} [0-9]{4})/i';
        // Roundcube/Zipdownload: "12-Dec-2016 10:56:33 +0100"
        $imapdate_rx = '/^([0-9]{1,2}-[a-z]{3}-[0-9]{4} [0-9]{2}:[0-9]{2}:[0-9]{2} [0-9+-]{5})/i';

        if (($pos = strpos($from, ' ', 6)) && ($dt_str = substr($from, $pos + 1))
            && (preg_match($mboxdate_rx, $dt_str, $m) || preg_match($imapdate_rx, $dt_str, $m))
        ) {
           try {
               $date = new DateTime($m[0], new DateTimeZone('UTC'));
            }
            catch (Exception $e) {
                // ignore
            }
        }
    }

    // unquote ">From " lines in message body
    $message = preg_replace('/\n>([>]*)From /', "\n\\1From ", $message);
    $message = rtrim($message);
    $rcmail  = rcmail::get_instance();

    if ($rcmail->storage->save_message($folder, $message, '', false, array(), $date)) {
        return true;
    }

    rcube::raise_error("Failed to import message to $folder", true, false);
    return false;
}
