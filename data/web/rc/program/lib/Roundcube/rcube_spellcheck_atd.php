<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 |                                                                       |
 | Copyright (C) 2013, The Roundcube Dev Team                            |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Spellchecking backend implementation for afterthedeadline services  |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

/**
 * Spellchecking backend implementation to work with an After the Deadline service
 * See http://www.afterthedeadline.com/ for more information
 *
 * @package    Framework
 * @subpackage Utils
 */
class rcube_spellcheck_atd extends rcube_spellcheck_engine
{
    const SERVICE_HOST = 'service.afterthedeadline.com';
    const SERVICE_PORT = 80;

    private $matches = array();
    private $content;
    private $langhosts = array(
        'fr' => 'fr.',
        'de' => 'de.',
        'pt' => 'pt.',
        'es' => 'es.',
    );

    /**
     * Return a list of languages supported by this backend
     *
     * @see rcube_spellcheck_engine::languages()
     */
    function languages()
    {
        $langs = array_values($this->langhosts);
        $langs[] = 'en';
        return $langs;
    }

    /**
     * Set content and check spelling
     *
     * @see rcube_spellcheck_engine::check()
     */
    function check($text)
    {
        $this->content = $text;

        // spell check uri is configured
        $rcube = rcube::get_instance();
        $url = $rcube->config->get('spellcheck_uri');
        $key = $rcube->config->get('spellcheck_atd_key');

        if ($url) {
            $a_uri = parse_url($url);
            $ssl   = ($a_uri['scheme'] == 'https' || $a_uri['scheme'] == 'ssl');
            $port  = $a_uri['port'] ?: ($ssl ? 443 : 80);
            $host  = ($ssl ? 'ssl://' : '') . $a_uri['host'];
            $path  = $a_uri['path'] . ($a_uri['query'] ? '?'.$a_uri['query'] : '') . $this->lang;
        }
        else {
            $host = self::SERVICE_HOST;
            $port = self::SERVICE_PORT;
            $path = '/checkDocument';

            // prefix host for other languages than 'en'
            $lang = substr($this->lang, 0, 2);
            if ($this->langhosts[$lang])
                $host = $this->langhosts[$lang] . $host;
        }

        $postdata = 'data=' . urlencode($text);

        if (!empty($key))
            $postdata .= '&key=' . urlencode($key);

        $response = $headers = '';
        $in_header = true;
        if ($fp = fsockopen($host, $port, $errno, $errstr, 30)) {
            $out = "POST $path HTTP/1.0\r\n";
            $out .= "Host: " . str_replace('ssl://', '', $host) . "\r\n";
            $out .= "Content-Length: " . strlen($postdata) . "\r\n";
            $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $out .= "Connection: Close\r\n\r\n";
            $out .= $postdata;
            fwrite($fp, $out);

            while (!feof($fp)) {
                if ($in_header) {
                    $line = fgets($fp, 512);
                    $headers .= $line;
                    if (trim($line) == '')
                        $in_header = false;
                }
                else {
                    $response .= fgets($fp, 1024);
                }
            }
            fclose($fp);
        }

        // parse HTTP response headers
        if (preg_match('!^HTTP/1.\d (\d+)(.+)!', $headers, $m)) {
            $http_status = $m[1];
            if ($http_status != '200')
                $this->error = 'HTTP ' . $m[1] . $m[2];
        }

        if (!$response) {
            $this->error = "Empty result from spelling engine";
        }

        try {
            $result = new SimpleXMLElement($response);
        }
        catch (Exception $e) {
            $this->error = "Unexpected response from server: " . $response;
            return array();
        }

        foreach ($result->error as $error) {
            if (strval($error->type) == 'spelling') {
                $word = strval($error->string);

                // skip exceptions
                if ($this->dictionary->is_exception($word)) {
                    continue;
                }

                $prefix = strval($error->precontext);
                $start = $prefix ? mb_strpos($text, $prefix) : 0;
                $pos = mb_strpos($text, $word, $start);
                $len = mb_strlen($word);
                $num = 0;

                $match = array($word, $pos, $len, null, array());
                foreach ($error->suggestions->option as $option) {
                    $match[4][] = strval($option);
                    if (++$num == self::MAX_SUGGESTIONS)
                        break;
                }
                $matches[] = $match;
            }
        }

        $this->matches = $matches;
        return $matches;
    }

    /**
     * Returns suggestions for the specified word
     *
     * @see rcube_spellcheck_engine::get_words()
     */
    function get_suggestions($word)
    {
        $matches = $word ? $this->check($word) : $this->matches;

        if ($matches[0][4]) {
            return $matches[0][4];
        }

        return array();
    }

    /**
     * Returns misspelled words
     *
     * @see rcube_spellcheck_engine::get_suggestions()
     */
    function get_words($text = null)
    {
        if ($text) {
            $matches = $this->check($text);
        }
        else {
            $matches = $this->matches;
            $text    = $this->content;
        }

        $result = array();

        foreach ($matches as $m) {
            $result[] = mb_substr($text, $m[1], $m[2], RCUBE_CHARSET);
        }

        return $result;
    }
}
