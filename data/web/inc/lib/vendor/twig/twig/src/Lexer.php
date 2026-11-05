<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 * (c) Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig;

use Twig\Error\SyntaxError;
use Twig\ExpressionParser\ExpressionParsers;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Lexer
{
    private $isInitialized = false;

    private $tokens;
    private $code;
    private $cursor;
    private $lineno;
    private $end;
    private $state;
    private $states;
    private $brackets;
    private $env;
    private $source;
    private $options;
    private $regexes;
    private $position;
    private $positions;
    private $currentVarBlockLine;
    /** @var list<string> */
    private array $documentation = [];
    private array $openingBrackets = ['{', '(', '['];
    private array $closingBrackets = ['}', ')', ']'];

    public const STATE_DATA = 0;
    public const STATE_BLOCK = 1;
    public const STATE_VAR = 2;
    public const STATE_STRING = 3;
    public const STATE_INTERPOLATION = 4;

    public const REGEX_NAME = '/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/A';
    public const REGEX_STRING = '/"([^#"\\\\]*(?:\\\\.[^#"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\'/As';

    public const REGEX_NUMBER = '/(?(DEFINE)
        (?<LNUM>[0-9]+(_[0-9]+)*)               # Integers (with underscores)   123_456
        (?<FRAC>\.(?&LNUM))                     # Fractional part               .456
        (?<EXPONENT>[eE][+-]?(?&LNUM))          # Exponent part                 E+10
        (?<DNUM>(?&LNUM)(?:(?&FRAC))?)          # Decimal number                123_456.456
    )(?:(?&DNUM)(?:(?&EXPONENT))?)              #                               123_456.456E+10
    /Ax';

    public const REGEX_DQ_STRING_DELIM = '/"/A';
    public const REGEX_DQ_STRING_PART = '/[^#"\\\\]*(?:(?:\\\\.|#(?!\{))[^#"\\\\]*)*/As';
    public const REGEX_INLINE_COMMENT = '/#[^\n]*/A';
    public const PUNCTUATION = '()[]{}?:.,|';

    private const REGEX_RAW_INLINE_COMMENT = '/#[^\r\n]*/A';

    private const SPECIAL_CHARS = [
        'f' => "\f",
        'n' => "\n",
        'r' => "\r",
        't' => "\t",
        'v' => "\v",
    ];

    public function __construct(Environment $env, array $options = [])
    {
        $this->env = $env;

        $this->options = array_merge([
            'tag_comment' => ['{#', '#}'],
            'tag_block' => ['{%', '%}'],
            'tag_variable' => ['{{', '}}'],
            'whitespace_trim' => '-',
            'whitespace_line_trim' => '~',
            'whitespace_line_chars' => ' \t\0\x0B',
            'interpolation' => ['#{', '}'],
        ], $options);
    }

    private function initialize(): void
    {
        if ($this->isInitialized) {
            return;
        }

        // when the comment closing tag starts with "#" (as "#}" does), the empty comment ("{##}")
        // starts like a documentation comment ("{##"); make sure it keeps lexing as a comment
        $emptyCommentLookahead = str_starts_with($this->options['tag_comment'][1], '#') ? '(?!'.preg_quote(substr($this->options['tag_comment'][1], 1), '#').')' : '';

        // when PHP 7.3 is the min version, we will be able to remove the '#' part in preg_quote as it's part of the default
        $this->regexes = [
            // }}
            'lex_var' => '{
                \s*
                (?:'.
                    preg_quote($this->options['whitespace_trim'].$this->options['tag_variable'][1], '#').'\s*'. // -}}\s*
                    '|'.
                    preg_quote($this->options['whitespace_line_trim'].$this->options['tag_variable'][1], '#').'['.$this->options['whitespace_line_chars'].']*'. // ~}}[ \t\0\x0B]*
                    '|'.
                    preg_quote($this->options['tag_variable'][1], '#'). // }}
                ')
            }Ax',

            // %}
            'lex_block' => '{
                \s*
                (?:'.
                    preg_quote($this->options['whitespace_trim'].$this->options['tag_block'][1], '#').'\s*\n?'. // -%}\s*\n?
                    '|'.
                    preg_quote($this->options['whitespace_line_trim'].$this->options['tag_block'][1], '#').'['.$this->options['whitespace_line_chars'].']*'. // ~%}[ \t\0\x0B]*
                    '|'.
                    preg_quote($this->options['tag_block'][1], '#').'(?:\r\n?|\n)?'. // %}(?:\r\n?|\n)?
                ')
            }Ax',

            // {% endverbatim %}
            'lex_raw_data' => '{'.
                preg_quote($this->options['tag_block'][0], '#'). // {%
                '('.
                    $this->options['whitespace_trim']. // -
                    '|'.
                    $this->options['whitespace_line_trim']. // ~
                ')?\s*endverbatim\s*'.
                '(?:'.
                    preg_quote($this->options['whitespace_trim'].$this->options['tag_block'][1], '#').'\s*'. // -%}
                    '|'.
                    preg_quote($this->options['whitespace_line_trim'].$this->options['tag_block'][1], '#').'['.$this->options['whitespace_line_chars'].']*'. // ~%}[ \t\0\x0B]*
                    '|'.
                    preg_quote($this->options['tag_block'][1], '#'). // %}
                ')
            }sx',

            'operator' => $this->getOperatorRegex(),

            // #}
            'lex_comment' => '{
                (?:'.
                    preg_quote($this->options['whitespace_trim'].$this->options['tag_comment'][1], '#').'\s*\n?'. // -#}\s*\n?
                    '|'.
                    preg_quote($this->options['whitespace_line_trim'].$this->options['tag_comment'][1], '#').'['.$this->options['whitespace_line_chars'].']*'. // ~#}[ \t\0\x0B]*
                    '|'.
                    preg_quote($this->options['tag_comment'][1], '#').'(?:\r\n?|\n)?'. // #}(?:\r\n?|\n)?
                ')
            }sx',

            // #} or ##}
            'lex_documentation_comment' => '{
                (?:'.
                    preg_quote($this->options['whitespace_trim'].'#'.$this->options['tag_comment'][1], '#').'\s*\n?'. // -##}\s*\n?
                    '|'.
                    preg_quote($this->options['whitespace_line_trim'].'#'.$this->options['tag_comment'][1], '#').'['.$this->options['whitespace_line_chars'].']*'. // ~##}[ \t\0\x0B]*
                    '|'.
                    preg_quote($this->options['whitespace_trim'].$this->options['tag_comment'][1], '#').'\s*\n?'. // -#}\s*\n?
                    '|'.
                    preg_quote($this->options['whitespace_line_trim'].$this->options['tag_comment'][1], '#').'['.$this->options['whitespace_line_chars'].']*'. // ~#}[ \t\0\x0B]*
                    '|'.
                    preg_quote($this->options['tag_comment'][1], '#').'(?:\r\n?|\n)?'. // #}(?:\r\n?|\n)?
                ')
            }sx',

            // verbatim %}
            'lex_block_raw' => '{
                \s*verbatim\s*
                (?:'.
                    preg_quote($this->options['whitespace_trim'].$this->options['tag_block'][1], '#').'\s*'. // -%}\s*
                    '|'.
                    preg_quote($this->options['whitespace_line_trim'].$this->options['tag_block'][1], '#').'['.$this->options['whitespace_line_chars'].']*'. // ~%}[ \t\0\x0B]*
                    '|'.
                    preg_quote($this->options['tag_block'][1], '#'). // %}
                ')
            }Asx',

            'lex_block_line' => '{\s*line\s+(\d+)\s*'.preg_quote($this->options['tag_block'][1], '#').'}As',

            // {{ or {% or {#
            'lex_tokens_start' => '{
                ('.
                    preg_quote($this->options['tag_variable'][0], '#'). // {{
                    '|'.
                    preg_quote($this->options['tag_block'][0], '#'). // {%
                    '|'.
                    preg_quote($this->options['tag_comment'][0].'#', '#').$emptyCommentLookahead. // {## (but not the empty comment {##})
                    '|'.
                    preg_quote($this->options['tag_comment'][0], '#'). // {#
                ')('.
                    preg_quote($this->options['whitespace_trim'], '#'). // -
                    '|'.
                    preg_quote($this->options['whitespace_line_trim'], '#'). // ~
                ')?
            }sx',
            'interpolation_start' => '{'.preg_quote($this->options['interpolation'][0], '#').'\s*}A',
            'interpolation_end' => '{\s*'.preg_quote($this->options['interpolation'][1], '#').'}A',
        ];

        $this->isInitialized = true;
    }

    public function tokenize(Source $source): TokenStream
    {
        $this->initialize();

        $this->source = $source;
        $this->code = $source->getCode();
        $this->cursor = 0;
        $this->lineno = 1;
        $this->end = \strlen($this->code);
        $this->tokens = [];
        $this->state = self::STATE_DATA;
        $this->states = [];
        $this->brackets = [];
        $this->position = -1;
        $this->documentation = [];

        // find all token starts in one go
        preg_match_all($this->regexes['lex_tokens_start'], $this->code, $matches, \PREG_OFFSET_CAPTURE);
        $this->positions = $matches;

        while ($this->cursor < $this->end) {
            // dispatch to the lexing functions depending
            // on the current state
            switch ($this->state) {
                case self::STATE_DATA:
                    $this->lexData();
                    break;

                case self::STATE_BLOCK:
                    $this->lexBlock();
                    break;

                case self::STATE_VAR:
                    $this->lexVar();
                    break;

                case self::STATE_STRING:
                    $this->lexString();
                    break;

                case self::STATE_INTERPOLATION:
                    $this->lexInterpolation();
                    break;
            }
        }

        $this->pushToken(Token::EOF_TYPE);

        if ($this->brackets) {
            [$expect, $lineno] = array_pop($this->brackets);
            throw new SyntaxError(\sprintf('Unclosed "%s".', $expect), $lineno, $this->source);
        }

        return new TokenStream($this->tokens, $this->source);
    }

    private function lexData(): void
    {
        // if no matches are left we return the rest of the template as simple text token
        if ($this->position == \count($this->positions[0]) - 1) {
            $text = substr($this->code, $this->cursor);
            $this->pushToken(Token::TEXT_TYPE, $this->normalizeNewlines($text));
            $this->moveCursor($text);

            return;
        }

        // Find the first token after the current cursor
        $position = $this->positions[0][++$this->position];
        while ($position[1] < $this->cursor) {
            if ($this->position == \count($this->positions[0]) - 1) {
                return;
            }
            $position = $this->positions[0][++$this->position];
        }

        // push the template text first
        $text = $textContent = substr($this->code, $this->cursor, $position[1] - $this->cursor);

        // trim?
        if (isset($this->positions[2][$this->position][0])) {
            if ($this->options['whitespace_trim'] === $this->positions[2][$this->position][0]) {
                // whitespace_trim detected ({%-, {{- or {#-)
                $text = rtrim($text);
            } elseif ($this->options['whitespace_line_trim'] === $this->positions[2][$this->position][0]) {
                // whitespace_line_trim detected ({%~, {{~ or {#~)
                // don't trim \r and \n
                $text = rtrim($text, " \t\0\x0B");
            }
        }
        $this->pushToken(Token::TEXT_TYPE, $this->normalizeNewlines($text));
        $this->moveCursor($textContent);

        switch ($this->positions[1][$this->position][0]) {
            case $this->options['tag_comment'][0].'#':
                $this->moveCursor($position[0]);
                $this->lexComment(true);
                break;

            case $this->options['tag_comment'][0]:
                $this->moveCursor($position[0]);
                $this->lexComment();
                break;

            case $this->options['tag_block'][0]:
                $lineno = $this->lineno;
                $cursor = $this->cursor;
                $this->moveCursor($position[0]);

                // raw data?
                if (preg_match($this->regexes['lex_block_raw'], $this->code, $match, 0, $this->cursor)) {
                    $this->documentation = [];
                    $this->moveCursor($match[0]);
                    $this->lexRawData();
                // {% line \d+ %}
                } elseif (preg_match($this->regexes['lex_block_line'], $this->code, $match, 0, $this->cursor)) {
                    $this->documentation = [];
                    $this->moveCursor($match[0]);
                    $this->lineno = (int) $match[1];
                } else {
                    $this->pushToken(Token::BLOCK_START_TYPE, '', $cursor, $lineno);
                    $this->pushState(self::STATE_BLOCK);
                    $this->currentVarBlockLine = $this->lineno;
                }
                break;

            case $this->options['tag_variable'][0]:
                $lineno = $this->lineno;
                $cursor = $this->cursor;
                $this->moveCursor($position[0]);
                $this->pushToken(Token::VAR_START_TYPE, '', $cursor, $lineno);
                $this->pushState(self::STATE_VAR);
                $this->currentVarBlockLine = $this->lineno;
                break;
        }
    }

    private function lexBlock(): void
    {
        if (!$this->brackets && preg_match($this->regexes['lex_block'], $this->code, $match, 0, $this->cursor)) {
            $this->pushClosingToken(Token::BLOCK_END_TYPE, $match[0]);
            $this->popState();
        } else {
            $this->lexExpression();
        }
    }

    private function lexVar(): void
    {
        if (!$this->brackets && preg_match($this->regexes['lex_var'], $this->code, $match, 0, $this->cursor)) {
            $this->pushClosingToken(Token::VAR_END_TYPE, $match[0]);
            $this->popState();
        } else {
            $this->lexExpression();
        }
    }

    private function lexExpression(): void
    {
        // whitespace
        if (preg_match('/\s+/A', $this->code, $match, 0, $this->cursor)) {
            $this->moveCursor($match[0]);

            if ($this->cursor >= $this->end) {
                throw new SyntaxError(\sprintf('Unclosed "%s".', self::STATE_BLOCK === $this->state ? 'block' : 'variable'), $this->currentVarBlockLine, $this->source);
            }
        }

        // operators
        if (preg_match($this->regexes['operator'], $this->code, $match, 0, $this->cursor)) {
            $operator = preg_replace('/\s+/', ' ', $match[0]);
            if (\in_array($operator, $this->openingBrackets, true)) {
                $this->checkBrackets($operator);
            }
            $this->pushToken(Token::OPERATOR_TYPE, $operator);
            $this->moveCursor($match[0]);
        }
        // names
        elseif (preg_match(self::REGEX_NAME, $this->code, $match, 0, $this->cursor)) {
            $this->pushToken(Token::NAME_TYPE, $match[0]);
            $this->moveCursor($match[0]);
        }
        // numbers
        elseif (preg_match(self::REGEX_NUMBER, $this->code, $match, 0, $this->cursor)) {
            $this->pushToken(Token::NUMBER_TYPE, 0 + str_replace('_', '', $match[0]));
            $this->moveCursor($match[0]);
        }
        // punctuation
        elseif (str_contains(self::PUNCTUATION, $this->code[$this->cursor])) {
            $this->checkBrackets($this->code[$this->cursor]);
            $this->pushToken(Token::PUNCTUATION_TYPE, $this->code[$this->cursor]);
            $this->moveCursor($this->code[$this->cursor]);
        }
        // strings
        elseif (preg_match(self::REGEX_STRING, $this->code, $match, 0, $this->cursor)) {
            $this->pushToken(Token::STRING_TYPE, $this->stripcslashes($this->normalizeNewlines(substr($match[0], 1, -1)), substr($match[0], 0, 1)));
            $this->moveCursor($match[0]);
        }
        // opening double quoted string
        elseif (preg_match(self::REGEX_DQ_STRING_DELIM, $this->code, $match, 0, $this->cursor)) {
            $this->brackets[] = ['"', $this->lineno];
            $this->pushState(self::STATE_STRING);
            $this->moveCursor($match[0]);
        }
        // inline comment
        elseif (preg_match(self::REGEX_RAW_INLINE_COMMENT, $this->code, $match, 0, $this->cursor)) {
            if (str_starts_with($match[0], '##')) {
                $this->addDocumentation(substr($match[0], 2));
            }
            $this->moveCursor($match[0]);
        }
        // unlexable
        else {
            throw new SyntaxError(\sprintf('Unexpected character "%s".', $this->code[$this->cursor]), $this->lineno, $this->source, columnno: $this->source->getColumn($this->cursor));
        }
    }

    private function stripcslashes(string $str, string $quoteType): string
    {
        $result = '';
        $length = \strlen($str);

        $i = 0;
        while ($i < $length) {
            if (false === $pos = strpos($str, '\\', $i)) {
                $result .= substr($str, $i);
                break;
            }

            $result .= substr($str, $i, $pos - $i);
            $i = $pos + 1;

            if ($i >= $length) {
                $result .= '\\';
                break;
            }

            $nextChar = $str[$i];

            if (isset(self::SPECIAL_CHARS[$nextChar])) {
                $result .= self::SPECIAL_CHARS[$nextChar];
            } elseif ('\\' === $nextChar) {
                $result .= $nextChar;
            } elseif ("'" === $nextChar || '"' === $nextChar) {
                if ($nextChar !== $quoteType) {
                    trigger_deprecation('twig/twig', '3.12', 'Character "%s" should not be escaped; the "\" character is ignored in Twig 3 but will not be in Twig 4. Please remove the extra "\" character at position %d in "%s" at line %d.', $nextChar, $i + 1, $this->source->getName(), $this->lineno);
                }
                $result .= $nextChar;
            } elseif ('#' === $nextChar && $i + 1 < $length && '{' === $str[$i + 1]) {
                $result .= '#{';
                ++$i;
            } elseif ('x' === $nextChar && $i + 1 < $length && ctype_xdigit($str[$i + 1])) {
                $hex = $str[++$i];
                if ($i + 1 < $length && ctype_xdigit($str[$i + 1])) {
                    $hex .= $str[++$i];
                }
                $result .= \chr(hexdec($hex));
            } elseif (ctype_digit($nextChar) && $nextChar < '8') {
                $octal = $nextChar;
                while ($i + 1 < $length && ctype_digit($str[$i + 1]) && $str[$i + 1] < '8' && \strlen($octal) < 3) {
                    $octal .= $str[++$i];
                }
                $result .= \chr(octdec($octal) % 256);
            } else {
                trigger_deprecation('twig/twig', '3.12', 'Character "%s" should not be escaped; the "\" character is ignored in Twig 3 but will not be in Twig 4. Please remove the extra "\" character at position %d in "%s" at line %d.', $nextChar, $i + 1, $this->source->getName(), $this->lineno);
                $result .= $nextChar;
            }

            ++$i;
        }

        return $result;
    }

    private function lexRawData(): void
    {
        if (!preg_match($this->regexes['lex_raw_data'], $this->code, $match, \PREG_OFFSET_CAPTURE, $this->cursor)) {
            throw new SyntaxError('Unexpected end of file: Unclosed "verbatim" block.', $this->lineno, $this->source);
        }

        $offset = $this->cursor;
        $text = substr($this->code, $this->cursor, $match[0][1] - $this->cursor);
        $this->moveCursor($text.$match[0][0]);

        // trim?
        if (isset($match[1][0])) {
            if ($this->options['whitespace_trim'] === $match[1][0]) {
                // whitespace_trim detected ({%-, {{- or {#-)
                $text = rtrim($text);
            } else {
                // whitespace_line_trim detected ({%~, {{~ or {#~)
                // don't trim \r and \n
                $text = rtrim($text, " \t\0\x0B");
            }
        }

        $this->pushToken(Token::TEXT_TYPE, $this->normalizeNewlines($text), $offset);
    }

    private function lexComment(bool $isDocumentation = false): void
    {
        $regex = $isDocumentation ? 'lex_documentation_comment' : 'lex_comment';
        if (!preg_match($this->regexes[$regex], $this->code, $match, \PREG_OFFSET_CAPTURE, $this->cursor)) {
            throw new SyntaxError('Unclosed comment.', $this->lineno, $this->source);
        }

        $comment = substr($this->code, $this->cursor, $match[0][1] - $this->cursor);
        if ($isDocumentation) {
            $documentation = $comment;
            // support a symmetric "##}" closing marker
            if (str_ends_with($documentation, '#')) {
                $documentation = substr($documentation, 0, -1);
            }
            $this->addDocumentation($documentation);
        }

        $this->moveCursor($comment.$match[0][0]);
    }

    private function lexString(): void
    {
        if (preg_match($this->regexes['interpolation_start'], $this->code, $match, 0, $this->cursor)) {
            $this->brackets[] = [$this->options['interpolation'][0], $this->lineno];
            $this->pushToken(Token::INTERPOLATION_START_TYPE);
            $this->moveCursor($match[0]);
            $this->pushState(self::STATE_INTERPOLATION);
        } elseif (preg_match(self::REGEX_DQ_STRING_PART, $this->code, $match, 0, $this->cursor) && '' !== $match[0]) {
            $this->pushToken(Token::STRING_TYPE, $this->stripcslashes($this->normalizeNewlines($match[0]), '"'));
            $this->moveCursor($match[0]);
        } elseif (preg_match(self::REGEX_DQ_STRING_DELIM, $this->code, $match, 0, $this->cursor)) {
            [$expect, $lineno] = array_pop($this->brackets);
            if ('"' != $this->code[$this->cursor]) {
                throw new SyntaxError(\sprintf('Unclosed "%s".', $expect), $lineno, $this->source);
            }

            $this->popState();
            $this->moveCursor($match[0]);
        } else {
            // unlexable
            throw new SyntaxError(\sprintf('Unexpected character "%s".', $this->code[$this->cursor]), $this->lineno, $this->source, columnno: $this->source->getColumn($this->cursor));
        }
    }

    private function lexInterpolation(): void
    {
        $bracket = end($this->brackets);
        if ($this->options['interpolation'][0] === $bracket[0] && preg_match($this->regexes['interpolation_end'], $this->code, $match, 0, $this->cursor)) {
            array_pop($this->brackets);
            $this->pushClosingToken(Token::INTERPOLATION_END_TYPE, $match[0]);
            $this->popState();
        } else {
            $this->lexExpression();
        }
    }

    private function pushToken($type, $value = '', ?int $offset = null, ?int $lineno = null): void
    {
        // do not push empty text tokens
        if (Token::TEXT_TYPE === $type && '' === $value) {
            return;
        }

        // by default the token starts at the current cursor; callers that
        // emit a token after consuming it must pass an explicit offset
        $documentation = null;
        if ($this->documentation && (Token::TEXT_TYPE !== $type || '' !== trim($value))) {
            $documentation = implode("\n", $this->documentation);
            $this->documentation = [];
        }

        $this->tokens[] = new Token($type, $value, $lineno ?? $this->lineno, $offset ?? $this->cursor, $documentation);
    }

    private function addDocumentation(string $documentation): void
    {
        if ('' !== $documentation = trim($this->normalizeNewlines($documentation))) {
            $this->documentation[] = $documentation;
        }
    }

    private function moveCursor($text): void
    {
        $this->cursor += \strlen($text);
        // count "\r\n" and "\r" as a single newline without allocating a
        // normalized copy when the chunk has no carriage return (common case)
        $this->lineno += str_contains($text, "\r") ? substr_count($this->normalizeNewlines($text), "\n") : substr_count($text, "\n");
    }

    private function normalizeNewlines(string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text);
    }

    private function pushClosingToken(int $type, string $match): void
    {
        $leadingWhitespaceLength = \strlen($match) - \strlen(ltrim($match));
        if ($leadingWhitespaceLength) {
            $this->moveCursor(substr($match, 0, $leadingWhitespaceLength));
        }

        $this->pushToken($type);
        $this->moveCursor(substr($match, $leadingWhitespaceLength));
    }

    private function getOperatorRegex(): string
    {
        $expressionParsers = [];
        foreach ($this->env->getExpressionParsers() as $expressionParser) {
            $expressionParsers = array_merge($expressionParsers, ExpressionParsers::getOperatorTokensFor($expressionParser));
        }

        $expressionParsers = array_combine($expressionParsers, array_map('strlen', $expressionParsers));
        arsort($expressionParsers);

        $regex = [];
        foreach ($expressionParsers as $expressionParser => $length) {
            // an operator that ends with a character must be followed by
            // a whitespace, a parenthesis, an opening map [ or sequence {
            $r = preg_quote($expressionParser, '/');
            if (ctype_alpha($expressionParser[$length - 1])) {
                $r .= '(?=[\s()\[{])';
            }

            // an operator that begins with a character must not have a dot or pipe before
            if (ctype_alpha($expressionParser[0])) {
                $r = '(?<![\.\|]\s|.[\.\|])'.$r;
            }

            // an operator with a space can be any amount of whitespaces
            $r = preg_replace('/\s+/', '\s+', $r);

            $regex[] = $r;
        }

        return '/'.implode('|', $regex).'/A';
    }

    private function pushState($state): void
    {
        $this->states[] = $this->state;
        $this->state = $state;
    }

    private function popState(): void
    {
        if (0 === \count($this->states)) {
            throw new \LogicException('Cannot pop state without a previous state.');
        }

        $this->state = array_pop($this->states);
    }

    private function checkBrackets(string $code): void
    {
        // opening bracket
        if (\in_array($code, $this->openingBrackets, true)) {
            $this->brackets[] = [$code, $this->lineno];
        } elseif (\in_array($code, $this->closingBrackets, true)) {
            // closing bracket
            if (!$this->brackets) {
                throw new SyntaxError(\sprintf('Unexpected "%s".', $code), $this->lineno, $this->source, columnno: $this->source->getColumn($this->cursor));
            }

            [$expect, $lineno] = array_pop($this->brackets);
            if ($code !== str_replace($this->openingBrackets, $this->closingBrackets, $expect)) {
                throw new SyntaxError(\sprintf('Unclosed "%s".', $expect), $lineno, $this->source);
            }
        }
    }
}
