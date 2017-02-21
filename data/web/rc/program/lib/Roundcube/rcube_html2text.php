<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2008-2012, The Roundcube Dev Team                       |
 | Copyright (c) 2005-2007, Jon Abernathy <jon@chuggnutt.com>            |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Converts HTML to formatted plain text (based on html2text class)    |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 | Author: Jon Abernathy <jon@chuggnutt.com>                             |
 +-----------------------------------------------------------------------+
 */

/**
 *  Takes HTML and converts it to formatted, plain text.
 *
 *  Thanks to Alexander Krug (http://www.krugar.de/) to pointing out and
 *  correcting an error in the regexp search array. Fixed 7/30/03.
 *
 *  Updated set_html() function's file reading mechanism, 9/25/03.
 *
 *  Thanks to Joss Sanglier (http://www.dancingbear.co.uk/) for adding
 *  several more HTML entity codes to the $search and $replace arrays.
 *  Updated 11/7/03.
 *
 *  Thanks to Darius Kasperavicius (http://www.dar.dar.lt/) for
 *  suggesting the addition of $allowed_tags and its supporting function
 *  (which I slightly modified). Updated 3/12/04.
 *
 *  Thanks to Justin Dearing for pointing out that a replacement for the
 *  <TH> tag was missing, and suggesting an appropriate fix.
 *  Updated 8/25/04.
 *
 *  Thanks to Mathieu Collas (http://www.myefarm.com/) for finding a
 *  display/formatting bug in the _build_link_list() function: email
 *  readers would show the left bracket and number ("[1") as part of the
 *  rendered email address.
 *  Updated 12/16/04.
 *
 *  Thanks to Wojciech Bajon (http://histeria.pl/) for submitting code
 *  to handle relative links, which I hadn't considered. I modified his
 *  code a bit to handle normal HTTP links and MAILTO links. Also for
 *  suggesting three additional HTML entity codes to search for.
 *  Updated 03/02/05.
 *
 *  Thanks to Jacob Chandler for pointing out another link condition
 *  for the _build_link_list() function: "https".
 *  Updated 04/06/05.
 *
 *  Thanks to Marc Bertrand (http://www.dresdensky.com/) for
 *  suggesting a revision to the word wrapping functionality; if you
 *  specify a $width of 0 or less, word wrapping will be ignored.
 *  Updated 11/02/06.
 *
 *  *** Big housecleaning updates below:
 *
 *  Thanks to Colin Brown (http://www.sparkdriver.co.uk/) for
 *  suggesting the fix to handle </li> and blank lines (whitespace).
 *  Christian Basedau (http://www.movetheweb.de/) also suggested the
 *  blank lines fix.
 *
 *  Special thanks to Marcus Bointon (http://www.synchromedia.co.uk/),
 *  Christian Basedau, Norbert Laposa (http://ln5.co.uk/),
 *  Bas van de Weijer, and Marijn van Butselaar
 *  for pointing out my glaring error in the <th> handling. Marcus also
 *  supplied a host of fixes.
 *
 *  Thanks to Jeffrey Silverman (http://www.newtnotes.com/) for pointing
 *  out that extra spaces should be compressed--a problem addressed with
 *  Marcus Bointon's fixes but that I had not yet incorporated.
 *
 *  Thanks to Daniel Schledermann (http://www.typoconsult.dk/) for
 *  suggesting a valuable fix with <a> tag handling.
 *
 *  Thanks to Wojciech Bajon (again!) for suggesting fixes and additions,
 *  including the <a> tag handling that Daniel Schledermann pointed
 *  out but that I had not yet incorporated. I haven't (yet)
 *  incorporated all of Wojciech's changes, though I may at some
 *  future time.
 *
 *  *** End of the housecleaning updates. Updated 08/08/07.
 */

/**
 * Converts HTML to formatted plain text
 *
 * @package    Framework
 * @subpackage Utils
 */
class rcube_html2text
{
    /**
     * Contains the HTML content to convert.
     *
     * @var string $html
     */
    protected $html;

    /**
     * Contains the converted, formatted text.
     *
     * @var string $text
     */
    protected $text;

    /**
     * Maximum width of the formatted text, in columns.
     *
     * Set this value to 0 (or less) to ignore word wrapping
     * and not constrain text to a fixed-width column.
     *
     * @var integer $width
     */
    protected $width = 70;

    /**
     * Target character encoding for output text
     *
     * @var string $charset
     */
    protected $charset = 'UTF-8';

    /**
     * List of preg* regular expression patterns to search for,
     * used in conjunction with $replace.
     *
     * @var array $search
     * @see $replace
     */
    protected $search = array(
        '/\r/',                                  // Non-legal carriage return
        '/^.*<body[^>]*>\n*/is',                 // Anything before <body>
        '/<head[^>]*>.*?<\/head>/is',            // <head>
        '/<script[^>]*>.*?<\/script>/is',        // <script>
        '/<style[^>]*>.*?<\/style>/is',          // <style>
        '/[\n\t]+/',                             // Newlines and tabs
        '/<p[^>]*>/i',                           // <p>
        '/<\/p>[\s\n\t]*<div[^>]*>/i',           // </p> before <div>
        '/<br[^>]*>[\s\n\t]*<div[^>]*>/i',       // <br> before <div>
        '/<br[^>]*>\s*/i',                       // <br>
        '/<i[^>]*>(.*?)<\/i>/i',                 // <i>
        '/<em[^>]*>(.*?)<\/em>/i',               // <em>
        '/(<ul[^>]*>|<\/ul>)/i',                 // <ul> and </ul>
        '/(<ol[^>]*>|<\/ol>)/i',                 // <ol> and </ol>
        '/<li[^>]*>(.*?)<\/li>/i',               // <li> and </li>
        '/<li[^>]*>/i',                          // <li>
        '/<hr[^>]*>/i',                          // <hr>
        '/<div[^>]*>/i',                         // <div>
        '/(<table[^>]*>|<\/table>)/i',           // <table> and </table>
        '/(<tr[^>]*>|<\/tr>)/i',                 // <tr> and </tr>
        '/<td[^>]*>(.*?)<\/td>/i',               // <td> and </td>
    );

    /**
     * List of pattern replacements corresponding to patterns searched.
     *
     * @var array $replace
     * @see $search
     */
    protected $replace = array(
        '',                                     // Non-legal carriage return
        '',                                     // Anything before <body>
        '',                                     // <head>
        '',                                     // <script>
        '',                                     // <style>
        ' ',                                    // Newlines and tabs
        "\n\n",                                 // <p>
        "\n<div>",                              // </p> before <div>
        '<div>',                                // <br> before <div>
        "\n",                                   // <br>
        '_\\1_',                                // <i>
        '_\\1_',                                // <em>
        "\n\n",                                 // <ul> and </ul>
        "\n\n",                                 // <ol> and </ol>
        "\t* \\1\n",                            // <li> and </li>
        "\n\t* ",                               // <li>
        "\n-------------------------\n",        // <hr>
        "<div>\n",                              // <div>
        "\n\n",                                 // <table> and </table>
        "\n",                                   // <tr> and </tr>
        "\t\t\\1\n",                            // <td> and </td>
    );

    /**
     * List of preg* regular expression patterns to search for,
     * used in conjunction with $ent_replace.
     *
     * @var array $ent_search
     * @see $ent_replace
     */
    protected $ent_search = array(
        '/&(nbsp|#160);/i',                      // Non-breaking space
        '/&(quot|rdquo|ldquo|#8220|#8221|#147|#148);/i',
                                         // Double quotes
        '/&(apos|rsquo|lsquo|#8216|#8217);/i',   // Single quotes
        '/&gt;/i',                               // Greater-than
        '/&lt;/i',                               // Less-than
        '/&(copy|#169);/i',                      // Copyright
        '/&(trade|#8482|#153);/i',               // Trademark
        '/&(reg|#174);/i',                       // Registered
        '/&(mdash|#151|#8212);/i',               // mdash
        '/&(ndash|minus|#8211|#8722);/i',        // ndash
        '/&(bull|#149|#8226);/i',                // Bullet
        '/&(pound|#163);/i',                     // Pound sign
        '/&(euro|#8364);/i',                     // Euro sign
        '/&(amp|#38);/i',                        // Ampersand: see _converter()
        '/[ ]{2,}/',                             // Runs of spaces, post-handling
    );

    /**
     * List of pattern replacements corresponding to patterns searched.
     *
     * @var array $ent_replace
     * @see $ent_search
     */
    protected $ent_replace = array(
        "\xC2\xA0",                             // Non-breaking space
        '"',                                    // Double quotes
        "'",                                    // Single quotes
        '>',
        '<',
        '(c)',
        '(tm)',
        '(R)',
        '--',
        '-',
        '*',
        '£',
        'EUR',                                  // Euro sign. €
        '|+|amp|+|',                            // Ampersand: see _converter()
        ' ',                                    // Runs of spaces, post-handling
    );

    /**
     * List of preg* regular expression patterns to search for
     * and replace using callback function.
     *
     * @var array $callback_search
     */
    protected $callback_search = array(
        '/<(a) [^>]*href=("|\')([^"\']+)\2[^>]*>(.*?)<\/a>/i', // <a href="">
        '/<(h)[123456]( [^>]*)?>(.*?)<\/h[123456]>/i',         // h1 - h6
        '/<(b)( [^>]*)?>(.*?)<\/b>/i',                         // <b>
        '/<(strong)( [^>]*)?>(.*?)<\/strong>/i',               // <strong>
        '/<(th)( [^>]*)?>(.*?)<\/th>/i',                       // <th> and </th>
    );

   /**
    * List of preg* regular expression patterns to search for in PRE body,
    * used in conjunction with $pre_replace.
    *
    * @var array $pre_search
    * @see $pre_replace
    */
    protected $pre_search = array(
        "/\n/",
        "/\t/",
        '/ /',
        '/<pre[^>]*>/',
        '/<\/pre>/'
    );

    /**
     * List of pattern replacements corresponding to patterns searched for PRE body.
     *
     * @var array $pre_replace
     * @see $pre_search
     */
    protected $pre_replace = array(
        '<br>',
        '&nbsp;&nbsp;&nbsp;&nbsp;',
        '&nbsp;',
        '',
        ''
    );

    /**
     * Contains a list of HTML tags to allow in the resulting text.
     *
     * @var string $allowed_tags
     * @see set_allowed_tags()
     */
    protected $allowed_tags = '';

    /**
     * Contains the base URL that relative links should resolve to.
     *
     * @var string $url
     */
    protected $url;

    /**
     * Indicates whether content in the $html variable has been converted yet.
     *
     * @var boolean $_converted
     * @see $html, $text
     */
    protected $_converted = false;

    /**
     * Contains URL addresses from links to be rendered in plain text.
     *
     * @var array $_link_list
     * @see _build_link_list()
     */
    protected $_link_list = array();

    /**
     * Boolean flag, true if a table of link URLs should be listed after the text.
     *
     * @var boolean $_do_links
     * @see __construct()
     */
    protected $_do_links = true;

    /**
     * Constructor.
     *
     * If the HTML source string (or file) is supplied, the class
     * will instantiate with that source propagated, all that has
     * to be done it to call get_text().
     *
     * @param string  $source    HTML content
     * @param boolean $from_file Indicates $source is a file to pull content from
     * @param boolean $do_links  Indicate whether a table of link URLs is desired
     * @param integer $width     Maximum width of the formatted text, 0 for no limit
     */
    function __construct($source = '', $from_file = false, $do_links = true, $width = 75, $charset = 'UTF-8')
    {
        if (!empty($source)) {
            $this->set_html($source, $from_file);
        }

        $this->set_base_url();

        $this->_do_links = $do_links;
        $this->width     = $width;
        $this->charset   = $charset;
    }

    /**
     * Loads source HTML into memory, either from $source string or a file.
     *
     * @param string  $source    HTML content
     * @param boolean $from_file Indicates $source is a file to pull content from
     */
    function set_html($source, $from_file = false)
    {
        if ($from_file && file_exists($source)) {
            $this->html = file_get_contents($source);
        }
        else {
            $this->html = $source;
        }

        $this->_converted = false;
    }

    /**
     * Returns the text, converted from HTML.
     *
     * @return string Plain text
     */
    function get_text()
    {
        if (!$this->_converted) {
            $this->_convert();
        }

        return $this->text;
    }

    /**
     * Prints the text, converted from HTML.
     */
    function print_text()
    {
        print $this->get_text();
    }

    /**
     * Sets the allowed HTML tags to pass through to the resulting text.
     *
     * Tags should be in the form "<p>", with no corresponding closing tag.
     */
    function set_allowed_tags($allowed_tags = '')
    {
        if (!empty($allowed_tags)) {
            $this->allowed_tags = $allowed_tags;
        }
    }

    /**
     * Sets a base URL to handle relative links.
     */
    function set_base_url($url = '')
    {
        if (empty($url)) {
            if (!empty($_SERVER['HTTP_HOST'])) {
                $this->url = 'http://' . $_SERVER['HTTP_HOST'];
            }
            else {
                $this->url = '';
            }
        }
        else {
            // Strip any trailing slashes for consistency (relative
            // URLs may already start with a slash like "/file.html")
            if (substr($url, -1) == '/') {
                $url = substr($url, 0, -1);
            }
            $this->url = $url;
        }
    }

    /**
     * Workhorse function that does actual conversion (calls _converter() method).
     */
    protected function _convert()
    {
        // Variables used for building the link list
        $this->_link_list = array();

        $text = $this->html;

        // Convert HTML to TXT
        $this->_converter($text);

        // Add link list
        if (!empty($this->_link_list)) {
            $text .= "\n\nLinks:\n------\n";
            foreach ($this->_link_list as $idx => $url) {
                $text .= '[' . ($idx+1) . '] ' . $url . "\n";
            }
        }

        $this->text       = $text;
        $this->_converted = true;
    }

    /**
     * Workhorse function that does actual conversion.
     *
     * First performs custom tag replacement specified by $search and
     * $replace arrays. Then strips any remaining HTML tags, reduces whitespace
     * and newlines to a readable format, and word wraps the text to
     * $width characters.
     *
     * @param string &$text Reference to HTML content string
     */
    protected function _converter(&$text)
    {
        // Convert <BLOCKQUOTE> (before PRE!)
        $this->_convert_blockquotes($text);

        // Convert <PRE>
        $this->_convert_pre($text);

        // Run our defined tags search-and-replace
        $text = preg_replace($this->search, $this->replace, $text);

        // Run our defined tags search-and-replace with callback
        $text = preg_replace_callback($this->callback_search, array($this, 'tags_preg_callback'), $text);

        // Strip any other HTML tags
        $text = strip_tags($text, $this->allowed_tags);

        // Run our defined entities/characters search-and-replace
        $text = preg_replace($this->ent_search, $this->ent_replace, $text);

        // Replace known html entities
        $text = html_entity_decode($text, ENT_QUOTES, $this->charset);

        // Replace unicode nbsp to regular spaces
        $text = preg_replace('/\xC2\xA0/', ' ', $text);

        // Remove unknown/unhandled entities (this cannot be done in search-and-replace block)
        $text = preg_replace('/&([a-zA-Z0-9]{2,6}|#[0-9]{2,4});/', '', $text);

        // Convert "|+|amp|+|" into "&", need to be done after handling of unknown entities
        // This properly handles situation of "&amp;quot;" in input string
        $text = str_replace('|+|amp|+|', '&', $text);

        // Bring down number of empty lines to 2 max
        $text = preg_replace("/\n\s+\n/", "\n\n", $text);
        $text = preg_replace("/[\n]{3,}/", "\n\n", $text);

        // remove leading empty lines (can be produced by eg. P tag on the beginning)
        $text = ltrim($text, "\n");

        // Wrap the text to a readable format
        // for PHP versions >= 4.0.2. Default width is 75
        // If width is 0 or less, don't wrap the text.
        if ( $this->width > 0 ) {
            $text = wordwrap($text, $this->width);
        }
    }

    /**
     * Helper function called by preg_replace() on link replacement.
     *
     * Maintains an internal list of links to be displayed at the end of the
     * text, with numeric indices to the original point in the text they
     * appeared. Also makes an effort at identifying and handling absolute
     * and relative links.
     *
     * @param string $link    URL of the link
     * @param string $display Part of the text to associate number with
     */
    protected function _build_link_list($link, $display)
    {
        if (!$this->_do_links || empty($link)) {
            return $display;
        }

        // Ignored link types
        if (preg_match('!^(javascript:|mailto:|#)!i', $link)) {
            return $display;
        }

        // skip links with href == content (#1490434)
        if ($link === $display) {
            return $display;
        }

        if (preg_match('!^([a-z][a-z0-9.+-]+:)!i', $link)) {
            $url = $link;
        }
        else {
            $url = $this->url;
            if (substr($link, 0, 1) != '/') {
                $url .= '/';
            }
            $url .= "$link";
        }

        if (($index = array_search($url, $this->_link_list)) === false) {
            $index = count($this->_link_list);
            $this->_link_list[] = $url;
        }

        return $display . ' [' . ($index+1) . ']';
    }

    /**
     * Helper function for PRE body conversion.
     *
     * @param string &$text HTML content
     */
    protected function _convert_pre(&$text)
    {
        // get the content of PRE element
        while (preg_match('/<pre[^>]*>(.*)<\/pre>/ismU', $text, $matches)) {
            $this->pre_content = $matches[1];

            // Run our defined tags search-and-replace with callback
            $this->pre_content = preg_replace_callback($this->callback_search,
                array($this, 'tags_preg_callback'), $this->pre_content);

            // convert the content
            $this->pre_content = sprintf('<div><br>%s<br></div>',
                preg_replace($this->pre_search, $this->pre_replace, $this->pre_content));

            // replace the content (use callback because content can contain $0 variable)
            $text = preg_replace_callback('/<pre[^>]*>.*<\/pre>/ismU',
                array($this, 'pre_preg_callback'), $text, 1);

            // free memory
            $this->pre_content = '';
        }
    }

    /**
     * Helper function for BLOCKQUOTE body conversion.
     *
     * @param string &$text HTML content
     */
    protected function _convert_blockquotes(&$text)
    {
        $level = 0;
        $offset = 0;
        while (($start = stripos($text, '<blockquote', $offset)) !== false) {
            $offset = $start + 12;
            do {
                $end = stripos($text, '</blockquote>', $offset);
                $next = stripos($text, '<blockquote', $offset);

                // nested <blockquote>, skip
                if ($next !== false && $next < $end) {
                    $offset = $next + 12;
                    $level++;
                }
                // nested </blockquote> tag
                if ($end !== false && $level > 0) {
                    $offset = $end + 12;
                    $level--;
                }
                // found matching end tag
                else if ($end !== false && $level == 0) {
                    $taglen = strpos($text, '>', $start) - $start;
                    $startpos = $start + $taglen + 1;

                    // get blockquote content
                    $body = trim(substr($text, $startpos, $end - $startpos));

                    // adjust text wrapping width
                    $p_width = $this->width;
                    if ($this->width > 0) $this->width -= 2;

                    // replace content with inner blockquotes
                    $this->_converter($body);

                    // resore text width
                    $this->width = $p_width;

                    // Add citation markers and create <pre> block
                    $body = preg_replace_callback('/((?:^|\n)>*)([^\n]*)/', array($this, 'blockquote_citation_callback'), trim($body));
                    $body = '<pre>' . htmlspecialchars($body) . '</pre>';

                    $text = substr_replace($text, $body . "\n", $start, $end + 13 - $start);
                    $offset = 0;

                    break;
                }
                // abort on invalid tag structure (e.g. no closing tag found)
                else {
                    break;
                }
            }
            while ($end || $next);
        }
    }

    /**
     * Callback function to correctly add citation markers for blockquote contents
     */
    public function blockquote_citation_callback($m)
    {
        $line  = ltrim($m[2]);
        $space = $line[0] == '>' ? '' : ' ';

        return $m[1] . '>' . $space . $line;
    }

    /**
     * Callback function for preg_replace_callback use.
     *
     * @param array $matches PREG matches
     * @return string
     */
    public function tags_preg_callback($matches)
    {
        switch (strtolower($matches[1])) {
        case 'b':
        case 'strong':
            return $this->_toupper($matches[3]);
        case 'th':
            return $this->_toupper("\t\t". $matches[3] ."\n");
        case 'h':
            return $this->_toupper("\n\n". $matches[3] ."\n\n");
        case 'a':
            // Remove spaces in URL (#1487805)
            $url = str_replace(' ', '', $matches[3]);
            return $this->_build_link_list($url, $matches[4]);
        }
    }

    /**
     * Callback function for preg_replace_callback use in PRE content handler.
     *
     * @param array $matches PREG matches
     * @return string
     */
    public function pre_preg_callback($matches)
    {
        return $this->pre_content;
    }

    /**
     * Strtoupper function with HTML tags and entities handling.
     *
     * @param string $str Text to convert
     * @return string Converted text
     */
    private function _toupper($str)
    {
        // string can containg HTML tags
        $chunks = preg_split('/(<[^>]*>)/', $str, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

        // convert toupper only the text between HTML tags
        foreach ($chunks as $idx => $chunk) {
            if ($chunk[0] != '<') {
                $chunks[$idx] = $this->_strtoupper($chunk);
            }
        }

        return implode($chunks);
    }

    /**
     * Strtoupper multibyte wrapper function with HTML entities handling.
     *
     * @param string $str Text to convert
     * @return string Converted text
     */
    private function _strtoupper($str)
    {
        $str = html_entity_decode($str, ENT_COMPAT, $this->charset);
        $str = mb_strtoupper($str);
        $str = htmlspecialchars($str, ENT_COMPAT, $this->charset);

        return $str;
    }
}
