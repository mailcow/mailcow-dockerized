<?php

/**
 +-------------------------------------------------------------------------+
 | Mail_mime wrapper for the Enigma Plugin                                 |
 |                                                                         |
 | Copyright (C) 2010-2015 The Roundcube Dev Team                          |
 |                                                                         |
 | Licensed under the GNU General Public License version 3 or              |
 | any later version with exceptions for skins & plugins.                  |
 | See the README file for a full license statement.                       |
 |                                                                         |
 +-------------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                              |
 +-------------------------------------------------------------------------+
*/

class enigma_mime_message extends Mail_mime
{
    const PGP_SIGNED    = 1;
    const PGP_ENCRYPTED = 2;

    protected $type;
    protected $message;
    protected $body;
    protected $signature;
    protected $encrypted;


    /**
     * Object constructor
     *
     * @param Mail_mime Original message
     * @param int       Output message type
     */
    function __construct($message, $type)
    {
        $this->message = $message;
        $this->type    = $type;

        // clone parameters
        foreach (array_keys($this->build_params) as $param) {
            $this->build_params[$param] = $message->getParam($param);
        }

        // clone headers
        $this->headers = $message->headers();

        // \r\n is must-have here
        $this->body = $message->get() . "\r\n";
    }

    /**
     * Check if the message is multipart (requires PGP/MIME)
     *
     * @return bool True if it is multipart, otherwise False
     */
    public function isMultipart()
    {
        return $this->message instanceof enigma_mime_message
            || $this->message->isMultipart() || $this->message->getHTMLBody();
    }

    /**
     * Get e-mail address of message sender
     *
     * @return string Sender address
     */
    public function getFromAddress()
    {
        // get sender address
        $headers = $this->message->headers();
        $from    = rcube_mime::decode_address_list($headers['From'], 1, false, null, true);
        $from    = $from[1];

        return $from;
    }

    /**
     * Get recipients' e-mail addresses
     *
     * @return array Recipients' addresses
     */
    public function getRecipients()
    {
        // get sender address
        $headers = $this->message->headers();
        $to      = rcube_mime::decode_address_list($headers['To'], null, false, null, true);
        $cc      = rcube_mime::decode_address_list($headers['Cc'], null, false, null, true);
        $bcc     = rcube_mime::decode_address_list($headers['Bcc'], null, false, null, true);

        $recipients = array_unique(array_merge($to, $cc, $bcc));
        $recipients = array_diff($recipients, array('undisclosed-recipients:'));

        return $recipients;
    }

    /**
     * Get original message body, to be encrypted/signed
     *
     * @return string Message body
     */
    public function getOrigBody()
    {
        $_headers = $this->message->headers();
        $headers  = array();

        if ($_headers['Content-Transfer-Encoding']
            && stripos($_headers['Content-Type'], 'multipart') === false
        ) {
            $headers[] = 'Content-Transfer-Encoding: ' . $_headers['Content-Transfer-Encoding'];
        }
        $headers[] = 'Content-Type: ' . $_headers['Content-Type'];

        return implode("\r\n", $headers) . "\r\n\r\n" . $this->body;
    }

    /**
     * Register signature attachment
     *
     * @param string Signature body
     */
    public function addPGPSignature($body)
    {
        $this->signature = $body;

        // Reset Content-Type to be overwritten with valid boundary
        unset($this->headers['Content-Type']);
        unset($this->headers['Content-Transfer-Encoding']);
    }

    /**
     * Register encrypted body
     *
     * @param string Encrypted body
     */
    public function setPGPEncryptedBody($body)
    {
        $this->encrypted = $body;

        // Reset Content-Type to be overwritten with valid boundary
        unset($this->headers['Content-Type']);
        unset($this->headers['Content-Transfer-Encoding']);
    }

    /**
     * Builds the multipart message.
     *
     * @param array    $params    Build parameters that change the way the email
     *                            is built. Should be associative. See $_build_params.
     * @param resource $filename  Output file where to save the message instead of
     *                            returning it
     * @param boolean  $skip_head True if you want to return/save only the message
     *                            without headers
     *
     * @return mixed The MIME message content string, null or PEAR error object
     */
    public function get($params = null, $filename = null, $skip_head = false)
    {
        if (isset($params)) {
            while (list($key, $value) = each($params)) {
                $this->build_params[$key] = $value;
            }
        }

        $this->checkParams();

        if ($this->type == self::PGP_SIGNED) {
            $params = array(
                'preamble'     => "This is an OpenPGP/MIME signed message (RFC 4880 and 3156)",
                'content_type' => "multipart/signed; micalg=pgp-sha1; protocol=\"application/pgp-signature\"",
                'eol'          => $this->build_params['eol'],
            );

            $message = new Mail_mimePart('', $params);

            if (!empty($this->body)) {
                $headers = $this->message->headers();
                $params  = array('content_type' => $headers['Content-Type']);

                if ($headers['Content-Transfer-Encoding']
                    && stripos($headers['Content-Type'], 'multipart') === false
                ) {
                    $params['encoding'] = $headers['Content-Transfer-Encoding'];
                }

                $message->addSubpart($this->body, $params);
            }

            if (!empty($this->signature)) {
                $message->addSubpart($this->signature, array(
                    'filename'     => 'signature.asc',
                    'content_type' => 'application/pgp-signature',
                    'disposition'  => 'attachment',
                    'description'  => 'OpenPGP digital signature',
                ));
            }
        }
        else if ($this->type == self::PGP_ENCRYPTED) {
            $params = array(
                'preamble'     => "This is an OpenPGP/MIME encrypted message (RFC 4880 and 3156)",
                'content_type' => "multipart/encrypted; protocol=\"application/pgp-encrypted\"",
                'eol'          => $this->build_params['eol'],
            );

            $message = new Mail_mimePart('', $params);

            $message->addSubpart('Version: 1', array(
                    'content_type' => 'application/pgp-encrypted',
                    'description'  => 'PGP/MIME version identification',
            ));

            $message->addSubpart($this->encrypted, array(
                    'content_type' => 'application/octet-stream',
                    'description'  => 'PGP/MIME encrypted message',
                    'disposition'  => 'inline',
                    'filename'     => 'encrypted.asc',
            ));
        }

        // Use saved boundary
        if (!empty($this->build_params['boundary'])) {
            $boundary = $this->build_params['boundary'];
        }
        else {
            $boundary = null;
        }

        // Write output to file
        if ($filename) {
            // Append mimePart message headers and body into file
            $headers = $message->encodeToFile($filename, $boundary, $skip_head);

            if ($this->isError($headers)) {
                return $headers;
            }

            $this->headers = array_merge($this->headers, $headers);

            return;
        }
        else {
            $output = $message->encode($boundary, $skip_head);

            if ($this->isError($output)) {
                return $output;
            }

            $this->headers = array_merge($this->headers, $output['headers']);

            return $output['body'];
        }
    }

    /**
     * Get Content-Type and Content-Transfer-Encoding headers of the message
     *
     * @return array Headers array
     */
    protected function contentHeaders()
    {
        $this->checkParams();

        $eol = $this->build_params['eol'] ?: "\r\n";

        // multipart message: and boundary
        if (!empty($this->build_params['boundary'])) {
            $boundary = $this->build_params['boundary'];
        }
        else if (!empty($this->headers['Content-Type'])
            && preg_match('/boundary="([^"]+)"/', $this->headers['Content-Type'], $m)
        ) {
            $boundary = $m[1];
        }
        else {
            $boundary = '=_' . md5(rand() . microtime());
        }

        $this->build_params['boundary'] = $boundary;

        if ($this->type == self::PGP_SIGNED) {
            $headers['Content-Type'] = "multipart/signed; micalg=pgp-sha1;$eol"
                ." protocol=\"application/pgp-signature\";$eol"
                ." boundary=\"$boundary\"";
        }
        else if ($this->type == self::PGP_ENCRYPTED) {
            $headers['Content-Type'] = "multipart/encrypted;$eol"
                ." protocol=\"application/pgp-encrypted\";$eol"
                ." boundary=\"$boundary\"";
        }

        return $headers;
    }
}
