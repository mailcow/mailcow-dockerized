<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2008-2012, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Utility class providing HTML sanityzer (based on Washtml class)     |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 | Author: Frederic Motte <fmotte@ubixis.com>                            |
 +-----------------------------------------------------------------------+
 */

/*
 *                Washtml, a HTML sanityzer.
 *
 * Copyright (c) 2007 Frederic Motte <fmotte@ubixis.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * OVERVIEW:
 *
 * Wahstml take an untrusted HTML and return a safe html string.
 *
 * SYNOPSIS:
 *
 * $washer = new washtml($config);
 * $washer->wash($html);
 * It return a sanityzed string of the $html parameter without html and head tags.
 * $html is a string containing the html code to wash.
 * $config is an array containing options:
 *   $config['allow_remote'] is a boolean to allow link to remote images.
 *   $config['blocked_src'] string with image-src to be used for blocked remote images
 *   $config['show_washed'] is a boolean to include washed out attributes as x-washed
 *   $config['cid_map'] is an array where cid urls index urls to replace them.
 *   $config['charset'] is a string containing the charset of the HTML document if it is not defined in it.
 * $washer->extlinks is a reference to a boolean that is set to true if remote images were removed. (FE: show remote images link)
 *
 * INTERNALS:
 *
 * Only tags and attributes in the static lists $html_elements and $html_attributes
 * are kept, inline styles are also filtered: all style identifiers matching
 * /[a-z\-]/i are allowed. Values matching colors, sizes, /[a-z\-]/i and safe
 * urls if allowed and cid urls if mapped are kept.
 *
 * Roundcube Changes:
 * - added $block_elements
 * - changed $ignore_elements behaviour
 * - added RFC2397 support
 * - base URL support
 * - invalid HTML comments removal before parsing
 * - "fixing" unitless CSS values for XHTML output
 * - SVG and MathML support
 */

/**
 * Utility class providing HTML sanityzer
 *
 * @package    Framework
 * @subpackage Utils
 */
class rcube_washtml
{
    /* Allowed HTML elements (default) */
    static $html_elements = array('a', 'abbr', 'acronym', 'address', 'area', 'b',
        'basefont', 'bdo', 'big', 'blockquote', 'br', 'caption', 'center',
        'cite', 'code', 'col', 'colgroup', 'dd', 'del', 'dfn', 'dir', 'div', 'dl',
        'dt', 'em', 'fieldset', 'font', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i',
        'ins', 'label', 'legend', 'li', 'map', 'menu', 'nobr', 'ol', 'p', 'pre', 'q',
        's', 'samp', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'table',
        'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'tt', 'u', 'ul', 'var', 'wbr', 'img',
        'video', 'source',
        // form elements
        'button', 'input', 'textarea', 'select', 'option', 'optgroup',
        // SVG
        'svg', 'altglyph', 'altglyphdef', 'altglyphitem', 'animate',
        'animatecolor', 'animatetransform', 'circle', 'clippath', 'defs', 'desc',
        'ellipse', 'font', 'g', 'glyph', 'glyphref', 'hkern', 'image', 'line',
        'lineargradient', 'marker', 'mask', 'mpath', 'path', 'pattern',
        'polygon', 'polyline', 'radialgradient', 'rect', 'set', 'stop', 'switch', 'symbol',
        'text', 'textpath', 'tref', 'tspan', 'use', 'view', 'vkern', 'filter',
         // SVG Filters
        'feblend', 'fecolormatrix', 'fecomponenttransfer', 'fecomposite',
        'feconvolvematrix', 'fediffuselighting', 'fedisplacementmap',
        'feflood', 'fefunca', 'fefuncb', 'fefuncg', 'fefuncr', 'fegaussianblur',
        'feimage', 'femerge', 'femergenode', 'femorphology', 'feoffset',
        'fespecularlighting', 'fetile', 'feturbulence',
        // MathML
        'math', 'menclose', 'merror', 'mfenced', 'mfrac', 'mglyph', 'mi', 'mlabeledtr',
        'mmuliscripts', 'mn', 'mo', 'mover', 'mpadded', 'mphantom', 'mroot', 'mrow',
        'ms', 'mspace', 'msqrt', 'mstyle', 'msub', 'msup', 'msubsup', 'mtable', 'mtd',
        'mtext', 'mtr', 'munder', 'munderover', 'maligngroup', 'malignmark',
        'mprescripts', 'semantics', 'annotation', 'annotation-xml', 'none',
        'infinity', 'matrix', 'matrixrow', 'ci', 'cn', 'sep', 'apply',
        'plus', 'minus', 'eq', 'power', 'times', 'divide', 'csymbol', 'root',
        'bvar', 'lowlimit', 'uplimit',
    );

    /* Ignore these HTML tags and their content */
    static $ignore_elements = array('script', 'applet', 'embed', 'object', 'style');

    /* Allowed HTML attributes */
    static $html_attribs = array('name', 'class', 'title', 'alt', 'width', 'height',
        'align', 'nowrap', 'col', 'row', 'id', 'rowspan', 'colspan', 'cellspacing',
        'cellpadding', 'valign', 'bgcolor', 'color', 'border', 'bordercolorlight',
        'bordercolordark', 'face', 'marginwidth', 'marginheight', 'axis', 'border',
        'abbr', 'char', 'charoff', 'clear', 'compact', 'coords', 'vspace', 'hspace',
        'cellborder', 'size', 'lang', 'dir', 'usemap', 'shape', 'media',
        'background', 'src', 'poster', 'href',
        // attributes of form elements
        'type', 'rows', 'cols', 'disabled', 'readonly', 'checked', 'multiple', 'value',
        // SVG
        'accent-height', 'accumulate', 'additive', 'alignment-baseline', 'alphabetic',
        'ascent', 'attributename', 'attributetype', 'azimuth', 'basefrequency', 'baseprofile',
        'baseline-shift', 'begin', 'bias', 'by', 'clip', 'clip-path', 'clip-rule',
        'color', 'color-interpolation', 'color-interpolation-filters', 'color-profile',
        'color-rendering', 'cx', 'cy', 'd', 'dx', 'dy', 'diffuseconstant', 'direction',
        'display', 'divisor', 'dur', 'edgemode', 'elevation', 'end', 'fill', 'fill-opacity',
        'fill-rule', 'filter', 'flood-color', 'flood-opacity', 'font-family', 'font-size',
        'font-size-adjust', 'font-stretch', 'font-style', 'font-variant', 'font-weight', 'from',
        'fx', 'fy', 'g1', 'g2', 'glyph-name', 'glyphref', 'gradientunits', 'gradienttransform',
        'image-rendering', 'in', 'in2', 'k', 'k1', 'k2', 'k3', 'k4', 'kerning', 'keypoints',
        'keysplines', 'keytimes', 'lengthadjust', 'letter-spacing', 'kernelmatrix',
        'kernelunitlength', 'lighting-color', 'local', 'marker-end', 'marker-mid',
        'marker-start', 'markerheight', 'markerunits', 'markerwidth', 'maskcontentunits',
        'maskunits', 'max', 'mask', 'mode', 'min', 'numoctaves', 'offset', 'operator',
        'opacity', 'order', 'orient', 'orientation', 'origin', 'overflow', 'paint-order',
        'path', 'pathlength', 'patterncontentunits', 'patterntransform', 'patternunits',
        'points', 'preservealpha', 'r', 'rx', 'ry', 'radius', 'refx', 'refy', 'repeatcount',
        'repeatdur', 'restart', 'rotate', 'scale', 'seed', 'shape-rendering', 'show', 'specularconstant',
        'specularexponent', 'spreadmethod', 'stddeviation', 'stitchtiles', 'stop-color',
        'stop-opacity', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap',
        'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke', 'stroke-width',
        'surfacescale', 'targetx', 'targety', 'transform', 'text-anchor', 'text-decoration',
        'text-rendering', 'textlength', 'to', 'u1', 'u2', 'unicode', 'values', 'viewbox',
        'visibility', 'vert-adv-y', 'version', 'vert-origin-x', 'vert-origin-y', 'word-spacing',
        'wrap', 'writing-mode', 'xchannelselector', 'ychannelselector', 'x', 'x1', 'x2',
        'xmlns', 'y', 'y1', 'y2', 'z', 'zoomandpan',
        // MathML
        'accent', 'accentunder', 'bevelled', 'close', 'columnalign', 'columnlines',
        'columnspan', 'denomalign', 'depth', 'display', 'displaystyle', 'encoding', 'fence',
        'frame', 'largeop', 'length', 'linethickness', 'lspace', 'lquote',
        'mathbackground', 'mathcolor', 'mathsize', 'mathvariant', 'maxsize',
        'minsize', 'movablelimits', 'notation', 'numalign', 'open', 'rowalign',
        'rowlines', 'rowspacing', 'rowspan', 'rspace', 'rquote', 'scriptlevel',
        'scriptminsize', 'scriptsizemultiplier', 'selection', 'separator',
        'separators', 'stretchy', 'subscriptshift', 'supscriptshift', 'symmetric', 'voffset',
        'fontsize', 'fontweight', 'fontstyle', 'fontfamily', 'groupalign', 'edge', 'side',
    );

    /* Elements which could be empty and be returned in short form (<tag />) */
    static $void_elements = array('area', 'base', 'br', 'col', 'command', 'embed', 'hr',
        'img', 'input', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr',
        // MathML
        'sep', 'infinity', 'in', 'plus', 'eq', 'power', 'times', 'divide', 'root',
        'maligngroup', 'none', 'mprescripts',
    );

    /* State for linked objects in HTML */
    public $extlinks = false;

    /* Current settings */
    private $config = array();

    /* Registered callback functions for tags */
    private $handlers = array();

    /* Allowed HTML elements */
    private $_html_elements = array();

    /* Ignore these HTML tags but process their content */
    private $_ignore_elements = array();

    /* Elements which could be empty and be returned in short form (<tag />) */
    private $_void_elements = array();

    /* Allowed HTML attributes */
    private $_html_attribs = array();

    /* Max nesting level */
    private $max_nesting_level;

    private $is_xml = false;


    /**
     * Class constructor
     */
    public function __construct($p = array())
    {
        $this->_html_elements   = array_flip((array)$p['html_elements']) + array_flip(self::$html_elements);
        $this->_html_attribs    = array_flip((array)$p['html_attribs']) + array_flip(self::$html_attribs);
        $this->_ignore_elements = array_flip((array)$p['ignore_elements']) + array_flip(self::$ignore_elements);
        $this->_void_elements   = array_flip((array)$p['void_elements']) + array_flip(self::$void_elements);

        unset($p['html_elements'], $p['html_attribs'], $p['ignore_elements'], $p['void_elements']);

        $this->config = $p + array('show_washed' => true, 'allow_remote' => false, 'cid_map' => array());
    }

    /**
     * Register a callback function for a certain tag
     */
    public function add_callback($tagName, $callback)
    {
        $this->handlers[$tagName] = $callback;
    }

    /**
     * Check CSS style
     */
    private function wash_style($style)
    {
        $result = array();

        // Remove unwanted white-space characters so regular expressions below work better
        $style = preg_replace('/[\n\r\s\t]+/', ' ', $style);

        foreach (explode(';', $style) as $declaration) {
            if (preg_match('/^\s*([a-z\-]+)\s*:\s*(.*)\s*$/i', $declaration, $match)) {
                $cssid = $match[1];
                $str   = $match[2];
                $value = '';

                foreach ($this->explode_style($str) as $val) {
                    if (preg_match('/^url\(/i', $val)) {
                        if (preg_match('/^url\(\s*[\'"]?([^\'"\)]*)[\'"]?\s*\)/iu', $val, $match)) {
                            if ($url = $this->wash_uri($match[1])) {
                                $value .= ' url(' . htmlspecialchars($url, ENT_QUOTES) . ')';
                            }
                        }
                    }
                    else if (!preg_match('/^(behavior|expression)/i', $val)) {
                        // Set position:fixed to position:absolute for security (#5264)
                        if (!strcasecmp($cssid, 'position') && !strcasecmp($val, 'fixed')) {
                            $val = 'absolute';
                        }

                        // whitelist ?
                        $value .= ' ' . $val;

                        // #1488535: Fix size units, so width:800 would be changed to width:800px
                        if (preg_match('/^(left|right|top|bottom|width|height)/i', $cssid)
                            && preg_match('/^[0-9]+$/', $val)
                        ) {
                            $value .= 'px';
                        }
                    }
                }

                if (isset($value[0])) {
                    $result[] = $cssid . ':' . $value;
                }
            }
        }

        return implode('; ', $result);
    }

    /**
     * Take a node and return allowed attributes and check values
     */
    private function wash_attribs($node)
    {
        $result = '';
        $washed = array();

        foreach ($node->attributes as $name => $attr) {
            $key   = strtolower($name);
            $value = $attr->nodeValue;

            if ($key == 'style' && ($style = $this->wash_style($value))) {
                // replace double quotes to prevent syntax error and XSS issues (#1490227)
                $result .= ' style="' . str_replace('"', '&quot;', $style) . '"';
            }
            else if (isset($this->_html_attribs[$key])) {
                $value = trim($value);
                $out   = null;

                // in SVG to/from attribs may contain anything, including URIs
                if ($key == 'to' || $key == 'from') {
                    $key = strtolower($node->getAttribute('attributeName'));
                    if ($key && !isset($this->_html_attribs[$key])) {
                        $key = null;
                    }
                }

                if ($this->is_image_attribute($node->tagName, $key)) {
                    $out = $this->wash_uri($value, true);
                }
                else if ($this->is_link_attribute($node->tagName, $key)) {
                    if (!preg_match('!^(javascript|vbscript|data:text)!i', $value)
                        && preg_match('!^([a-z][a-z0-9.+-]+:|//|#).+!i', $value)
                    ) {
                        $out = $value;
                    }
                }
                else if ($this->is_funciri_attribute($node->tagName, $key)) {
                    if (preg_match('/^[a-z:]*url\(/i', $val)) {
                        if (preg_match('/^([a-z:]*url)\(\s*[\'"]?([^\'"\)]*)[\'"]?\s*\)/iu', $value, $match)) {
                            if ($url = $this->wash_uri($match[2])) {
                                $result .= ' ' . $attr->nodeName . '="' . $match[1] . '(' . htmlspecialchars($url, ENT_QUOTES) . ')'
                                     . substr($val, strlen($match[0])) . '"';
                                continue;
                            }
                        }
                        else {
                            $out = $value;
                        }
                    }
                    else {
                        $out = $value;
                    }
                }
                else if ($key) {
                   $out = $value;
                }

                if ($out !== null && $out !== '') {
                    $result .= ' ' . $attr->nodeName . '="' . htmlspecialchars($out, ENT_QUOTES) . '"';
                }
                else if ($value) {
                    $washed[] = htmlspecialchars($attr->nodeName, ENT_QUOTES);
                }
            }
            else {
                $washed[] = htmlspecialchars($attr->nodeName, ENT_QUOTES);
            }
        }

        if (!empty($washed) && $this->config['show_washed']) {
            $result .= ' x-washed="' . implode(' ', $washed) . '"';
        }

        return $result;
    }

    /**
     * Wash URI value
     */
    private function wash_uri($uri, $blocked_source = false)
    {
        if (($src = $this->config['cid_map'][$uri])
            || ($src = $this->config['cid_map'][$this->config['base_url'].$uri])
        ) {
            return $src;
        }

        // allow url(#id) used in SVG
        if ($uri[0] == '#') {
            return $uri;
        }

        if (preg_match('/^(http|https|ftp):.+/i', $uri)) {
            if ($this->config['allow_remote']) {
                return $uri;
            }

            $this->extlinks = true;
            if ($blocked_source && $this->config['blocked_src']) {
                return $this->config['blocked_src'];
            }
        }
        else if (preg_match('/^data:image.+/i', $uri)) { // RFC2397
            return $uri;
        }
    }

    /**
     * Check it the tag/attribute may contain an URI
     */
    private function is_link_attribute($tag, $attr)
    {
        return ($tag == 'a' || $tag == 'area') && $attr == 'href';
    }

    /**
     * Check it the tag/attribute may contain an image URI
     */
    private function is_image_attribute($tag, $attr)
    {
        return $attr == 'background'
            || $attr == 'color-profile' // SVG
            || ($attr == 'poster' && $tag == 'video')
            || ($attr == 'src' && preg_match('/^(img|source)$/i', $tag))
            || ($tag == 'image' && $attr == 'href'); // SVG
    }

    /**
     * Check it the tag/attribute may contain a FUNCIRI value
     */
    private function is_funciri_attribute($tag, $attr)
    {
        return in_array($attr, array('fill', 'filter', 'stroke', 'marker-start',
            'marker-end', 'marker-mid', 'clip-path', 'mask', 'cursor'));
    }

    /**
     * The main loop that recurse on a node tree.
     * It output only allowed tags with allowed attributes and allowed inline styles
     *
     * @param DOMNode $node  HTML element
     * @param int     $level Recurrence level (safe initial value found empirically)
     */
    private function dumpHtml($node, $level = 20)
    {
        if (!$node->hasChildNodes()) {
            return '';
        }

        $level++;

        if ($this->max_nesting_level > 0 && $level == $this->max_nesting_level - 1) {
            // log error message once
            if (!$this->max_nesting_level_error) {
                $this->max_nesting_level_error = true;
                rcube::raise_error(array('code' => 500, 'type' => 'php',
                    'line' => __LINE__, 'file' => __FILE__,
                    'message' => "Maximum nesting level exceeded (xdebug.max_nesting_level={$this->max_nesting_level})"),
                    true, false);
            }
            return '<!-- ignored -->';
        }

        $node = $node->firstChild;
        $dump = '';

        do {
            switch ($node->nodeType) {
            case XML_ELEMENT_NODE: //Check element
                $tagName = strtolower($node->tagName);
                if ($callback = $this->handlers[$tagName]) {
                    $dump .= call_user_func($callback, $tagName,
                        $this->wash_attribs($node), $this->dumpHtml($node, $level), $this);
                }
                else if (isset($this->_html_elements[$tagName])) {
                    $content = $this->dumpHtml($node, $level);
                    $dump .= '<' . $node->tagName;

                    if ($tagName == 'svg') {
                        $xpath = new DOMXPath($node->ownerDocument);
                        foreach ($xpath->query('namespace::*') as $ns) {
                            if ($ns->nodeName != 'xmlns:xml') {
                                $dump .= ' ' . $ns->nodeName . '="' . $ns->nodeValue . '"';
                            }
                        }
                    }

                    $dump .= $this->wash_attribs($node);

                    if ($content === '' && ($this->is_xml || isset($this->_void_elements[$tagName]))) {
                        $dump .= ' />';
                    }
                    else {
                        $dump .= '>' . $content . '</' . $node->tagName . '>';
                    }
                }
                else if (isset($this->_ignore_elements[$tagName])) {
                    $dump .= '<!-- ' . htmlspecialchars($node->tagName, ENT_QUOTES) . ' not allowed -->';
                }
                else {
                    $dump .= '<!-- ' . htmlspecialchars($node->tagName, ENT_QUOTES) . ' ignored -->';
                    $dump .= $this->dumpHtml($node, $level); // ignore tags not its content
                }
                break;

            case XML_CDATA_SECTION_NODE:
                $dump .= $node->nodeValue;
                break;

            case XML_TEXT_NODE:
                $dump .= htmlspecialchars($node->nodeValue);
                break;

            case XML_HTML_DOCUMENT_NODE:
                $dump .= $this->dumpHtml($node, $level);
                break;
            }
        }
        while($node = $node->nextSibling);

        return $dump;
    }

    /**
     * Main function, give it untrusted HTML, tell it if you allow loading
     * remote images and give it a map to convert "cid:" urls.
     */
    public function wash($html)
    {
        // Charset seems to be ignored (probably if defined in the HTML document)
        $node = new DOMDocument('1.0', $this->config['charset']);
        $this->extlinks = false;

        $html = $this->cleanup($html);

        // Find base URL for images
        if (preg_match('/<base\s+href=[\'"]*([^\'"]+)/is', $html, $matches)) {
            $this->config['base_url'] = $matches[1];
        }
        else {
            $this->config['base_url'] = '';
        }

        // Detect max nesting level (for dumpHTML) (#1489110)
        $this->max_nesting_level = (int) @ini_get('xdebug.max_nesting_level');

        // SVG need to be parsed as XML
        $this->is_xml = stripos($html, '<html') === false && stripos($html, '<svg') !== false;
        $method       = $this->is_xml ? 'loadXML' : 'loadHTML';
        $options      = 0;

        // Use optimizations if supported
        if (PHP_VERSION_ID >= 50400) {
            $options = LIBXML_PARSEHUGE | LIBXML_COMPACT | LIBXML_NONET;
            @$node->{$method}($html, $options);
        }
        else {
            @$node->{$method}($html);
        }

        return $this->dumpHtml($node);
    }

    /**
     * Getter for config parameters
     */
    public function get_config($prop)
    {
        return $this->config[$prop];
    }

    /**
     * Clean HTML input
     */
    private function cleanup($html)
    {
        $html = trim($html);

        // special replacements (not properly handled by washtml class)
        $html_search = array(
            // space(s) between <NOBR>
            '/(<\/nobr>)(\s+)(<nobr>)/i',
            // PHP bug #32547 workaround: remove title tag
            '/<title[^>]*>[^<]*<\/title>/i',
            // remove <!doctype> before BOM (#1490291)
            '/<\!doctype[^>]+>[^<]*/im',
            // byte-order mark (only outlook?)
            '/^(\0\0\xFE\xFF|\xFF\xFE\0\0|\xFE\xFF|\xFF\xFE|\xEF\xBB\xBF)/',
            // washtml/DOMDocument cannot handle xml namespaces
            '/<html\s[^>]+>/i',
        );

        $html_replace = array(
            '\\1'.' &nbsp; '.'\\3',
            '',
            '',
            '',
            '<html>',
        );

        $html = preg_replace($html_search, $html_replace, trim($html));

        // Replace all of those weird MS Word quotes and other high characters
        $badwordchars = array(
            "\xe2\x80\x98", // left single quote
            "\xe2\x80\x99", // right single quote
            "\xe2\x80\x9c", // left double quote
            "\xe2\x80\x9d", // right double quote
            "\xe2\x80\x94", // em dash
            "\xe2\x80\xa6"  // elipses
        );

        $fixedwordchars = array(
            "'",
            "'",
            '"',
            '"',
            '&mdash;',
            '...'
        );

        $html = str_replace($badwordchars, $fixedwordchars, $html);

        // PCRE errors handling (#1486856), should we use something like for every preg_* use?
        if ($html === null && ($preg_error = preg_last_error()) != PREG_NO_ERROR) {
            $errstr = "Could not clean up HTML message! PCRE Error: $preg_error.";

            if ($preg_error == PREG_BACKTRACK_LIMIT_ERROR) {
                $errstr .= " Consider raising pcre.backtrack_limit!";
            }
            if ($preg_error == PREG_RECURSION_LIMIT_ERROR) {
                $errstr .= " Consider raising pcre.recursion_limit!";
            }

            rcube::raise_error(array('code' => 620, 'type' => 'php',
                'line' => __LINE__, 'file' => __FILE__,
                'message' => $errstr), true, false);

            return '';
        }

        // fix (unknown/malformed) HTML tags before "wash"
        $html = preg_replace_callback('/(<(?!\!)[\/]*)([^\s>]+)([^>]*)/', array($this, 'html_tag_callback'), $html);

        // Remove invalid HTML comments (#1487759)
        // Don't remove valid conditional comments
        // Don't remove MSOutlook (<!-->) conditional comments (#1489004)
        $html = preg_replace('/<!--[^-<>\[\n]+>/', '', $html);

        // fix broken nested lists
        self::fix_broken_lists($html);

        // turn relative into absolute urls
        $html = self::resolve_base($html);

        return $html;
    }

    /**
     * Callback function for HTML tags fixing
     */
    public static function html_tag_callback($matches)
    {
        $tagname = $matches[2];
        $tagname = preg_replace(array(
            '/:.*$/',               // Microsoft's Smart Tags <st1:xxxx>
            '/[^a-z0-9_\[\]\!?-]/i', // forbidden characters
        ), '', $tagname);

        // fix invalid closing tags - remove any attributes (#1489446)
        if ($matches[1] == '</') {
            $matches[3] = '';
        }

        return $matches[1] . $tagname . $matches[3];
    }

    /**
     * Convert all relative URLs according to a <base> in HTML
     */
    public static function resolve_base($body)
    {
        // check for <base href=...>
        if (preg_match('!(<base.*href=["\']?)([hftps]{3,5}://[a-z0-9/.%-]+)!i', $body, $regs)) {
            $replacer = new rcube_base_replacer($regs[2]);
            $body     = $replacer->replace($body);
        }

        return $body;
    }

    /**
     * Fix broken nested lists, they are not handled properly by DOMDocument (#1488768)
     */
    public static function fix_broken_lists(&$html)
    {
        // do two rounds, one for <ol>, one for <ul>
        foreach (array('ol', 'ul') as $tag) {
            $pos = 0;
            while (($pos = stripos($html, '<' . $tag, $pos)) !== false) {
                $pos++;

                // make sure this is an ol/ul tag
                if (!in_array($html[$pos+2], array(' ', '>'))) {
                    continue;
                }

                $p      = $pos;
                $in_li  = false;
                $li_pos = 0;

                while (($p = strpos($html, '<', $p)) !== false) {
                    $tt = strtolower(substr($html, $p, 4));

                    // li open tag
                    if ($tt == '<li>' || $tt == '<li ') {
                        $in_li = true;
                        $p += 4;
                    }
                    // li close tag
                    else if ($tt == '</li' && in_array($html[$p+4], array(' ', '>'))) {
                        $li_pos = $p;
                        $p += 4;
                        $in_li = false;
                    }
                    // ul/ol closing tag
                    else if ($tt == '</' . $tag && in_array($html[$p+4], array(' ', '>'))) {
                        break;
                    }
                    // nested ol/ul element out of li
                    else if (!$in_li && $li_pos && ($tt == '<ol>' || $tt == '<ol ' || $tt == '<ul>' || $tt == '<ul ')) {
                        // find closing tag of this ul/ol element
                        $element = substr($tt, 1, 2);
                        $cpos    = $p;
                        do {
                            $tpos = stripos($html, '<' . $element, $cpos+1);
                            $cpos = stripos($html, '</' . $element, $cpos+1);
                        }
                        while ($tpos !== false && $cpos !== false && $cpos > $tpos);

                        // not found, this is invalid HTML, skip it
                        if ($cpos === false) {
                            break;
                        }

                        // get element content
                        $end     = strpos($html, '>', $cpos);
                        $len     = $end - $p + 1;
                        $element = substr($html, $p, $len);

                        // move element to the end of the last li
                        $html    = substr_replace($html, '', $p, $len);
                        $html    = substr_replace($html, $element, $li_pos, 0);

                        $p = $end;
                    }
                    else {
                        $p++;
                    }
                }
            }
        }
    }

    /**
     * Explode css style value
     */
    protected function explode_style($style)
    {
        $pos = 0;

        // first remove comments
        while (($pos = strpos($style, '/*', $pos)) !== false) {
            $end = strpos($style, '*/', $pos+2);

            if ($end === false) {
                $style = substr($style, 0, $pos);
            }
            else {
                $style = substr_replace($style, '', $pos, $end - $pos + 2);
            }
        }

        $style  = trim($style);
        $strlen = strlen($style);
        $result = array();

        // explode value
        for ($p=$i=0; $i < $strlen; $i++) {
            if (($style[$i] == "\"" || $style[$i] == "'") && $style[$i-1] != "\\") {
                if ($q == $style[$i]) {
                    $q = false;
                }
                else if (!$q) {
                    $q = $style[$i];
                }
            }

            if (!$q && $style[$i] == ' ' && !preg_match('/[,\(]/', $style[$i-1])) {
                $result[] = substr($style, $p, $i - $p);
                $p = $i + 1;
            }
        }

        $result[] = (string) substr($style, $p);

        return $result;
    }
}
