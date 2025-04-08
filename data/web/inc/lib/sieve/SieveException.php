<?php namespace Sieve;

require_once('SieveToken.php');

use Exception;

class SieveException extends Exception
{
    protected $token_;

    public function __construct(SieveToken $token, $arg)
    {
        $message = 'undefined sieve exception';
        $this->token_ = $token;

        if (is_string($arg))
        {
            $message = $arg;
        }
        else
        {
            if (is_array($arg))
            {
                $type = SieveToken::typeString(array_shift($arg));
                foreach($arg as $t)
                {
                    $type .= ' or '. SieveToken::typeString($t);
                }
            }
            else
            {
                $type = SieveToken::typeString($arg);
            }

            $tokenType = SieveToken::typeString($token->type);
            $message = "$tokenType where $type expected near ". $token->text;
        }

        parent::__construct('line '. $token->line .": $message");
    }

    public function getLineNo()
    {
        return $this->token_->line;
    }

}
