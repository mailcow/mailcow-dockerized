<?php namespace Sieve;

include_once 'SieveTree.php';
include_once 'SieveScanner.php';
include_once 'SieveSemantics.php';
include_once 'SieveException.php';

class SieveParser
{
    protected $scanner_;
    protected $script_;
    protected $tree_;
    protected $status_;

    public function __construct($script = null)
    {
        if (isset($script))
            $this->parse($script);
    }

    public function GetParseTree()
    {
        return $this->tree_;
    }

    public function dumpParseTree()
    {
        return $this->tree_->dump();
    }

    public function getScriptText()
    {
        return $this->tree_->getText();
    }

    protected function getPrevToken_($parent_id)
    {
        $childs = $this->tree_->getChilds($parent_id);

        for ($i = count($childs); $i > 0; --$i)
        {
            $prev = $this->tree_->getNode($childs[$i-1]);
            if ($prev->is(SieveToken::Comment|SieveToken::Whitespace))
                continue;

            // use command owning a block or list instead of previous
            if ($prev->is(SieveToken::BlockStart|SieveToken::Comma|SieveToken::LeftParenthesis))
                $prev = $this->tree_->getNode($parent_id);

            return $prev;
        }

        return $this->tree_->getNode($parent_id);
    }

    /*******************************************************************************
     * methods for recursive descent start below
     */
    public function passthroughWhitespaceComment($token)
    {
        return 0;
    }

    public function passthroughFunction($token)
    {
        $this->tree_->addChild($token);
    }

    public function parse($script)
    {
        $this->script_ = $script;

        $this->scanner_ = new SieveScanner($this->script_);

        // Define what happens with passthrough tokens like whitespacs and comments
        $this->scanner_->setPassthroughFunc(
            array(
                $this, 'passthroughWhitespaceComment'
            )
        );

        $this->tree_ = new SieveTree('tree');

        $this->commands_($this->tree_->getRoot());

        if (!$this->scanner_->nextTokenIs(SieveToken::ScriptEnd)) {
            $token = $this->scanner_->nextToken();
            throw new SieveException($token, SieveToken::ScriptEnd);
        }
    }

    protected function commands_($parent_id)
    {
        while (true)
        {
            if (!$this->scanner_->nextTokenIs(SieveToken::Identifier))
                break;

            // Get and check a command token
            $token = $this->scanner_->nextToken();
            $semantics = new SieveSemantics($token, $this->getPrevToken_($parent_id));

            // Process eventual arguments
            $this_node = $this->tree_->addChildTo($parent_id, $token);
            $this->arguments_($this_node, $semantics);

            $token = $this->scanner_->nextToken();
            if (!$token->is(SieveToken::Semicolon))
            {
                // TODO: check if/when semcheck is needed here
                $semantics->validateToken($token);

                if ($token->is(SieveToken::BlockStart))
                {
                    $this->tree_->addChildTo($this_node, $token);
                    $this->block_($this_node, $semantics);
                    continue;
                }

                throw new SieveException($token, SieveToken::Semicolon);
            }

            $semantics->done($token);
            $this->tree_->addChildTo($this_node, $token);
        }
    }

    protected function arguments_($parent_id, &$semantics)
    {
        while (true)
        {
            if ($this->scanner_->nextTokenIs(SieveToken::Number|SieveToken::Tag))
            {
                // Check if semantics allow a number or tag
                $token = $this->scanner_->nextToken();
                $semantics->validateToken($token);
                $this->tree_->addChildTo($parent_id, $token);
            }
            else if ($this->scanner_->nextTokenIs(SieveToken::StringList))
            {
                $this->stringlist_($parent_id, $semantics);
            }
            else
            {
                break;
            }
        }

        if ($this->scanner_->nextTokenIs(SieveToken::TestList))
        {
            $this->testlist_($parent_id, $semantics);
        }
    }

    protected function stringlist_($parent_id, &$semantics)
    {
        if (!$this->scanner_->nextTokenIs(SieveToken::LeftBracket))
        {
            $this->string_($parent_id, $semantics);
            return;
        }

        $token = $this->scanner_->nextToken();
        $semantics->startStringList($token);
        $this->tree_->addChildTo($parent_id, $token);
        
        if($this->scanner_->nextTokenIs(SieveToken::RightBracket)) {
            //allow empty lists
            $token = $this->scanner_->nextToken();
            $this->tree_->addChildTo($parent_id, $token);
            $semantics->endStringList();
            return;
        }

        do
        {
            $this->string_($parent_id, $semantics);
            $token = $this->scanner_->nextToken();

            if (!$token->is(SieveToken::Comma|SieveToken::RightBracket))
                throw new SieveException($token, array(SieveToken::Comma, SieveToken::RightBracket));

            if ($token->is(SieveToken::Comma))
                $semantics->continueStringList();

            $this->tree_->addChildTo($parent_id, $token);
        }
        while (!$token->is(SieveToken::RightBracket));

        $semantics->endStringList();
    }

    protected function string_($parent_id, &$semantics)
    {
        $token = $this->scanner_->nextToken();
        $semantics->validateToken($token);
        $this->tree_->addChildTo($parent_id, $token);
    }

    protected function testlist_($parent_id, &$semantics)
    {
        if (!$this->scanner_->nextTokenIs(SieveToken::LeftParenthesis))
        {
            $this->test_($parent_id, $semantics);
            return;
        }

        $token = $this->scanner_->nextToken();
        $semantics->validateToken($token);
        $this->tree_->addChildTo($parent_id, $token);

        do
        {
            $this->test_($parent_id, $semantics);

            $token = $this->scanner_->nextToken();
            if (!$token->is(SieveToken::Comma|SieveToken::RightParenthesis))
            {
                throw new SieveException($token, array(SieveToken::Comma, SieveToken::RightParenthesis));
            }
            $this->tree_->addChildTo($parent_id, $token);
        }
        while (!$token->is(SieveToken::RightParenthesis));
    }

    protected function test_($parent_id, &$semantics)
    {
        // Check if semantics allow an identifier
        $token = $this->scanner_->nextToken();
        $semantics->validateToken($token);

        // Get semantics for this test command
        $this_semantics = new SieveSemantics($token, $this->getPrevToken_($parent_id));
        $this_node = $this->tree_->addChildTo($parent_id, $token);

        // Consume eventual argument tokens
        $this->arguments_($this_node, $this_semantics);

        // Check that all required arguments were there
        $token = $this->scanner_->peekNextToken();
        $this_semantics->done($token);
    }

    protected function block_($parent_id, &$semantics)
    {
        $this->commands_($parent_id, $semantics);

        $token = $this->scanner_->nextToken();
        if (!$token->is(SieveToken::BlockEnd))
        {
            throw new SieveException($token, SieveToken::BlockEnd);
        }
        $this->tree_->addChildTo($parent_id, $token);
    }
}
