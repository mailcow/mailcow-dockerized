<?php namespace Sieve;

include_once('SieveDumpable.php');

class SieveToken implements SieveDumpable
{
    const Unknown          = 0x0000;
    const ScriptEnd        = 0x0001;
    const LeftBracket      = 0x0002;
    const RightBracket     = 0x0004;
    const BlockStart       = 0x0008;
    const BlockEnd         = 0x0010;
    const LeftParenthesis  = 0x0020;
    const RightParenthesis = 0x0040;
    const Comma            = 0x0080;
    const Semicolon        = 0x0100;
    const Whitespace       = 0x0200;
    const Tag              = 0x0400;
    const QuotedString     = 0x0800;
    const Number           = 0x1000;
    const Comment          = 0x2000;
    const MultilineString  = 0x4000;
    const Identifier       = 0x8000;

    const String        = 0x4800; // Quoted | Multiline
    const StringList    = 0x4802; // Quoted | Multiline | LeftBracket
    const StringListSep = 0x0084; // Comma | RightBracket
    const Unparsed      = 0x2200; // Comment | Whitespace
    const TestList      = 0x8020; // Identifier | LeftParenthesis

    public $type;
    public $text;
    public $line;

    public function __construct($type, $text, $line)
    {
        $this->text = $text;
        $this->type = $type;
        $this->line = intval($line);
    }

    public function dump()
    {
        return '<'. SieveToken::escape($this->text) .'> type:'. SieveToken::typeString($this->type) .' line:'. $this->line;
    }

    public function text()
    {
        return $this->text;
    }

    public function is($type)
    {
        return (bool)($this->type & $type);
    }

    public static function typeString($type)
    {
        switch ($type)
        {
        case SieveToken::Identifier: return 'identifier';
        case SieveToken::Whitespace: return 'whitespace';
        case SieveToken::QuotedString: return 'quoted string';
        case SieveToken::Tag: return 'tag';
        case SieveToken::Semicolon: return 'semicolon';
        case SieveToken::LeftBracket: return 'left bracket';
        case SieveToken::RightBracket: return 'right bracket';
        case SieveToken::BlockStart: return 'block start';
        case SieveToken::BlockEnd: return 'block end';
        case SieveToken::LeftParenthesis: return 'left parenthesis';
        case SieveToken::RightParenthesis: return 'right parenthesis';
        case SieveToken::Comma: return 'comma';
        case SieveToken::Number: return 'number';
        case SieveToken::Comment: return 'comment';
        case SieveToken::MultilineString: return 'multiline string';
        case SieveToken::ScriptEnd: return 'script end';
        case SieveToken::String: return 'string';
        case SieveToken::StringList: return 'string list';
        default: return 'unknown token';
        }
    }

    protected static $tr_ = array("\r" => '\r', "\n" => '\n', "\t" => '\t');
    public static function escape($val)
    {
        return strtr($val, self::$tr_);
    }
}
