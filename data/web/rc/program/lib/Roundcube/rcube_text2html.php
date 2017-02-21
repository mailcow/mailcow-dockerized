<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2008-2014, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Converts plain text to HTML                                         |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
 */

/**
 * Converts plain text to HTML
 *
 * @package    Framework
 * @subpackage Utils
 */
class rcube_text2html
{
    /**
     * Contains the HTML content after conversion.
     *
     * @var string $html
     */
    protected $html;

    /**
     * Contains the plain text.
     *
     * @var string $text
     */
    protected $text;

    /**
     * Configuration
     *
     * @var array $config
     */
    protected $config = array(
        // non-breaking space
        'space' => "\xC2\xA0",
        // enables format=flowed parser
        'flowed' => false,
        // enables wrapping for non-flowed text
        'wrap' => true,
        // line-break tag
        'break' => "<br>\n",
        // prefix and suffix (wrapper element)
        'begin' => '<div class="pre">',
        'end'   => '</div>',
        // enables links replacement
        'links' => true,
        // string replacer class
        'replacer' => 'rcube_string_replacer',
        // prefix and suffix of unwrappable line
        'nobr_start' => '<span style="white-space:nowrap">',
        'nobr_end'   => '</span>',
    );


    /**
     * Constructor.
     *
     * If the plain text source string (or file) is supplied, the class
     * will instantiate with that source propagated, all that has
     * to be done it to call get_html().
     *
     * @param string  $source    Plain text
     * @param boolean $from_file Indicates $source is a file to pull content from
     * @param array   $config    Class configuration
     */
    function __construct($source = '', $from_file = false, $config = array())
    {
        if (!empty($source)) {
            $this->set_text($source, $from_file);
        }

        if (!empty($config) && is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }
    }

    /**
     * Loads source text into memory, either from $source string or a file.
     *
     * @param string  $source    Plain text
     * @param boolean $from_file Indicates $source is a file to pull content from
     */
    function set_text($source, $from_file = false)
    {
        if ($from_file && file_exists($source)) {
            $this->text = file_get_contents($source);
        }
        else {
            $this->text = $source;
        }

        $this->_converted = false;
    }

    /**
     * Returns the HTML content.
     *
     * @return string HTML content
     */
    function get_html()
    {
        if (!$this->_converted) {
            $this->_convert();
        }

        return $this->html;
    }

    /**
     * Prints the HTML.
     */
    function print_html()
    {
        print $this->get_html();
    }

    /**
     * Workhorse function that does actual conversion (calls _converter() method).
     */
    protected function _convert()
    {
        // Convert TXT to HTML
        $this->html       = $this->_converter($this->text);
        $this->_converted = true;
    }

    /**
     * Workhorse function that does actual conversion.
     *
     * @param string Plain text
     */
    protected function _converter($text)
    {
        // make links and email-addresses clickable
        $attribs  = array('link_attribs' => array('rel' => 'noreferrer', 'target' => '_blank'));
        $replacer = new $this->config['replacer']($attribs);

        if ($this->config['flowed']) {
            $flowed_char = 0x01;
            $text        = rcube_mime::unfold_flowed($text, chr($flowed_char));
        }

        // search for patterns like links and e-mail addresses and replace with tokens
        if ($this->config['links']) {
            $text = $replacer->replace($text);
        }

        // split body into single lines
        $text        = preg_split('/\r?\n/', $text);
        $quote_level = 0;
        $last        = null;

        // wrap quoted lines with <blockquote>
        for ($n = 0, $cnt = count($text); $n < $cnt; $n++) {
            $flowed = false;
            if ($this->config['flowed'] && ord($text[$n][0]) == $flowed_char) {
                $flowed   = true;
                $text[$n] = substr($text[$n], 1);
            }

            if ($text[$n][0] == '>' && preg_match('/^(>+ {0,1})+/', $text[$n], $regs)) {
                $q        = substr_count($regs[0], '>');
                $text[$n] = substr($text[$n], strlen($regs[0]));
                $text[$n] = $this->_convert_line($text[$n], $flowed || $this->config['wrap']);
                $_length  = strlen(str_replace(' ', '', $text[$n]));

                if ($q > $quote_level) {
                    if ($last !== null) {
                        $text[$last] .= (!$length ? "\n" : '')
                            . $replacer->get_replacement($replacer->add(
                                str_repeat('<blockquote>', $q - $quote_level)))
                            . $text[$n];

                        unset($text[$n]);
                    }
                    else {
                        $text[$n] = $replacer->get_replacement($replacer->add(
                            str_repeat('<blockquote>', $q - $quote_level))) . $text[$n];

                        $last = $n;
                    }
                }
                else if ($q < $quote_level) {
                    $text[$last] .= (!$length ? "\n" : '')
                        . $replacer->get_replacement($replacer->add(
                            str_repeat('</blockquote>', $quote_level - $q)))
                        . $text[$n];

                    unset($text[$n]);
                }
                else {
                    $last = $n;
                }
            }
            else {
                $text[$n] = $this->_convert_line($text[$n], $flowed || $this->config['wrap']);
                $q        = 0;
                $_length  = strlen(str_replace(' ', '', $text[$n]));

                if ($quote_level > 0) {
                    $text[$last] .= (!$length ? "\n" : '')
                        . $replacer->get_replacement($replacer->add(
                            str_repeat('</blockquote>', $quote_level)))
                        . $text[$n];

                    unset($text[$n]);
                }
                else {
                    $last = $n;
                }
            }

            $quote_level = $q;
            $length      = $_length;
        }

        if ($quote_level > 0) {
            $text[$last] .= $replacer->get_replacement($replacer->add(
                str_repeat('</blockquote>', $quote_level)));
        }

        $text = join("\n", $text);

        // colorize signature (up to <sig_max_lines> lines)
        $len           = strlen($text);
        $sig_sep       = "--" . $this->config['space'] . "\n";
        $sig_max_lines = rcube::get_instance()->config->get('sig_max_lines', 15);

        while (($sp = strrpos($text, $sig_sep, $sp ? -$len+$sp-1 : 0)) !== false) {
            if ($sp == 0 || $text[$sp-1] == "\n") {
                // do not touch blocks with more that X lines
                if (substr_count($text, "\n", $sp) < $sig_max_lines) {
                    $text = substr($text, 0, max(0, $sp))
                        .'<span class="sig">'.substr($text, $sp).'</span>';
                }

                break;
            }
        }

        // insert url/mailto links and citation tags
        $text = $replacer->resolve($text);

        // replace line breaks
        $text = str_replace("\n", $this->config['break'], $text);

        return $this->config['begin'] . $text . $this->config['end'];
    }

    /**
     * Converts spaces in line of text
     */
    protected function _convert_line($text, $is_flowed)
    {
        static $table;

        if (empty($table)) {
            $table = get_html_translation_table(HTML_SPECIALCHARS);
            unset($table['?']);

            // replace some whitespace characters
            $table["\r"] = '';
            $table["\t"] = '    ';
        }

        // skip signature separator
        if ($text == '-- ') {
            return '--' . $this->config['space'];
        }

        // replace HTML special and whitespace characters
        $text = strtr($text, $table);

        $nbsp = $this->config['space'];

        // replace spaces with non-breaking spaces
        if ($is_flowed) {
            $pos  = 0;
            $diff = 0;
            $len  = strlen($nbsp);
            $copy = $text;

            while (($pos = strpos($text, ' ', $pos)) !== false) {
                if ($pos == 0 || $text[$pos-1] == ' ') {
                    $copy = substr_replace($copy, $nbsp, $pos + $diff, 1);
                    $diff += $len - 1;
                }
                $pos++;
            }

            $text = $copy;
        }
        // make the whole line non-breakable if needed
        else if ($text !== '' && preg_match('/[^a-zA-Z0-9_]/', $text)) {
            // use non-breakable spaces to correctly display
            // trailing/leading spaces and multi-space inside
            $text = str_replace(' ', $nbsp, $text);
            // wrap in nobr element, so it's not wrapped on e.g. - or /
            $text = $this->config['nobr_start'] . $text .  $this->config['nobr_end'];
        }

        return $text;
    }
}
