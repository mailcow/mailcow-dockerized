<?php namespace Sieve;

include_once('SieveToken.php');

class SieveScanner
{
    public function __construct(&$script)
    {
        if ($script === null)
            return;

        $this->tokenize($script);
    }

    public function setPassthroughFunc($callback)
    {
        if ($callback == null || is_callable($callback))
            $this->ptFn_ = $callback;
    }

    public function tokenize(&$script)
    {
        $pos = 0;
        $line = 1;

        $scriptLength = mb_strlen($script);

        $unprocessedScript = $script;


        //create one regex to find the right match
        //avoids looping over all possible tokens: increases performance
        $nameToType = [];
        $regex = [];
        // chr(65) == 'A'
        $i = 65;

        foreach ($this->tokenMatch_ as $type => $subregex) {
            $nameToType[chr($i)] = $type;
            $regex[] = "(?P<". chr($i) . ">^$subregex)";
            $i++;
        }

        $regex = '/' . join('|', $regex) . '/';

        while ($pos < $scriptLength)
        {
            if (preg_match($regex, $unprocessedScript, $match)) {

                // only keep the group that match and we only want matches with group names
                // we can use the group name to find the token type using nameToType
                $filterMatch = array_filter(array_filter($match), 'is_string', ARRAY_FILTER_USE_KEY);

                // the first element in filterMatch will contain the matched group and the key will be the name
                $type = $nameToType[key($filterMatch)];
                $currentMatch = current($filterMatch);

                //create the token
                $token = new SieveToken($type, $currentMatch, $line);
                $this->tokens_[] = $token;

                if ($type == SieveToken::Unknown)
                    return;

                // just remove the part that we parsed: don't extract the new substring using script length
                // as mb_strlen is \theta(pos)  (it's linear in the position)
                $matchLength = mb_strlen($currentMatch);
                $unprocessedScript = mb_substr($unprocessedScript, $matchLength);

                $pos += $matchLength;
                $line += mb_substr_count($currentMatch, "\n");
            } else {
                $this->tokens_[] = new SieveToken(SieveToken::Unknown, '', $line);
                return;
            }

        }

        $this->tokens_[] = new SieveToken(SieveToken::ScriptEnd, '', $line);
    }

    public function nextTokenIs($type)
    {
        return $this->peekNextToken()->is($type);
    }

    public function peekNextToken()
    {
        $offset = 0;
        do {
            $next = $this->tokens_[$this->tokenPos_ + $offset++];
        } while ($next->is(SieveToken::Comment|SieveToken::Whitespace));

        return $next;
    }

    public function nextToken()
    {
        $token = $this->tokens_[$this->tokenPos_++];

        while ($token->is(SieveToken::Comment|SieveToken::Whitespace))
        {
            if ($this->ptFn_ != null)
                call_user_func($this->ptFn_, $token);

            $token = $this->tokens_[$this->tokenPos_++];
        }

        return $token;
    }

    protected $ptFn_ = null;
    protected $tokenPos_ = 0;
    protected $tokens_ = array();
    protected $tokenMatch_ = array (
        SieveToken::LeftBracket       =>  '\[',
        SieveToken::RightBracket      =>  '\]',
        SieveToken::BlockStart        =>  '\{',
        SieveToken::BlockEnd          =>  '\}',
        SieveToken::LeftParenthesis   =>  '\(',
        SieveToken::RightParenthesis  =>  '\)',
        SieveToken::Comma             =>  ',',
        SieveToken::Semicolon         =>  ';',
        SieveToken::Whitespace        =>  '[ \r\n\t]+',
        SieveToken::Tag               =>  ':[[:alpha:]_][[:alnum:]_]*(?=\b)',
        /*
        "                           # match a quotation mark
        (                           # start matching parts that include an escaped quotation mark
        ([^"]*[^"\\\\])             # match a string without quotation marks and not ending with a backlash
        ?                           # this also includes the empty string
        (\\\\\\\\)*                 # match any groups of even number of backslashes
                                    # (thus the character after these groups are not escaped)
        \\\\"                       # match an escaped quotation mark
        )*                          # accept any number of strings that end with an escaped quotation mark
        [^"]*                       # accept any trailing part that does not contain any quotation marks
        "                           # end of the quoted string
        */
        SieveToken::QuotedString      =>  '"(([^"]*[^"\\\\])?(\\\\\\\\)*\\\\")*[^"]*"',
        SieveToken::Number            =>  '[[:digit:]]+(?:[KMG])?(?=\b)',
        SieveToken::Comment           =>  '(?:\/\*(?:[^\*]|\*(?=[^\/]))*\*\/|#[^\r\n]*\r?(\n|$))',
        SieveToken::MultilineString   =>  'text:[ \t]*(?:#[^\r\n]*)?\r?\n(\.[^\r\n]+\r?\n|[^\.][^\r\n]*\r?\n)*\.\r?(\n|$)',
        SieveToken::Identifier        =>  '[[:alpha:]_][[:alnum:]_]*(?=\b)',
        SieveToken::Unknown           =>  '[^ \r\n\t]+'
    );
}
