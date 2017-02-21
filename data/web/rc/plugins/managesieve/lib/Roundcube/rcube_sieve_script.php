<?php

/**
 *  Class for operations on Sieve scripts
 *
 * Copyright (C) 2008-2011, The Roundcube Dev Team
 * Copyright (C) 2011, Kolab Systems AG
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see http://www.gnu.org/licenses/.
 */

class rcube_sieve_script
{
    public $content = array();      // script rules array

    private $vars      = array();   // "global" variables
    private $prefix    = '';        // script header (comments)
    private $supported = array(     // supported Sieve extensions:
        'body',                     // RFC5173
        'copy',                     // RFC3894
        'date',                     // RFC5260
        'duplicate',                // RFC7352
        'enotify',                  // RFC5435
        'envelope',                 // RFC5228
        'ereject',                  // RFC5429
        'fileinto',                 // RFC5228
        'imapflags',                // draft-melnikov-sieve-imapflags-06
        'imap4flags',               // RFC5232
        'include',                  // RFC6609
        'index',                    // RFC5260
        'notify',                   // RFC5435
        'regex',                    // draft-ietf-sieve-regex-01
        'reject',                   // RFC5429
        'relational',               // RFC3431
        'subaddress',               // RFC5233
        'vacation',                 // RFC5230
        'vacation-seconds',         // RFC6131
        'variables',                // RFC5229
        // @TODO: spamtest+virustest, mailbox
    );

    /**
     * Object constructor
     *
     * @param  string  Script's text content
     * @param  array   List of capabilities supported by server
     */
    public function __construct($script, $capabilities=array())
    {
        $capabilities = array_map('strtolower', (array) $capabilities);

        // disable features by server capabilities
        if (!empty($capabilities)) {
            foreach ($this->supported as $idx => $ext) {
                if (!in_array($ext, $capabilities)) {
                    unset($this->supported[$idx]);
                }
            }
        }

        // Parse text content of the script
        $this->_parse_text($script);
    }

    /**
     * Adds rule to the script (at the end)
     *
     * @param string Rule name
     * @param array  Rule content (as array)
     *
     * @return int The index of the new rule
     */
    public function add_rule($content)
    {
        // TODO: check this->supported
        array_push($this->content, $content);
        return sizeof($this->content)-1;
    }

    public function delete_rule($index)
    {
        if(isset($this->content[$index])) {
            unset($this->content[$index]);
            return true;
        }
        return false;
    }

    public function size()
    {
        return sizeof($this->content);
    }

    public function update_rule($index, $content)
    {
        // TODO: check this->supported
        if ($this->content[$index]) {
            $this->content[$index] = $content;
            return $index;
        }
        return false;
    }

    /**
     * Sets "global" variable
     *
     * @param string $name  Variable name
     * @param string $value Variable value
     * @param array  $mods  Variable modifiers
     */
    public function set_var($name, $value, $mods = array())
    {
        // Check if variable exists
        for ($i=0, $len=count($this->vars); $i<$len; $i++) {
            if ($this->vars[$i]['name'] == $name) {
                break;
            }
        }

        $var = array_merge($mods, array('name' => $name, 'value' => $value));
        $this->vars[$i] = $var;
    }

    /**
     * Unsets "global" variable
     *
     * @param string $name  Variable name
     */
    public function unset_var($name)
    {
        // Check if variable exists
        foreach ($this->vars as $idx => $var) {
            if ($var['name'] == $name) {
                unset($this->vars[$idx]);
                break;
            }
        }
    }

    /**
     * Gets the value of  "global" variable
     *
     * @param string $name  Variable name
     *
     * @return string Variable value
     */
    public function get_var($name)
    {
        // Check if variable exists
        for ($i=0, $len=count($this->vars); $i<$len; $i++) {
            if ($this->vars[$i]['name'] == $name) {
                return $this->vars[$i]['name'];
            }
        }
    }

    /**
     * Sets script header content
     *
     * @param string $text  Header content
     */
    public function set_prefix($text)
    {
        $this->prefix = $text;
    }

    /**
     * Returns script as text
     */
    public function as_text()
    {
        $output = '';
        $exts   = array();
        $idx    = 0;

        if (!empty($this->vars)) {
            if (in_array('variables', (array)$this->supported)) {
                $has_vars = true;
                array_push($exts, 'variables');
            }
            foreach ($this->vars as $var) {
                if (empty($has_vars)) {
                    // 'variables' extension not supported, put vars in comments
                    $output .= sprintf("# %s %s\n", $var['name'], $var['value']);
                }
                else {
                    $output .= 'set ';
                    foreach (array_diff(array_keys($var), array('name', 'value')) as $opt) {
                        $output .= ":$opt ";
                    }
                    $output .= self::escape_string($var['name']) . ' ' . self::escape_string($var['value']) . ";\n";
                }
            }
        }

        $imapflags = in_array('imap4flags', $this->supported) ? 'imap4flags' : 'imapflags';
        $notify    = in_array('enotify', $this->supported) ? 'enotify' : 'notify';

        // rules
        foreach ($this->content as $rule) {
            $script    = '';
            $tests     = array();
            $i         = 0;

            // header
            if (!empty($rule['name']) && strlen($rule['name'])) {
                $script .= '# rule:[' . $rule['name'] . "]\n";
            }

            // constraints expressions
            if (!empty($rule['tests'])) {
                foreach ($rule['tests'] as $test) {
                    $tests[$i] = '';
                    switch ($test['test']) {
                    case 'size':
                        $tests[$i] .= ($test['not'] ? 'not ' : '');
                        $tests[$i] .= 'size :' . ($test['type']=='under' ? 'under ' : 'over ') . $test['arg'];
                        break;

                    case 'true':
                        $tests[$i] .= ($test['not'] ? 'false' : 'true');
                        break;

                    case 'exists':
                        $tests[$i] .= ($test['not'] ? 'not ' : '');
                        $tests[$i] .= 'exists ' . self::escape_string($test['arg']);
                        break;

                    case 'header':
                    case 'string':
                        if ($test['test'] == 'string') {
                            array_push($exts, 'variables');
                        }

                        $tests[$i] .= ($test['not'] ? 'not ' : '');
                        $tests[$i] .= $test['test'];

                        $this->add_index($test, $tests[$i], $exts);
                        $this->add_operator($test, $tests[$i], $exts);

                        $tests[$i] .= ' ' . self::escape_string($test['arg1']);
                        $tests[$i] .= ' ' . self::escape_string($test['arg2']);
                        break;

                    case 'address':
                    case 'envelope':
                        if ($test['test'] == 'envelope') {
                            array_push($exts, 'envelope');
                        }

                        $tests[$i] .= ($test['not'] ? 'not ' : '');
                        $tests[$i] .= $test['test'];

                        if ($test['test'] != 'envelope') {
                            $this->add_index($test, $tests[$i], $exts);
                        }

                        // :all address-part is optional, skip it
                        if (!empty($test['part']) && $test['part'] != 'all') {
                            $tests[$i] .= ' :' . $test['part'];
                            if ($test['part'] == 'user' || $test['part'] == 'detail') {
                                array_push($exts, 'subaddress');
                            }
                        }

                        $this->add_operator($test, $tests[$i], $exts);

                        $tests[$i] .= ' ' . self::escape_string($test['arg1']);
                        $tests[$i] .= ' ' . self::escape_string($test['arg2']);
                        break;

                    case 'body':
                        array_push($exts, 'body');

                        $tests[$i] .= ($test['not'] ? 'not ' : '') . 'body';

                        if (!empty($test['part'])) {
                            $tests[$i] .= ' :' . $test['part'];

                            if (!empty($test['content']) && $test['part'] == 'content') {
                                $tests[$i] .= ' ' . self::escape_string($test['content']);
                            }
                        }

                        $this->add_operator($test, $tests[$i], $exts);

                        $tests[$i] .= ' ' . self::escape_string($test['arg']);
                        break;

                    case 'date':
                    case 'currentdate':
                        array_push($exts, 'date');

                        $tests[$i] .= ($test['not'] ? 'not ' : '') . $test['test'];

                        $this->add_index($test, $tests[$i], $exts);

                        if (!empty($test['originalzone']) && $test['test'] == 'date') {
                            $tests[$i] .= ' :originalzone';
                        }
                        else if (!empty($test['zone'])) {
                            $tests[$i] .= ' :zone ' . self::escape_string($test['zone']);
                        }

                        $this->add_operator($test, $tests[$i], $exts);

                        if ($test['test'] == 'date') {
                            $tests[$i] .= ' ' . self::escape_string($test['header']);
                        }

                        $tests[$i] .= ' ' . self::escape_string($test['part']);
                        $tests[$i] .= ' ' . self::escape_string($test['arg']);

                        break;

                    case 'duplicate':
                        array_push($exts, 'duplicate');

                        $tests[$i] .= ($test['not'] ? 'not ' : '') . $test['test'];

                        $tokens = array('handle', 'uniqueid', 'header');
                        foreach ($tokens as $token)
                            if ($test[$token] !== null && $test[$token] !== '') {
                                $tests[$i] .= " :$token " . self::escape_string($test[$token]);
                            }

                        if (!empty($test['seconds'])) {
                            $tests[$i] .= ' :seconds ' . intval($test['seconds']);
                        }

                        if (!empty($test['last'])) {
                            $tests[$i] .= ' :last';
                        }

                        break;
                    }

                    $i++;
                }
            }

            // disabled rule: if false #....
            if (!empty($tests)) {
                $script .= 'if ' . ($rule['disabled'] ? 'false # ' : '');

                if (count($tests) > 1) {
                    $tests_str = implode(', ', $tests);
                }
                else {
                    $tests_str = $tests[0];
                }

                if ($rule['join'] || count($tests) > 1) {
                    $script .= sprintf('%s (%s)', $rule['join'] ? 'allof' : 'anyof', $tests_str);
                }
                else {
                    $script .= $tests_str;
                }
                $script .= "\n{\n";
            }

            // action(s)
            if (!empty($rule['actions'])) {
                foreach ($rule['actions'] as $action) {
                    $action_script = '';

                    switch ($action['type']) {

                    case 'fileinto':
                        array_push($exts, 'fileinto');
                        $action_script .= 'fileinto ';
                        if ($action['copy']) {
                            $action_script .= ':copy ';
                            array_push($exts, 'copy');
                        }
                        $action_script .= self::escape_string($action['target']);
                        break;

                    case 'redirect':
                        $action_script .= 'redirect ';
                        if ($action['copy']) {
                            $action_script .= ':copy ';
                            array_push($exts, 'copy');
                        }
                        $action_script .= self::escape_string($action['target']);
                        break;

                    case 'reject':
                    case 'ereject':
                        array_push($exts, $action['type']);
                        $action_script .= $action['type'].' '
                            . self::escape_string($action['target']);
                        break;

                    case 'addflag':
                    case 'setflag':
                    case 'removeflag':
                        array_push($exts, $imapflags);
                        $action_script .= $action['type'].' '
                            . self::escape_string($action['target']);
                        break;

                    case 'keep':
                    case 'discard':
                    case 'stop':
                        $action_script .= $action['type'];
                        break;

                    case 'include':
                        array_push($exts, 'include');
                        $action_script .= 'include ';
                        foreach (array_diff(array_keys($action), array('target', 'type')) as $opt) {
                            $action_script .= ":$opt ";
                        }
                        $action_script .= self::escape_string($action['target']);
                        break;

                    case 'set':
                        array_push($exts, 'variables');
                        $action_script .= 'set ';
                        foreach (array_diff(array_keys($action), array('name', 'value', 'type')) as $opt) {
                            $action_script .= ":$opt ";
                        }
                        $action_script .= self::escape_string($action['name']) . ' ' . self::escape_string($action['value']);
                        break;

                    case 'notify':
                        array_push($exts, $notify);
                        $action_script .= 'notify';

                        $method = $action['method'];
                        unset($action['method']);
                        $action['options'] = (array) $action['options'];

                        // Here we support draft-martin-sieve-notify-01 used by Cyrus
                        if ($notify == 'notify') {
                            switch ($action['importance']) {
                                case 1: $action_script .= " :high"; break;
                                //case 2: $action_script .= " :normal"; break;
                                case 3: $action_script .= " :low"; break;
                            }

                            // Old-draft way: :method "mailto" :options "email@address"
                            if (!empty($method)) {
                                $parts = explode(':', $method, 2);
                                $action['method'] = $parts[0];
                                array_unshift($action['options'], $parts[1]);
                            }

                            unset($action['importance']);
                            unset($action['from']);
                            unset($method);
                        }

                        foreach (array('id', 'importance', 'method', 'options', 'from', 'message') as $n_tag) {
                            if (!empty($action[$n_tag])) {
                                $action_script .= " :$n_tag " . self::escape_string($action[$n_tag]);
                            }
                        }

                        if (!empty($method)) {
                            $action_script .= ' ' . self::escape_string($method);
                        }

                        break;

                    case 'vacation':
                        array_push($exts, 'vacation');
                        $action_script .= 'vacation';
                        if (isset($action['seconds'])) {
                            array_push($exts, 'vacation-seconds');
                            $action_script .= " :seconds " . intval($action['seconds']);
                        }
                        else if (!empty($action['days'])) {
                            $action_script .= " :days " . intval($action['days']);
                        }
                        if (!empty($action['addresses']))
                            $action_script .= " :addresses " . self::escape_string($action['addresses']);
                        if (!empty($action['subject']))
                            $action_script .= " :subject " . self::escape_string($action['subject']);
                        if (!empty($action['handle']))
                            $action_script .= " :handle " . self::escape_string($action['handle']);
                        if (!empty($action['from']))
                            $action_script .= " :from " . self::escape_string($action['from']);
                        if (!empty($action['mime']))
                            $action_script .= " :mime";
                        $action_script .= " " . self::escape_string($action['reason']);
                        break;
                    }

                    if ($action_script) {
                        $script .= !empty($tests) ? "\t" : '';
                        $script .= $action_script . ";\n";
                    }
                }
            }

            if ($script) {
                $output .= $script . (!empty($tests) ? "}\n" : '');
                $idx++;
            }
        }

        // requires
        if (!empty($exts)) {
            $exts = array_unique($exts);

            if (in_array('vacation-seconds', $exts) && ($key = array_search('vacation', $exts)) !== false) {
                unset($exts[$key]);
            }

            sort($exts); // for convenience use always the same order

            $output = 'require ["' . implode('","', $exts) . "\"];\n" . $output;
        }

        if (!empty($this->prefix)) {
            $output = $this->prefix . "\n\n" . $output;
        }

        return $output;
    }

    /**
     * Returns script object
     *
     */
    public function as_array()
    {
        return $this->content;
    }

    /**
     * Returns array of supported extensions
     *
     */
    public function get_extensions()
    {
        return array_values($this->supported);
    }

    /**
     * Converts text script to rules array
     *
     * @param string Text script
     */
    private function _parse_text($script)
    {
        $prefix   = '';
        $options  = array();
        $position = 0;
        $length   = strlen($script);

        while ($position < $length) {
            // skip whitespace chars
            $position = self::ltrim_position($script, $position);
            $rulename = '';

            // Comments
            while ($script[$position] === '#') {
                $endl = strpos($script, "\n", $position) ?: $length;
                $line = substr($script, $position, $endl - $position);

                // Roundcube format
                if (preg_match('/^# rule:\[(.*)\]/', $line, $matches)) {
                    $rulename = $matches[1];
                }
                // KEP:14 variables
                else if (preg_match('/^# (EDITOR|EDITOR_VERSION) (.+)$/', $line, $matches)) {
                    $this->set_var($matches[1], $matches[2]);
                }
                // Horde-Ingo format
                else if (!empty($options['format']) && $options['format'] == 'INGO'
                    && preg_match('/^# (.*)/', $line, $matches)
                ) {
                    $rulename = $matches[1];
                }
                else if (empty($options['prefix'])) {
                    $prefix .= $line . "\n";
                }

                $position = $endl + 1;
            }

            // handle script header
            if (empty($options['prefix'])) {
                $options['prefix'] = true;
                if ($prefix && strpos($prefix, 'horde.org/ingo')) {
                    $options['format'] = 'INGO';
                }
            }

            // Control structures/blocks
            if (preg_match('/^(if|else|elsif)/i', substr($script, $position, 5))) {
                $rule = $this->_tokenize_rule($script, $position);
                if (strlen($rulename) && !empty($rule)) {
                    $rule['name'] = $rulename;
                }
            }
            // Simple commands
            else {
                $rule = $this->_parse_actions($script, $position, ';');
                if (!empty($rule[0]) && is_array($rule)) {
                    // set "global" variables
                    if ($rule[0]['type'] == 'set') {
                        unset($rule[0]['type']);
                        $this->vars[] = $rule[0];
                        unset($rule);
                    }
                    else {
                        $rule = array('actions' => $rule);
                    }
                }
            }

            if (!empty($rule)) {
                $this->content[] = $rule;
            }
        }

        if (!empty($prefix)) {
            $this->prefix = trim($prefix);
        }
    }

    /**
     * Convert text script fragment to rule object
     *
     * @param string $content   The whole script content
     * @param int    &$position Start position in the script
     *
     * @return array Rule data
     */
    private function _tokenize_rule($content, &$position)
    {
        $cond = strtolower(self::tokenize($content, 1, $position));
        if ($cond != 'if' && $cond != 'elsif' && $cond != 'else') {
            return null;
        }

        $disabled = false;
        $join     = false;
        $join_not = false;
        $length   = strlen($content);

        // disabled rule (false + comment): if false # .....
        if (preg_match('/^\s*false\s+#\s*/i', substr($content, $position, 20), $m)) {
            $position += strlen($m[0]);
            $disabled = true;
        }

        while ($position < $length) {
            $tokens    = self::tokenize($content, true, $position);
            $separator = array_pop($tokens);

            if (!empty($tokens)) {
                $token = array_shift($tokens);
            }
            else {
                $token = $separator;
            }

            $token = strtolower($token);

            if ($token == 'not') {
                $not = true;
                $token = strtolower(array_shift($tokens));
            }
            else {
                $not = false;
            }

            // we support "not allof" as a negation of allof sub-tests
            if ($join_not) {
                $not = !$not;
            }

            switch ($token) {
            case 'allof':
                $join     = true;
                $join_not = $not;
                break;

            case 'anyof':
                break;

            case 'size':
                $test = array('test' => 'size', 'not' => $not);

                $test['arg'] = array_pop($tokens);

                for ($i=0, $len=count($tokens); $i<$len; $i++) {
                    if (!is_array($tokens[$i])
                        && preg_match('/^:(under|over)$/i', $tokens[$i])
                    ) {
                        $test['type'] = strtolower(substr($tokens[$i], 1));
                    }
                }

                $tests[] = $test;
                break;

            case 'header':
            case 'string':
            case 'address':
            case 'envelope':
                $test = array('test' => $token, 'not' => $not);

                $test['arg2'] = array_pop($tokens);
                $test['arg1'] = array_pop($tokens);

                $test += $this->test_tokens($tokens);

                if ($token != 'header' && $token != 'string' && !empty($tokens)) {
                    for ($i=0, $len=count($tokens); $i<$len; $i++) {
                        if (!is_array($tokens[$i]) && preg_match('/^:(localpart|domain|all|user|detail)$/i', $tokens[$i])) {
                            $test['part'] = strtolower(substr($tokens[$i], 1));
                        }
                    }
                }

                $tests[] = $test;
                break;

            case 'body':
                $test = array('test' => 'body', 'not' => $not);

                $test['arg'] = array_pop($tokens);

                $test += $this->test_tokens($tokens);

                for ($i=0, $len=count($tokens); $i<$len; $i++) {
                    if (!is_array($tokens[$i]) && preg_match('/^:(raw|content|text)$/i', $tokens[$i])) {
                        $test['part'] = strtolower(substr($tokens[$i], 1));

                        if ($test['part'] == 'content') {
                            $test['content'] = $tokens[++$i];
                        }
                    }
                }

                $tests[] = $test;
                break;

            case 'date':
            case 'currentdate':
                $test = array('test' => $token, 'not' => $not);

                $test['arg']  = array_pop($tokens);
                $test['part'] = array_pop($tokens);

                if ($token == 'date') {
                    $test['header']  = array_pop($tokens);
                }

                $test += $this->test_tokens($tokens);

                for ($i=0, $len=count($tokens); $i<$len; $i++) {
                    if (!is_array($tokens[$i]) && preg_match('/^:zone$/i', $tokens[$i])) {
                        $test['zone'] = $tokens[++$i];
                    }
                    else if (!is_array($tokens[$i]) && preg_match('/^:originalzone$/i', $tokens[$i])) {
                        $test['originalzone'] = true;
                    }
                }

                $tests[] = $test;
                break;

            case 'duplicate':
                $test = array('test' => $token, 'not' => $not);

                for ($i=0, $len=count($tokens); $i<$len; $i++) {
                    if (!is_array($tokens[$i])) {
                        if (preg_match('/^:(handle|header|uniqueid|seconds)$/i', $tokens[$i], $m)) {
                            $test[strtolower($m[1])] = $tokens[++$i];
                        }
                        else if (preg_match('/^:last$/i', $tokens[$i])) {
                            $test['last'] = true;
                        }
                    }
                }

                $tests[] = $test;
                break;

            case 'exists':
                $tests[] = array('test' => 'exists', 'not' => $not,
                    'arg'  => array_pop($tokens));
                break;

            case 'true':
                $tests[] = array('test' => 'true', 'not' => $not);
                break;

            case 'false':
                $tests[] = array('test' => 'true', 'not' => !$not);
                break;
            }

            // goto actions...
            if ($separator == '{') {
                break;
            }
        }

        // ...and actions block
        $actions = $this->_parse_actions($content, $position);

        if ($tests && $actions) {
            $result = array(
                'type'     => $cond,
                'tests'    => $tests,
                'actions'  => $actions,
                'join'     => $join,
                'disabled' => $disabled,
            );
        }

        return $result;
    }

    /**
     * Parse body of actions section
     *
     * @param string $content   The whole script content
     * @param int    &$position Start position in the script
     * @param string $end       End of text separator
     *
     * @return array Array of parsed action type/target pairs
     */
    private function _parse_actions($content, &$position, $end = '}')
    {
        $result = null;
        $length = strlen($content);

        while ($position < $length) {
            $tokens    = self::tokenize($content, true, $position);
            $separator = array_pop($tokens);
            $token     = !empty($tokens) ? array_shift($tokens) : $separator;

            switch ($token) {
            case 'if':
                // nested 'if' conditions, ignore the whole rule (#5540)
                $this->_parse_actions($content, $position);
                continue 2;

            case 'discard':
            case 'keep':
            case 'stop':
                $result[] = array('type' => $token);
                break;

            case 'fileinto':
            case 'redirect':
                $action  = array('type' => $token, 'target' => array_pop($tokens));
                $args    = array('copy');
                $action += $this->action_arguments($tokens, $args);

                $result[] = $action;
                break;

            case 'vacation':
                $action  = array('type' => 'vacation', 'reason' => array_pop($tokens));
                $args    = array('mime');
                $vargs   = array('seconds', 'days', 'addresses', 'subject', 'handle', 'from');
                $action += $this->action_arguments($tokens, $args, $vargs);

                $result[] = $action;
                break;

            case 'reject':
            case 'ereject':
            case 'setflag':
            case 'addflag':
            case 'removeflag':
                $result[] = array('type' => $token, 'target' => array_pop($tokens));
                break;

            case 'include':
                $action  = array('type' => 'include', 'target' => array_pop($tokens));
                $args    = array('once', 'optional', 'global', 'personal');
                $action += $this->action_arguments($tokens, $args);

                $result[] = $action;
                break;

            case 'set':
                $action  = array('type' => 'set', 'value' => array_pop($tokens), 'name' => array_pop($tokens));
                $args    = array('lower', 'upper', 'lowerfirst', 'upperfirst', 'quotewildcard', 'length');
                $action += $this->action_arguments($tokens, $args);

                $result[] = $action;
                break;

            case 'require':
                // skip, will be build according to used commands
                // $result[] = array('type' => 'require', 'target' => array_pop($tokens));
                break;

            case 'notify':
                $action     = array('type' => 'notify');
                $priorities = array('high' => 1, 'normal' => 2, 'low' => 3);
                $vargs      = array('from', 'id', 'importance', 'options', 'message', 'method');
                $args       = array_keys($priorities);
                $action    += $this->action_arguments($tokens, $args, $vargs);

                // Here we'll convert draft-martin-sieve-notify-01 into RFC 5435
                if (!isset($action['importance'])) {
                    foreach ($priorities as $key => $val) {
                        if (isset($action[$key])) {
                            $action['importance'] = $val;
                            unset($action[$key]);
                        }
                    }
                }

                $action['options'] = (array) $action['options'];

                // Old-draft way: :method "mailto" :options "email@address"
                if (!empty($action['method']) && !empty($action['options'])) {
                    $action['method'] .= ':' . array_shift($action['options']);
                }
                // unnamed parameter is a :method in enotify extension
                else if (!isset($action['method'])) {
                    $action['method'] = array_pop($tokens);
                }

                $result[] = $action;
                break;
            }

            if ($separator == $end) {
                break;
            }
        }

        return $result;
    }

    /**
     * Add comparator to the test
     */
    private function add_comparator($test, &$out, &$exts)
    {
        if (empty($test['comparator'])) {
            return;
        }

        if ($test['comparator'] == 'i;ascii-numeric') {
            array_push($exts, 'relational');
            array_push($exts, 'comparator-i;ascii-numeric');
        }
        else if (!in_array($test['comparator'], array('i;octet', 'i;ascii-casemap'))) {
            array_push($exts, 'comparator-' . $test['comparator']);
        }

        // skip default comparator
        if ($test['comparator'] != 'i;ascii-casemap') {
            $out .= ' :comparator ' . self::escape_string($test['comparator']);
        }
    }

    /**
     * Add index argument to the test
     */
    private function add_index($test, &$out, &$exts)
    {
        if (!empty($test['index'])) {
            array_push($exts, 'index');
            $out .= ' :index ' . intval($test['index']) . ($test['last'] ? ' :last' : '');
        }
    }

    /**
     * Add operators to the test
     */
    private function add_operator($test, &$out, &$exts)
    {
        if (empty($test['type'])) {
            return;
        }

        // relational operator
        if (preg_match('/^(value|count)-([gteqnl]{2})/', $test['type'], $m)) {
            array_push($exts, 'relational');

            $out .= ' :' . $m[1] . ' "' . $m[2] . '"';
        }
        else {
            if ($test['type'] == 'regex') {
                array_push($exts, 'regex');
            }

            $out .= ' :' . $test['type'];
        }

        $this->add_comparator($test, $out, $exts);
    }

    /**
     * Extract test tokens
     */
    private function test_tokens(&$tokens)
    {
        $test   = array();
        $result = array();

        for ($i=0, $len=count($tokens); $i<$len; $i++) {
            if (!is_array($tokens[$i]) && preg_match('/^:comparator$/i', $tokens[$i])) {
                $test['comparator'] = $tokens[++$i];
            }
            else if (!is_array($tokens[$i]) && preg_match('/^:(count|value)$/i', $tokens[$i])) {
                $test['type'] = strtolower(substr($tokens[$i], 1)) . '-' . $tokens[++$i];
            }
            else if (!is_array($tokens[$i]) && preg_match('/^:(is|contains|matches|regex)$/i', $tokens[$i])) {
                $test['type'] = strtolower(substr($tokens[$i], 1));
            }
            else if (!is_array($tokens[$i]) && preg_match('/^:index$/i', $tokens[$i])) {
                $test['index'] = intval($tokens[++$i]);
                if ($tokens[$i+1] && preg_match('/^:last$/i', $tokens[$i+1])) {
                    $test['last'] = true;
                    $i++;
                }
           }
           else {
               $result[] = $tokens[$i];
           }
        }

        $tokens = $result;

        return $test;
    }

    /**
     * Extract action arguments
     */
    private function action_arguments(&$tokens, $bool_args, $val_args = array())
    {
        $action = array();
        $result = array();

        for ($i=0, $len=count($tokens); $i<$len; $i++) {
            $tok = $tokens[$i];
            if (!is_array($tok) && $tok[0] == ':') {
                $tok = strtolower(substr($tok, 1));
                if (in_array($tok, $bool_args)) {
                    $action[$tok] = true;
                }
                else if (in_array($tok, $val_args)) {
                    $action[$tok] = $tokens[++$i];
                }
                else {
                    $result[] = $tok;
                }
            }
            else {
                $result[] = $tok;
            }
        }

        $tokens = $result;

        return $action;
    }

    /**
     * Escape special chars into quoted string value or multi-line string
     * or list of strings
     *
     * @param string $str Text or array (list) of strings
     *
     * @return string Result text
     */
    static function escape_string($str)
    {
        if (is_array($str) && count($str) > 1) {
            foreach($str as $idx => $val)
                $str[$idx] = self::escape_string($val);

            return '[' . implode(',', $str) . ']';
        }
        else if (is_array($str)) {
            $str = array_pop($str);
        }

        // multi-line string
        if (preg_match('/[\r\n\0]/', $str) || strlen($str) > 1024) {
            return sprintf("text:\n%s\n.\n", self::escape_multiline_string($str));
        }
        // quoted-string
        else {
            return '"' . addcslashes($str, '\\"') . '"';
        }
    }

    /**
     * Escape special chars in multi-line string value
     *
     * @param string $str Text
     *
     * @return string Text
     */
    static function escape_multiline_string($str)
    {
        $str = preg_split('/(\r?\n)/', $str, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($str as $idx => $line) {
            // dot-stuffing
            if (isset($line[0]) && $line[0] == '.') {
                $str[$idx] = '.' . $line;
            }
        }

        return implode($str);
    }

    /**
     * Splits script into string tokens
     *
     * @param string $str       The script
     * @param mixed  $num       Number of tokens to return, 0 for all
     *                          or True for all tokens until separator is found.
     *                          Separator will be returned as last token.
     * @param int    &$position Parsing start position
     *
     * @return mixed Tokens array or string if $num=1
     */
    static function tokenize($str, $num = 0, &$position = 0)
    {
        $result = array();
        $length = strlen($str);

        // remove spaces from the beginning of the string
        while ($position < $length && (!$num || $num === true || count($result) < $num)) {
            // skip whitespace chars
            $position = self::ltrim_position($str, $position);

            switch ($str[$position]) {

            // Quoted string
            case '"':
                for ($pos = $position + 1; $pos < $length; $pos++) {
                    if ($str[$pos] == '"') {
                        break;
                    }
                    if ($str[$pos] == "\\") {
                        if ($str[$pos + 1] == '"' || $str[$pos + 1] == "\\") {
                            $pos++;
                        }
                    }
                }
                if ($str[$pos] != '"') {
                    // error
                }

                // we need to strip slashes for a quoted string
                $result[] = stripslashes(substr($str, $position + 1, $pos - $position - 1));
                $position = $pos + 1;
                break;

            // Parenthesized list
            case '[':
                $position++;
                $result[] = self::tokenize($str, 0, $position);
                break;
            case ']':
                $position++;
                return $result;
                break;

            // list/test separator
            case ',':
            // command separator
            case ';':
            // block/tests-list
            case '(':
            case ')':
            case '{':
            case '}':
                $sep = $str[$position];
                $position++;
                if ($num === true) {
                    $result[] = $sep;
                    break 2;
                }
                break;

            // bracket-comment
            case '/':
                if ($str[$position + 1] == '*') {
                    if ($end_pos = strpos($str, '*/', $position + 2)) {
                        $position = $end_pos + 2;
                    }
                    else {
                        // error
                        $position = $length;
                    }
                }
                break;

            // hash-comment
            case '#':
                if ($lf_pos = strpos($str, "\n", $position)) {
                    $position = $lf_pos + 1;
                    break;
                }
                else {
                    $position = $length;
                }

            // String atom
            default:
                // empty or one character
                if ($position == $length) {
                    break 2;
                }
                if ($length - $position < 2) {
                    $result[] = substr($str, $position);
                    $position = $length;
                    break;
                }

                // tag/identifier/number
                if (preg_match('/[a-zA-Z0-9:_]+/', $str, $m, PREG_OFFSET_CAPTURE, $position)
                    && $m[0][1] == $position
                ) {
                    $atom      = $m[0][0];
                    $position += strlen($atom);

                    if ($atom != 'text:') {
                        $result[] = $atom;
                    }
                    // multiline string
                    else {
                        // skip whitespace chars (except \r\n)
                        $position = self::ltrim_position($str, $position, false);

                        // possible hash-comment after "text:"
                        if ($str[$position] === '#') {
                            $endl     = strpos($str, "\n", $position);
                            $position = $endl ?: $length;
                        }

                        // skip \n or \r\n
                        if ($str[$position] == "\n") {
                            $position++;
                        }
                        else if ($str[$position] == "\r" && $str[$position] == "\n") {
                            $position += 2;
                        }

                        $text = '';

                        // get text until alone dot in a line
                        while ($position < $length) {
                            $pos = strpos($str, "\n.", $position);
                            if ($pos === false) {
                                break;
                            }

                            $text    .= substr($str, $position, $pos - $position);
                            $position = $pos + 2;

                            if ($str[$pos] == "\n"
                                || ($str[$pos] == "\r" && $str[$pos + 1] == "\n")
                            ) {
                                break;
                            }
                        }

                        // remove dot-stuffing
                        $text = str_replace("\n..", "\n.", $text);

                        $result[] = $text;
                        $position++;
                    }
                }
                // fallback, skip one character as infinite loop prevention
                else {
                    $position++;
                }

                break;
            }
        }

        return $num === 1 ? (isset($result[0]) ? $result[0] : null) : $result;
    }

    /**
     * Skip whitespace characters in a string from specified position.
     */
    static function ltrim_position($content, $position, $br = true)
    {
        $blanks = array("\t", "\0", "\x0B", " ");

        if ($br) {
            $blanks[] = "\r";
            $blanks[] = "\n";
        }

        while (isset($content[$position]) && isset($content[$position + 1])
            && in_array($content[$position], $blanks, true)
        ) {
            $position++;
        }

        return $position;
    }
}
