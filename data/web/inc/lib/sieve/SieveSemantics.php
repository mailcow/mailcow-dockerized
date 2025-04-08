<?php namespace Sieve;

require_once('SieveKeywordRegistry.php');
require_once('SieveToken.php');
require_once('SieveException.php');

class SieveSemantics
{
    protected static $requiredExtensions_ = array();

    protected $comparator_;
    protected $matchType_;
    protected $addressPart_;
    protected $tags_ = array();
    protected $arguments_;
    protected $deps_ = array();
    protected $followupToken_;

    public function __construct($token, $prevToken)
    {
        $this->registry_ = SieveKeywordRegistry::get();
        $command = strtolower($token->text);

        // Check the registry for $command
        if ($this->registry_->isCommand($command))
        {
            $xml = $this->registry_->command($command);
            $this->arguments_ = $this->makeArguments_($xml);
            $this->followupToken_ = SieveToken::Semicolon;
        }
        else if ($this->registry_->isTest($command))
        {
            $xml = $this->registry_->test($command);
            $this->arguments_ = $this->makeArguments_($xml);
            $this->followupToken_ = SieveToken::BlockStart;
        }
        else
        {
            throw new SieveException($token, 'unknown command '. $command);
        }

        // Check if command may appear at this position within the script
        if ($this->registry_->isTest($command))
        {
            if (is_null($prevToken))
                throw new SieveException($token, $command .' may not appear as first command');

            if (!preg_match('/^(if|elsif|anyof|allof|not)$/i', $prevToken->text))
                throw new SieveException($token, $command .' may not appear after '. $prevToken->text);
        }
        else if (isset($prevToken))
        {
            switch ($command)
            {
            case 'require':
                $valid_after = 'require';
                break;
            case 'elsif':
            case 'else':
                $valid_after = '(if|elsif)';
                break;
            default:
                $valid_after = $this->commandsRegex_();
            }

            if (!preg_match('/^'. $valid_after .'$/i', $prevToken->text))
                throw new SieveException($token, $command .' may not appear after '. $prevToken->text);
        }

        // Check for extension arguments to add to the command
        foreach ($this->registry_->arguments($command) as $arg)
        {
            switch ((string) $arg['type'])
            {
            case 'tag':
                array_unshift($this->arguments_, array(
                    'type'       => SieveToken::Tag,
                    'occurrence' => $this->occurrence_($arg),
                    'regex'      => $this->regex_($arg),
                    'call'       => 'tagHook_',
                    'name'       => $this->name_($arg),
                    'subArgs'    => $this->makeArguments_($arg->children())
                ));
                break;
            }
        }
    }

    public function __destruct()
    {
        $this->registry_->put();
    }

    // TODO: the *Regex functions could possibly also be static properties
    protected function requireStringsRegex_()
    {
        return '('. implode('|', $this->registry_->requireStrings()) .')';
    }

    protected function matchTypeRegex_()
    {
        return '('. implode('|', $this->registry_->matchTypes()) .')';
    }

    protected function addressPartRegex_()
    {
        return '('. implode('|', $this->registry_->addressParts()) .')';
    }

    protected function commandsRegex_()
    {
        return '('. implode('|', $this->registry_->commands()) .')';
    }

    protected function testsRegex_()
    {
        return '('. implode('|', $this->registry_->tests()) .')';
    }

    protected function comparatorRegex_()
    {
        return '('. implode('|', $this->registry_->comparators()) .')';
    }

    protected function occurrence_($arg)
    {
        if (isset($arg['occurrence']))
        {
            switch ((string) $arg['occurrence'])
            {
            case 'optional':
                return '?';
            case 'any':
                return '*';
            case 'some':
                return '+';
            }
        }
        return '1';
    }

    protected function name_($arg)
    {
        if (isset($arg['name']))
        {
            return (string) $arg['name'];
        }
        return (string) $arg['type'];
    }

    protected function regex_($arg)
    {
        if (isset($arg['regex']))
        {
            return (string) $arg['regex'];
        }
        return '.*';
    }

    protected function case_($arg)
    {
        if (isset($arg['case']))
        {
            return (string) $arg['case'];
        }
        return 'adhere';
    }

    protected function follows_($arg)
    {
        if (isset($arg['follows']))
        {
            return (string) $arg['follows'];
        }
        return '.*';
    }

    protected function makeValue_($arg)
    {
        if (isset($arg->value))
        {
            $res = $this->makeArguments_($arg->value);
            return array_shift($res);
        }
        return null;
    }

    /**
     * Convert an extension (test) commands parameters from XML to
     * a PHP array the {@see Semantics} class understands.
     * @param array(SimpleXMLElement) $parameters
     * @return array
     */
    protected function makeArguments_($parameters)
    {
        $arguments = array();

        foreach ($parameters as $arg)
        {
            // Ignore anything not a <parameter>
            if ($arg->getName() != 'parameter')
                continue;

            switch ((string) $arg['type'])
            {
            case 'addresspart':
                array_push($arguments, array(
                    'type'       => SieveToken::Tag,
                    'occurrence' => $this->occurrence_($arg),
                    'regex'      => $this->addressPartRegex_(),
                    'call'       => 'addressPartHook_',
                    'name'       => 'address part',
                    'subArgs'    => $this->makeArguments_($arg)
                ));
                break;

            case 'block':
                array_push($arguments, array(
                    'type'       => SieveToken::BlockStart,
                    'occurrence' => '1',
                    'regex'      => '{',
                    'name'       => 'block',
                    'subArgs'    => $this->makeArguments_($arg)
                ));
                break;

            case 'comparator':
                array_push($arguments, array(
                    'type'       => SieveToken::Tag,
                    'occurrence' => $this->occurrence_($arg),
                    'regex'      => 'comparator',
                    'name'       => 'comparator',
                    'subArgs'    => array( array(
                        'type'       => SieveToken::String,
                        'occurrence' => '1',
                        'call'       => 'comparatorHook_',
                        'case'       => 'adhere',
                        'regex'      => $this->comparatorRegex_(),
                        'name'       => 'comparator string',
                        'follows'    => 'comparator'
                    ))
                ));
                break;

            case 'matchtype':
                array_push($arguments, array(
                    'type'       => SieveToken::Tag,
                    'occurrence' => $this->occurrence_($arg),
                    'regex'      => $this->matchTypeRegex_(),
                    'call'       => 'matchTypeHook_',
                    'name'       => 'match type',
                    'subArgs'    => $this->makeArguments_($arg)
                ));
                break;

            case 'number':
                array_push($arguments, array(
                    'type'       => SieveToken::Number,
                    'occurrence' => $this->occurrence_($arg),
                    'regex'      => $this->regex_($arg),
                    'name'       => $this->name_($arg),
                    'follows'    => $this->follows_($arg)
                ));
                break;

            case 'requirestrings':
                array_push($arguments, array(
                    'type'       => SieveToken::StringList,
                    'occurrence' => $this->occurrence_($arg),
                    'call'       => 'setRequire_',
                    'case'       => 'adhere',
                    'regex'      => $this->requireStringsRegex_(),
                    'name'       => $this->name_($arg)
                ));
                break;

            case 'string':
                array_push($arguments, array(
                    'type'       => SieveToken::String,
                    'occurrence' => $this->occurrence_($arg),
                    'regex'      => $this->regex_($arg),
                    'case'       => $this->case_($arg),
                    'name'       => $this->name_($arg),
                    'follows'    => $this->follows_($arg)
                ));
                break;

            case 'stringlist':
                array_push($arguments, array(
                    'type'       => SieveToken::StringList,
                    'occurrence' => $this->occurrence_($arg),
                    'regex'      => $this->regex_($arg),
                    'case'       => $this->case_($arg),
                    'name'       => $this->name_($arg),
                    'follows'    => $this->follows_($arg)
                ));
                break;

            case 'tag':
                array_push($arguments, array(
                    'type'       => SieveToken::Tag,
                    'occurrence' => $this->occurrence_($arg),
                    'regex'      => $this->regex_($arg),
                    'call'       => 'tagHook_',
                    'name'       => $this->name_($arg),
                    'subArgs'    => $this->makeArguments_($arg->children()),
                    'follows'    => $this->follows_($arg)
                ));
                break;

            case 'test':
                array_push($arguments, array(
                    'type'       => SieveToken::Identifier,
                    'occurrence' => $this->occurrence_($arg),
                    'regex'      => $this->testsRegex_(),
                    'name'       => $this->name_($arg),
                    'subArgs'    => $this->makeArguments_($arg->children())
                ));
                break;

            case 'testlist':
                array_push($arguments, array(
                    'type'       => SieveToken::LeftParenthesis,
                    'occurrence' => '1',
                    'regex'      => '\(',
                    'name'       => $this->name_($arg),
                    'subArgs'    => null
                ));
                array_push($arguments, array(
                    'type'       => SieveToken::Identifier,
                    'occurrence' => '+',
                    'regex'      => $this->testsRegex_(),
                    'name'       => $this->name_($arg),
                    'subArgs'    => $this->makeArguments_($arg->children())
                ));
                break;
            }
        }

        return $arguments;
    }

    /**
     * Add argument(s) expected / allowed to appear next.
     * @param array $value
     */
    protected function addArguments_($identifier, $subArgs)
    {
        for ($i = count($subArgs); $i > 0; $i--)
        {
            $arg = $subArgs[$i-1];
            if (preg_match('/^'. $arg['follows'] .'$/si', $identifier))
                array_unshift($this->arguments_, $arg);
        }
    }

    /**
     * Add dependency that is expected to be fullfilled when parsing
     * of the current command is {@see done}.
     * @param array $dependency
     */
    protected function addDependency_($type, $name, $dependencies)
    {
        foreach ($dependencies as $d)
        {
            array_push($this->deps_, array(
                'o_type' => $type,
                'o_name' => $name,
                'type'   => $d['type'],
                'name'   => $d['name'],
                'regex'  => $d['regex']
            ));
        }
    }

    protected function invoke_($token, $func, $arg = array())
    {
        if (!is_array($arg))
            $arg = array($arg);

        $err = call_user_func_array(array(&$this, $func), $arg);

        if ($err)
            throw new SieveException($token, $err);
    }

    protected function setRequire_($extension)
    {
        array_push(self::$requiredExtensions_, $extension);
        $this->registry_->activate($extension);
    }

    /**
     * Hook function that is called after a address part match was found
     * in a command. The kind of address part is remembered in case it's
     * needed later {@see done}. For address parts from a extension
     * dependency information and valid values are looked up as well.
     * @param string $addresspart
     */
    protected function addressPartHook_($addresspart)
    {
        $this->addressPart_ = $addresspart;
        $xml = $this->registry_->addresspart($this->addressPart_);

        if (isset($xml))
        {
            // Add possible value and dependancy
            $this->addArguments_($this->addressPart_, $this->makeArguments_($xml));
            $this->addDependency_('address part', $this->addressPart_, $xml->requires);
        }
    }

    /**
     * Hook function that is called after a match type was found in a
     * command. The kind of match type is remembered in case it's
     * needed later {@see done}. For a match type from extensions
     * dependency information and valid values are looked up as well.
     * @param string $matchtype
     */
    protected function matchTypeHook_($matchtype)
    {
        $this->matchType_ = $matchtype;
        $xml = $this->registry_->matchtype($this->matchType_);

        if (isset($xml))
        {
            // Add possible value and dependancy
            $this->addArguments_($this->matchType_, $this->makeArguments_($xml));
            $this->addDependency_('match type', $this->matchType_, $xml->requires);
        }
    }

    /**
     * Hook function that is called after a comparator was found in
     * a command. The comparator is remembered in case it's needed for
     * comparsion later {@see done}. For a comparator from extensions
     * dependency information is looked up as well.
     * @param string $comparator
     */
    protected function comparatorHook_($comparator)
    {
        $this->comparator_ = $comparator;
        $xml = $this->registry_->comparator($this->comparator_);

        if (isset($xml))
        {
            // Add possible dependancy
            $this->addDependency_('comparator', $this->comparator_, $xml->requires);
        }
    }

    /**
     * Hook function that is called after a tag was found in
     * a command. The tag is remembered in case it's needed for
     * comparsion later {@see done}. For a tags from extensions
     * dependency information is looked up as well.
     * @param string $tag
     */
    protected function tagHook_($tag)
    {
        array_push($this->tags_, $tag);
        $xml = $this->registry_->argument($tag);

        // Add possible dependancies
        if (isset($xml))
            $this->addDependency_('tag', $tag, $xml->requires);
    }

    protected function validType_($token)
    {
        foreach ($this->arguments_ as $arg)
        {
            if ($arg['occurrence'] == '0')
            {
                array_shift($this->arguments_);
                continue;
            }

            if ($token->is($arg['type']))
                return;

            // Is the argument required
            if ($arg['occurrence'] != '?' && $arg['occurrence'] != '*')
                throw new SieveException($token, $arg['type']);

            array_shift($this->arguments_);
        }

        // Check if command expects any (more) arguments
        if (empty($this->arguments_))
            throw new SieveException($token, $this->followupToken_);

        throw new SieveException($token, 'unexpected '. SieveToken::typeString($token->type) .' '. $token->text);
    }

    public function startStringList($token)
    {
        $this->validType_($token);
        $this->arguments_[0]['type'] = SieveToken::String;
        $this->arguments_[0]['occurrence'] = '+';
    }

    public function continueStringList()
    {
        $this->arguments_[0]['occurrence'] = '+';
    }

    public function endStringList()
    {
        array_shift($this->arguments_);
    }

    public function validateToken($token)
    {
        // Make sure the argument has a valid type
        $this->validType_($token);

        foreach ($this->arguments_ as &$arg)
        {
            // Build regular expression according to argument type
            switch ($arg['type'])
            {
            case SieveToken::String:
            case SieveToken::StringList:
                $regex = '/^(?:text:[^\n]*\n(?P<one>'. $arg['regex'] .')\.\r?\n?|"(?P<two>'. $arg['regex'] .')")$/'
                       . ($arg['case'] == 'ignore' ? 'si' : 's');
                break;
            case SieveToken::Tag:
                $regex = '/^:(?P<one>'. $arg['regex'] .')$/si';
                break;
            default:
                $regex = '/^(?P<one>'. $arg['regex'] .')$/si';
            }

            if (preg_match($regex, $token->text, $match))
            {
                $text = ($match['one'] ? $match['one'] : $match['two']);

                // Add argument(s) that may now appear after this one
                if (isset($arg['subArgs']))
                    $this->addArguments_($text, $arg['subArgs']);

                // Call extra processing function if defined
                if (isset($arg['call']))
                    $this->invoke_($token, $arg['call'], $text);

                // Check if a possible value of this argument may occur
                if ($arg['occurrence'] == '?' || $arg['occurrence'] == '1')
                {
                    $arg['occurrence'] = '0';
                }
                else if ($arg['occurrence'] == '+')
                {
                    $arg['occurrence'] = '*';
                }

                return;
            }

            if ($token->is($arg['type']) && $arg['occurrence'] == 1)
            {
                throw new SieveException($token,
                    SieveToken::typeString($token->type) ." $token->text where ". $arg['name'] .' expected');
            }
        }

        throw new SieveException($token, 'unexpected '. SieveToken::typeString($token->type) .' '. $token->text);
    }

    public function done($token)
    {
        // Check if there are required arguments left
        foreach ($this->arguments_ as $arg)
        {
            if ($arg['occurrence'] == '+' || $arg['occurrence'] == '1')
                throw new SieveException($token, $arg['type']);
        }

        // Check if the command depends on use of a certain tag
        foreach ($this->deps_ as $d)
        {
            switch ($d['type'])
            {
            case 'addresspart':
                $values = array($this->addressPart_);
                break;

            case 'matchtype':
                $values = array($this->matchType_);
                break;

            case 'comparator':
                $values = array($this->comparator_);
                break;

            case 'tag':
                $values = $this->tags_;
                break;
            }

            foreach ($values as $value)
            {
                if (preg_match('/^'. $d['regex'] .'$/mi', $value))
                    break 2;
            }

            throw new SieveException($token,
                $d['o_type'] .' '. $d['o_name'] .' requires use of '. $d['type'] .' '. $d['name']);
        }
    }
}
