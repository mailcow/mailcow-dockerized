<?php namespace Sieve;

class SieveKeywordRegistry
{
    protected $registry_ = array();
    protected $matchTypes_ = array();
    protected $comparators_ = array();
    protected $addressParts_ = array();
    protected $commands_ = array();
    protected $tests_ = array();
    protected $arguments_ = array();

    protected static $refcount = 0;
    protected static $instance = null;

    protected function __construct()
    {
        $keywords = simplexml_load_file(dirname(__FILE__) .'/keywords.xml');
        foreach ($keywords->children() as $keyword)
        {
            switch ($keyword->getName())
            {
            case 'matchtype':
                $type =& $this->matchTypes_;
                break;
            case 'comparator':
                $type =& $this->comparators_;
                break;
            case 'addresspart':
                $type =& $this->addressParts_;
                break;
            case 'test':
                $type =& $this->tests_;
                break;
            case 'command':
                $type =& $this->commands_;
                break;
            default:
                trigger_error('Unsupported keyword type "'. $keyword->getName()
                    . '" in file "keywords/'. basename($file) .'"');
                return;
            }

            $name = (string) $keyword['name'];
            if (array_key_exists($name, $type))
                trigger_error("redefinition of $type $name - skipping");
            else
                $type[$name] = $keyword->children();
        }

        foreach (glob(dirname(__FILE__) .'/extensions/*.xml') as $file)
        {
            $extension = simplexml_load_file($file);
            $name = (string) $extension['name'];

            if (array_key_exists($name, $this->registry_))
            {
                trigger_error('overwriting extension "'. $name .'"');
            }
            $this->registry_[$name] = $extension;
        }
    }

    public static function get()
    {
        if (self::$instance == null)
        {
            self::$instance = new SieveKeywordRegistry();
        }

        self::$refcount++;

        return self::$instance;
    }

    public function put()
    {
        if (--self::$refcount == 0)
        {
            self::$instance = null;
        }
    }

    public function activate($extension)
    {
        if (!isset($this->registry_[$extension]))
        {
            return;
        }

        $xml = $this->registry_[$extension];

        foreach ($xml->children() as $e)
        {
            switch ($e->getName())
            {
            case 'matchtype':
                $type =& $this->matchTypes_;
                break;
            case 'comparator':
                $type =& $this->comparators_;
                break;
            case 'addresspart':
                $type =& $this->addressParts_;
                break;
            case 'test':
                $type =& $this->tests_;
                break;
            case 'command':
                $type =& $this->commands_;
                break;
            case 'tagged-argument':
                $xml = $e->parameter[0];
                $this->arguments_[(string) $xml['name']] = array(
                    'extends' => (string) $e['extends'],
                    'rules'   => $xml
                );
                continue;
            default:
                trigger_error('Unsupported extension type \''.
                    $e->getName() ."' in extension '$extension'");
                return;
            }

            $name = (string) $e['name'];
            if (!isset($type[$name]) ||
                (string) $e['overrides'] == 'true')
            {
                $type[$name] = $e->children();
            }
        }
    }

    public function isTest($name)
    {
        return (isset($this->tests_[$name]) ? true : false);
    }

    public function isCommand($name)
    {
        return (isset($this->commands_[$name]) ? true : false);
    }

    public function matchtype($name)
    {
        if (isset($this->matchTypes_[$name]))
        {
            return $this->matchTypes_[$name];
        }
        return null;
    }

    public function addresspart($name)
    {
        if (isset($this->addressParts_[$name]))
        {
            return $this->addressParts_[$name];
        }
        return null;
    }

    public function comparator($name)
    {
        if (isset($this->comparators_[$name]))
        {
            return $this->comparators_[$name];
        }
        return null;
    }

    public function test($name)
    {
        if (isset($this->tests_[$name]))
        {
            return $this->tests_[$name];
        }
        return null;
    }

    public function command($name)
    {
        if (isset($this->commands_[$name]))
        {
            return $this->commands_[$name];
        }
        return null;
    }

    public function arguments($command)
    {
        $res = array();
        foreach ($this->arguments_ as $arg)
        {
            if (preg_match('/'.$arg['extends'].'/', $command))
                array_push($res, $arg['rules']);
        }
        return $res;
    }

    public function argument($name)
    {
        if (isset($this->arguments_[$name]))
        {
            return $this->arguments_[$name]['rules'];
        }
        return null;
    }

    public function requireStrings()
    {
        return array_keys($this->registry_);
    }
    public function matchTypes()
    {
        return array_keys($this->matchTypes_);
    }
    public function comparators()
    {
        return array_keys($this->comparators_);
    }
    public function addressParts()
    {
        return array_keys($this->addressParts_);
    }
    public function tests()
    {
        return array_keys($this->tests_);
    }
    public function commands()
    {
        return array_keys($this->commands_);
    }
}
