<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Helper class to create valid XHTML code                             |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

/**
 * Class for HTML code creation
 *
 * @package    Framework
 * @subpackage View
 */
class html
{
    protected $tagname;
    protected $content;
    protected $attrib  = array();
    protected $allowed = array();

    public static $doctype = 'xhtml';
    public static $lc_tags = true;
    public static $common_attrib = array('id','class','style','title','align','unselectable','tabindex','role');
    public static $containers    = array('iframe','div','span','p','h1','h2','h3','ul','form','textarea','table','thead','tbody','tr','th','td','style','script');
    public static $bool_attrib   = array('checked','multiple','disabled','selected','autofocus','readonly');


    /**
     * Constructor
     *
     * @param array $attrib Hash array with tag attributes
     */
    public function __construct($attrib = array())
    {
        if (is_array($attrib)) {
            $this->attrib = $attrib;
        }
    }

    /**
     * Return the tag code
     *
     * @return string The finally composed HTML tag
     */
    public function show()
    {
        return self::tag($this->tagname, $this->attrib, $this->content, array_merge(self::$common_attrib, $this->allowed));
    }

    /****** STATIC METHODS *******/

    /**
     * Generic method to create a HTML tag
     *
     * @param string $tagname Tag name
     * @param array  $attrib  Tag attributes as key/value pairs
     * @param string $content Optinal Tag content (creates a container tag)
     * @param array  $allowed List with allowed attributes, omit to allow all
     *
     * @return string The XHTML tag
     */
    public static function tag($tagname, $attrib = array(), $content = null, $allowed = null)
    {
        if (is_string($attrib)) {
            $attrib = array('class' => $attrib);
        }

        $inline_tags = array('a','span','img');
        $suffix = $attrib['nl'] || ($content && $attrib['nl'] !== false && !in_array($tagname, $inline_tags)) ? "\n" : '';

        $tagname = self::$lc_tags ? strtolower($tagname) : $tagname;
        if (isset($content) || in_array($tagname, self::$containers)) {
            $suffix = $attrib['noclose'] ? $suffix : '</' . $tagname . '>' . $suffix;
            unset($attrib['noclose'], $attrib['nl']);
            return '<' . $tagname  . self::attrib_string($attrib, $allowed) . '>' . $content . $suffix;
        }
        else {
            return '<' . $tagname  . self::attrib_string($attrib, $allowed) . '>' . $suffix;
        }
    }

    /**
     * Return DOCTYPE tag of specified type
     *
     * @param string $type Document type (html5, xhtml, 'xhtml-trans, xhtml-strict)
     */
    public static function doctype($type)
    {
        $doctypes = array(
            'html5'        => '<!DOCTYPE html>',
            'xhtml'        => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
            'xhtml-trans'  => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
            'xhtml-strict' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
        );

        if ($doctypes[$type]) {
            self::$doctype = preg_replace('/-\w+$/', '', $type);
            return $doctypes[$type];
        }

        return '';
    }

    /**
     * Derrived method for <div> containers
     *
     * @param mixed  $attr Hash array with tag attributes or string with class name
     * @param string $cont Div content
     *
     * @return string HTML code
     * @see html::tag()
     */
    public static function div($attr = null, $cont = null)
    {
        if (is_string($attr)) {
            $attr = array('class' => $attr);
        }

        return self::tag('div', $attr, $cont, array_merge(self::$common_attrib, array('onclick')));
    }

    /**
     * Derrived method for <p> blocks
     *
     * @param mixed  $attr Hash array with tag attributes or string with class name
     * @param string $cont Paragraph content
     *
     * @return string HTML code
     * @see html::tag()
     */
    public static function p($attr = null, $cont = null)
    {
        if (is_string($attr)) {
            $attr = array('class' => $attr);
        }

        return self::tag('p', $attr, $cont, self::$common_attrib);
    }

    /**
     * Derrived method to create <img />
     *
     * @param mixed $attr Hash array with tag attributes or string with image source (src)
     *
     * @return string HTML code
     * @see html::tag()
     */
    public static function img($attr = null)
    {
        if (is_string($attr)) {
            $attr = array('src' => $attr);
        }

        return self::tag('img', $attr + array('alt' => ''), null, array_merge(self::$common_attrib,
            array('src','alt','width','height','border','usemap','onclick','onerror','onload')));
    }

    /**
     * Derrived method for link tags
     *
     * @param mixed  $attr Hash array with tag attributes or string with link location (href)
     * @param string $cont Link content
     *
     * @return string HTML code
     * @see html::tag()
     */
    public static function a($attr, $cont)
    {
        if (is_string($attr)) {
            $attr = array('href' => $attr);
        }

        return self::tag('a', $attr, $cont, array_merge(self::$common_attrib,
            array('href','target','name','rel','onclick','onmouseover','onmouseout','onmousedown','onmouseup')));
    }

    /**
     * Derrived method for inline span tags
     *
     * @param mixed  $attr Hash array with tag attributes or string with class name
     * @param string $cont Tag content
     *
     * @return string HTML code
     * @see html::tag()
     */
    public static function span($attr, $cont)
    {
        if (is_string($attr)) {
            $attr = array('class' => $attr);
        }

        return self::tag('span', $attr, $cont, self::$common_attrib);
    }

    /**
     * Derrived method for form element labels
     *
     * @param mixed  $attr Hash array with tag attributes or string with 'for' attrib
     * @param string $cont Tag content
     *
     * @return string HTML code
     * @see html::tag()
     */
    public static function label($attr, $cont)
    {
        if (is_string($attr)) {
            $attr = array('for' => $attr);
        }

        return self::tag('label', $attr, $cont, array_merge(self::$common_attrib,
            array('for','onkeypress')));
    }

    /**
     * Derrived method to create <iframe></iframe>
     *
     * @param mixed $attr Hash array with tag attributes or string with frame source (src)
     *
     * @return string HTML code
     * @see html::tag()
     */
    public static function iframe($attr = null, $cont = null)
    {
        if (is_string($attr)) {
            $attr = array('src' => $attr);
        }

        return self::tag('iframe', $attr, $cont, array_merge(self::$common_attrib,
            array('src','name','width','height','border','frameborder','onload','allowfullscreen')));
    }

    /**
     * Derrived method to create <script> tags
     *
     * @param mixed  $attr Hash array with tag attributes or string with script source (src)
     * @param string $cont Javascript code to be placed as tag content
     *
     * @return string HTML code
     * @see html::tag()
     */
    public static function script($attr, $cont = null)
    {
        if (is_string($attr)) {
            $attr = array('src' => $attr);
        }
        if ($cont) {
            if (self::$doctype == 'xhtml')
                $cont = "\n/* <![CDATA[ */\n" . $cont . "\n/* ]]> */\n";
            else
                $cont = "\n" . $cont . "\n";
        }

        return self::tag('script', $attr + array('type' => 'text/javascript', 'nl' => true),
            $cont, array_merge(self::$common_attrib, array('src','type','charset')));
    }

    /**
     * Derrived method for line breaks
     *
     * @param array $attrib Associative arry with tag attributes
     *
     * @return string HTML code
     * @see html::tag()
     */
    public static function br($attrib = array())
    {
        return self::tag('br', $attrib);
    }

    /**
     * Create string with attributes
     *
     * @param array $attrib  Associative array with tag attributes
     * @param array $allowed List of allowed attributes
     *
     * @return string Valid attribute string
     */
    public static function attrib_string($attrib = array(), $allowed = null)
    {
        if (empty($attrib)) {
            return '';
        }

        $allowed_f  = array_flip((array)$allowed);
        $attrib_arr = array();

        foreach ($attrib as $key => $value) {
            // skip size if not numeric
            if ($key == 'size' && !is_numeric($value)) {
                continue;
            }

            // ignore "internal" or empty attributes
            if ($key == 'nl' || $value === null) {
                continue;
            }

            // ignore not allowed attributes, except aria-* and data-*
            if (!empty($allowed)) {
                $is_data_attr = @substr_compare($key, 'data-', 0, 5) === 0;
                $is_aria_attr = @substr_compare($key, 'aria-', 0, 5) === 0;
                if (!$is_aria_attr && !$is_data_attr && !isset($allowed_f[$key])) {
                    continue;
                }
            }

            // skip empty eventhandlers
            if (preg_match('/^on[a-z]+/', $key) && !$value) {
                continue;
            }

            // attributes with no value
            if (in_array($key, self::$bool_attrib)) {
                if ($value) {
                    $value = $key;
                    if (self::$doctype == 'xhtml') {
                        $value .= '="' . $value . '"';
                    }

                    $attrib_arr[] = $value;
                }
            }
            else {
                $attrib_arr[] = $key . '="' . self::quote($value) . '"';
            }
        }

        return count($attrib_arr) ? ' '.implode(' ', $attrib_arr) : '';
    }

    /**
     * Convert a HTML attribute string attributes to an associative array (name => value)
     *
     * @param string $str Input string
     *
     * @return array Key-value pairs of parsed attributes
     */
    public static function parse_attrib_string($str)
    {
        $attrib = array();
        $html   = '<html><body><div ' . rtrim($str, '/ ') . ' /></body></html>';

        $document = new DOMDocument('1.0', RCUBE_CHARSET);
        @$document->loadHTML($html);

        if ($node = $document->getElementsByTagName('div')->item(0)) {
            foreach ($node->attributes as $name => $attr) {
                $attrib[strtolower($name)] = $attr->nodeValue;
            }
        }

        return $attrib;
    }

    /**
     * Replacing specials characters in html attribute value
     *
     * @param string $str Input string
     *
     * @return string The quoted string
     */
    public static function quote($str)
    {
        static $flags;

        if (!$flags) {
            $flags = ENT_COMPAT;
            if (defined('ENT_SUBSTITUTE')) {
                $flags |= ENT_SUBSTITUTE;
            }
        }

        return @htmlspecialchars($str, $flags, RCUBE_CHARSET);
    }
}


/**
 * Class to create an HTML input field
 *
 * @package    Framework
 * @subpackage View
 */
class html_inputfield extends html
{
    protected $tagname = 'input';
    protected $type    = 'text';
    protected $allowed = array(
        'type','name','value','size','tabindex','autocapitalize','required',
        'autocomplete','checked','onchange','onclick','disabled','readonly',
        'spellcheck','results','maxlength','src','multiple','accept',
        'placeholder','autofocus','pattern',
    );

    /**
     * Object constructor
     *
     * @param array $attrib Associative array with tag attributes
     */
    public function __construct($attrib = array())
    {
        if (is_array($attrib)) {
            $this->attrib = $attrib;
        }

        if ($attrib['type']) {
            $this->type = $attrib['type'];
        }
    }

    /**
     * Compose input tag
     *
     * @param string $value  Field value
     * @param array  $attrib Additional attributes to override
     *
     * @return string HTML output
     */
    public function show($value = null, $attrib = null)
    {
        // overwrite object attributes
        if (is_array($attrib)) {
            $this->attrib = array_merge($this->attrib, $attrib);
        }

        // set value attribute
        if ($value !== null) {
            $this->attrib['value'] = $value;
        }
        // set type
        $this->attrib['type'] = $this->type;

        return parent::show();
    }
}

/**
 * Class to create an HTML password field
 *
 * @package    Framework
 * @subpackage View
 */
class html_passwordfield extends html_inputfield
{
    protected $type = 'password';
}

/**
 * Class to create an hidden HTML input field
 *
 * @package    Framework
 * @subpackage View
 */
class html_hiddenfield extends html
{
    protected $tagname = 'input';
    protected $type    = 'hidden';
    protected $allowed = array('type','name','value','onchange','disabled','readonly');
    protected $fields  = array();

    /**
     * Constructor
     *
     * @param array $attrib Named tag attributes
     */
    public function __construct($attrib = null)
    {
        if (is_array($attrib)) {
            $this->add($attrib);
        }
    }

    /**
     * Add a hidden field to this instance
     *
     * @param array $attrib Named tag attributes
     */
    public function add($attrib)
    {
        $this->fields[] = $attrib;
    }

    /**
     * Create HTML code for the hidden fields
     *
     * @return string Final HTML code
     */
    public function show()
    {
        $out = '';
        foreach ($this->fields as $attrib) {
            $out .= self::tag($this->tagname, array('type' => $this->type) + $attrib);
        }

        return $out;
    }
}

/**
 * Class to create HTML radio buttons
 *
 * @package    Framework
 * @subpackage View
 */
class html_radiobutton extends html_inputfield
{
    protected $type = 'radio';

    /**
     * Get HTML code for this object
     *
     * @param string $value  Value of the checked field
     * @param array  $attrib Additional attributes to override
     *
     * @return string HTML output
     */
    public function show($value = '', $attrib = null)
    {
        // overwrite object attributes
        if (is_array($attrib)) {
            $this->attrib = array_merge($this->attrib, $attrib);
        }

        // set value attribute
        $this->attrib['checked'] = ((string)$value == (string)$this->attrib['value']);

        return parent::show();
    }
}

/**
 * Class to create HTML checkboxes
 *
 * @package    Framework
 * @subpackage View
 */
class html_checkbox extends html_inputfield
{
    protected $type = 'checkbox';

    /**
     * Get HTML code for this object
     *
     * @param string $value  Value of the checked field
     * @param array  $attrib Additional attributes to override
     *
     * @return string HTML output
     */
    public function show($value = '', $attrib = null)
    {
        // overwrite object attributes
        if (is_array($attrib)) {
            $this->attrib = array_merge($this->attrib, $attrib);
        }

        // set value attribute
        $this->attrib['checked'] = ((string)$value == (string)$this->attrib['value']);

        return parent::show();
    }
}

/**
 * Class to create an HTML textarea
 *
 * @package    Framework
 * @subpackage View
 */
class html_textarea extends html
{
    protected $tagname = 'textarea';
    protected $allowed = array('name','rows','cols','wrap','tabindex',
        'onchange','disabled','readonly','spellcheck');

    /**
     * Get HTML code for this object
     *
     * @param string $value  Textbox value
     * @param array  $attrib Additional attributes to override
     *
     * @return string HTML output
     */
    public function show($value = '', $attrib = null)
    {
        // overwrite object attributes
        if (is_array($attrib)) {
            $this->attrib = array_merge($this->attrib, $attrib);
        }

        // take value attribute as content
        if (empty($value) && !empty($this->attrib['value'])) {
            $value = $this->attrib['value'];
        }

        // make shure we don't print the value attribute
        if (isset($this->attrib['value'])) {
            unset($this->attrib['value']);
        }

        if (!empty($value) && empty($this->attrib['is_escaped'])) {
            $value = self::quote($value);
        }

        return self::tag($this->tagname, $this->attrib, $value,
            array_merge(self::$common_attrib, $this->allowed));
    }
}

/**
 * Builder for HTML drop-down menus
 * Syntax:<pre>
 * // create instance. arguments are used to set attributes of select-tag
 * $select = new html_select(array('name' => 'fieldname'));
 *
 * // add one option
 * $select->add('Switzerland', 'CH');
 *
 * // add multiple options
 * $select->add(array('Switzerland','Germany'), array('CH','DE'));
 *
 * // generate pulldown with selection 'Switzerland'  and return html-code
 * // as second argument the same attributes available to instanciate can be used
 * print $select->show('CH');
 * </pre>
 *
 * @package    Framework
 * @subpackage View
 */
class html_select extends html
{
    protected $tagname = 'select';
    protected $options = array();
    protected $allowed = array('name','size','tabindex','autocomplete',
        'multiple','onchange','disabled','rel');

    /**
     * Add a new option to this drop-down
     *
     * @param mixed $names  Option name or array with option names
     * @param mixed $values Option value or array with option values
     * @param array $attrib Additional attributes for the option entry
     */
    public function add($names, $values = null, $attrib = array())
    {
        if (is_array($names)) {
            foreach ($names as $i => $text) {
                $this->options[] = array('text' => $text, 'value' => $values[$i]) + $attrib;
            }
        }
        else {
            $this->options[] = array('text' => $names, 'value' => $values) + $attrib;
        }
    }

    /**
     * Get HTML code for this object
     *
     * @param string $select Value of the selection option
     * @param array  $attrib Additional attributes to override
     *
     * @return string HTML output
     */
    public function show($select = array(), $attrib = null)
    {
        // overwrite object attributes
        if (is_array($attrib)) {
            $this->attrib = array_merge($this->attrib, $attrib);
        }

        $this->content = "\n";
        $select = (array)$select;
        foreach ($this->options as $option) {
            $attr = array(
                'value' => $option['value'],
                'selected' => (in_array($option['value'], $select, true) ||
                  in_array($option['text'], $select, true)) ? 1 : null);

            $option_content = $option['text'];
            if (empty($this->attrib['is_escaped'])) {
                $option_content = self::quote($option_content);
            }

            $this->content .= self::tag('option', $attr + $option, $option_content, array('value','label','class','style','title','disabled','selected'));
        }

        return parent::show();
    }
}


/**
 * Class to build an HTML table
 *
 * @package    Framework
 * @subpackage View
 */
class html_table extends html
{
    protected $tagname = 'table';
    protected $allowed = array('id','class','style','width','summary',
        'cellpadding','cellspacing','border');

    private $header   = array();
    private $rows     = array();
    private $rowindex = 0;
    private $colindex = 0;

    /**
     * Constructor
     *
     * @param array $attrib Named tag attributes
     */
    public function __construct($attrib = array())
    {
        $default_attrib = self::$doctype == 'xhtml' ? array('summary' => '', 'border' => '0') : array();
        $this->attrib   = array_merge($attrib, $default_attrib);

        if (!empty($attrib['tagname']) && $attrib['tagname'] != 'table') {
          $this->tagname = $attrib['tagname'];
          $this->allowed = self::$common_attrib;
        }
    }

    /**
     * Add a table cell
     *
     * @param array  $attr Cell attributes
     * @param string $cont Cell content
     */
    public function add($attr, $cont)
    {
        if (is_string($attr)) {
            $attr = array('class' => $attr);
        }

        $cell = new stdClass;
        $cell->attrib  = $attr;
        $cell->content = $cont;

        $this->rows[$this->rowindex]->cells[$this->colindex] = $cell;
        $this->colindex += max(1, intval($attr['colspan']));

        if ($this->attrib['cols'] && $this->colindex >= $this->attrib['cols']) {
            $this->add_row();
        }
    }

    /**
     * Add a table header cell
     *
     * @param array  $attr Cell attributes
     * @param string $cont Cell content
     */
    public function add_header($attr, $cont)
    {
        if (is_string($attr)) {
            $attr = array('class' => $attr);
        }

        $cell = new stdClass;
        $cell->attrib   = $attr;
        $cell->content  = $cont;
        $this->header[] = $cell;
    }

    /**
     * Remove a column from a table
     * Useful for plugins making alterations
     *
     * @param string $class Class name
     */
    public function remove_column($class)
    {
        // Remove the header
        foreach ($this->header as $index => $header){
            if ($header->attrib['class'] == $class){
                unset($this->header[$index]);
                break;
            }
        }

        // Remove cells from rows
        foreach ($this->rows as $i => $row){
            foreach ($row->cells as $j => $cell){
                if ($cell->attrib['class'] == $class){
                    unset($this->rows[$i]->cells[$j]);
                    break;
                }
            }
        }
    }

    /**
     * Jump to next row
     *
     * @param array $attr Row attributes
     */
    public function add_row($attr = array())
    {
        $this->rowindex++;
        $this->colindex = 0;
        $this->rows[$this->rowindex] = new stdClass;
        $this->rows[$this->rowindex]->attrib = $attr;
        $this->rows[$this->rowindex]->cells  = array();
    }

    /**
     * Set row attributes
     *
     * @param array $attr  Row attributes
     * @param int   $index Optional row index (default current row index)
     */
    public function set_row_attribs($attr = array(), $index = null)
    {
        if (is_string($attr)) {
            $attr = array('class' => $attr);
        }

        if ($index === null) {
            $index = $this->rowindex;
        }

        // make sure row object exists (#1489094)
        if (!$this->rows[$index]) {
            $this->rows[$index] = new stdClass;
        }

        $this->rows[$index]->attrib = $attr;
    }

    /**
     * Get row attributes
     *
     * @param int $index Row index
     *
     * @return array Row attributes
     */
    public function get_row_attribs($index = null)
    {
        if ($index === null) {
            $index = $this->rowindex;
        }

        return $this->rows[$index] ? $this->rows[$index]->attrib : null;
    }

    /**
     * Build HTML output of the table data
     *
     * @param array $attrib Table attributes
     *
     * @return string The final table HTML code
     */
    public function show($attrib = null)
    {
        if (is_array($attrib)) {
            $this->attrib = array_merge($this->attrib, $attrib);
        }

        $thead = $tbody = "";

        // include <thead>
        if (!empty($this->header)) {
            $rowcontent = '';
            foreach ($this->header as $c => $col) {
                $rowcontent .= self::tag($this->_head_tagname(), $col->attrib, $col->content);
            }
            $thead = $this->tagname == 'table' ? self::tag('thead', null, self::tag('tr', null, $rowcontent, parent::$common_attrib)) :
                self::tag($this->_row_tagname(), array('class' => 'thead'), $rowcontent, parent::$common_attrib);
        }

        foreach ($this->rows as $r => $row) {
            $rowcontent = '';
            foreach ($row->cells as $c => $col) {
                $rowcontent .= self::tag($this->_col_tagname(), $col->attrib, $col->content);
            }

            if ($r < $this->rowindex || count($row->cells)) {
                $tbody .= self::tag($this->_row_tagname(), $row->attrib, $rowcontent, parent::$common_attrib);
            }
        }

        if ($this->attrib['rowsonly']) {
            return $tbody;
        }

        // add <tbody>
        $this->content = $thead . ($this->tagname == 'table' ? self::tag('tbody', null, $tbody) : $tbody);

        unset($this->attrib['cols'], $this->attrib['rowsonly']);
        return parent::show();
    }

    /**
     * Count number of rows
     *
     * @return The number of rows
     */
    public function size()
    {
        return count($this->rows);
    }

    /**
     * Remove table body (all rows)
     */
    public function remove_body()
    {
        $this->rows     = array();
        $this->rowindex = 0;
    }

    /**
     * Getter for the corresponding tag name for table row elements
     */
    private function _row_tagname()
    {
        static $row_tagnames = array('table' => 'tr', 'ul' => 'li', '*' => 'div');
        return $row_tagnames[$this->tagname] ?: $row_tagnames['*'];
    }

    /**
     * Getter for the corresponding tag name for table row elements
     */
    private function _head_tagname()
    {
        static $head_tagnames = array('table' => 'th', '*' => 'span');
        return $head_tagnames[$this->tagname] ?: $head_tagnames['*'];
    }

    /**
     * Getter for the corresponding tag name for table cell elements
     */
    private function _col_tagname()
    {
        static $col_tagnames = array('table' => 'td', '*' => 'span');
        return $col_tagnames[$this->tagname] ?: $col_tagnames['*'];
    }
}
