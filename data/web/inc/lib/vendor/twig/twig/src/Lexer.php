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

    public const STATE_DATA = 0;
    public const STATE_BLOCK = 1;
    public const STATE_VAR = 2;
    public const STATE_STRING = 3;
    public const STATE_INTERPOLATION = 4;

    public const REGEX_NAME = '/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/A';
    public const REGEX_NUMBER = '/[0-9]+(?:\.[0-9]+)?([Ee][\+\-][0-9]+)?/A';
    public const REGEX_STRING = '/"([^#"\\\\]*(?:\\\\.[^#"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\'/As';
    public const REGEX_DQ_STRING_DELIM = '/"/A';
    public const REGEX_DQ_STRING_PART = '/[^#"\\\\]*(?:(?:\\\\.|#(?!\{))[^#"\\\\]*)*/As';
    public const PUNCTUATION = '()[]{}?:.,|';

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

    private function initialize()
    {
        if ($this->isInitialized) {
            return;
        }

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
                    preg_quote($this->options['tag_block'][1], '#').'\n?'. // %}\n?
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
                    preg_quote($this->options['tag_comment'][1], '#').'\n?'. // #}\n?
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
        $this->code = str_replace(["\r\n", "\r"], "\n", $source->getCode());
        $this->cursor = 0;
        $this->lineno = 1;
        $this->end = \strlen($this->code);
        $this->tokens = [];
        $this->state = self::STATE_DATA;
        $this->states = [];
        $this->brackets = [];
        $this->position = -1;

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

        if (!empty($this->brackets)) {
            [$expect, $lineno] = array_pop($this->brackets);
            throw new SyntaxError(\sprintf('Unclosed "%s".', $expect), $lineno, $this->source);
        }

        return new TokenStream($this->tokens, $this->source);
    }

    private function lexData(): void
    {
        // if no matches are left we return the rest of the template as simple text token
        if ($this->position == \count($this->positions[0]) - 1) {
            $this->pushToken(Token::TEXT_TYPE, substr($this->code, $this->cursor));
            $this->cursor = $this->end;

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
        $this->pushToken(Token::TEXT_TYPE, $text);
        $this->moveCursor($textContent.$position[0]);

        switch ($this->positions[1][$this->position][0]) {
            case $this->options['tag_comment'][0]:
                $this->lexComment();
                break;

            case $this->options['tag_block'][0]:
                // raw data?
                if (preg_match($this->regexes['lex_block_raw'], $this->code, $match, 0, $this->cursor)) {
                    $this->moveCursor($match[0]);
                    $this->lexRawData();
                // {% line \d+ %}
                } elseif (preg_match($this->regexes['lex_block_line'], $this->code, $match, 0, $this->cursor)) {
                    $this->moveCursor($match[0]);
                    $this->lineno = (int) $match[1];
                } else {
                    $this->pushToken(Token::BLOCK_START_TYPE);
                    $this->pushState(self::STATE_BLOCK);
                    $this->currentVarBlockLine = $this->lineno;
                }
                break;

            case $this->options['tag_variable'][0]:
                $this->pushToken(Token::VAR_START_TYPE);
                $this->pushState(self::STATE_VAR);
                $this->currentVarBlockLine = $this->lineno;
                break;
        }
    }

    private function lexBlock(): void
    {
        if (empty($this->brackets) && preg_match($this->regexes['lex_block'], $this->code, $match, 0, $this->cursor)) {
            $this->pushToken(Token::BLOCK_END_TYPE);
            $this->moveCursor($match[0]);
            $this->popState();
        } else {
            $this->lexExpression();
        }
    }

    private function lexVar(): void
    {
        if (empty($this->brackets) && preg_match($this->regexes['lex_var'], $this->code, $match, 0, $this->cursor)) {
            $this->pushToken(Token::VAR_END_TYPE);
            $this->moveCursor($match[0]);
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

        // spread operator
        if ('.' === $this->code[$this->cursor] && ($this->cursor + 2 < $this->end) && '.' === $this->code[$this->cursor + 1] && '.' === $this->code[$this->cursor + 2]) {
            $this->pushToken(Token::SPREAD_TYPE, '...');
            $this->moveCursor('...');
        }
        // arrow function
        elseif ('=' === $this->code[$this->cursor] && ($this->cursor + 1 < $this->end) && '>' === $this->code[$this->cursor + 1]) {
            $this->pushToken(Token::ARROW_TYPE, '=>');
            $this->moveCursor('=>');
        }
        // operators
        elseif (preg_match($this->regexes['operator'], $this->code, $match, 0, $this->cursor)) {
            $this->pushToken(Token::OPERATOR_TYPE, preg_replace('/\s+/', ' ', $match[0]));
            $this->moveCursor($match[0]);
        }
        // names
        elseif (preg_match(self::REGEX_NAME, $this->code, $match, 0, $this->cursor)) {
            $this->pushToken(Token::NAME_TYPE, $match[0]);
            $this->moveCursor($match[0]);
        }
        // numbers
        elseif (preg_match(self::REGEX_NUMBER, $this->code, $match, 0, $this->cursor)) {
            $number = (float) $match[0];  // floats
            if (ctype_digit($match[0]) && $number <= \PHP_INT_MAX) {
                $number = (int) $match[0]; // integers lower than the maximum
            }
            $this->pushToken(Token::NUMBER_TYPE, $number);
            $this->moveCursor($match[0]);
        }
        // punctuation
        elseif (str_contains(self::PUNCTUATION, $this->code[$this->cursor])) {
            // opening bracket
            if (str_contains('([{', $this->code[$this->cursor])) {
                $this->brackets[] = [$this->code[$this->cursor], $this->lineno];
            }
            // closing bracket
            elseif (str_contains(')]}', $this->code[$this->cursor])) {
                if (empty($this->brackets)) {
                    throw new SyntaxError(\sprintf('Unexpected "%s".', $this->code[$this->cursor]), $this->lineno, $this->source);
                }

                [$expect, $lineno] = array_pop($this->brackets);
                if ($this->code[$this->cursor] != strtr($expect, '([{', ')]}')) {
                    throw new SyntaxError(\sprintf('Unclosed "%s".', $expect), $lineno, $this->source);
                }
            }

            $this->pushToken(Token::PUNCTUATION_TYPE, $this->code[$this->cursor]);
            ++$this->cursor;
        }
        // strings
        elseif (preg_match(self::REGEX_STRING, $this->code, $match, 0, $this->cursor)) {
            $this->pushToken(Token::STRING_TYPE, $this->stripcslashes(substr($match[0], 1, -1), substr($match[0], 0, 1)));
            $this->moveCursor($match[0]);
        }
        // opening double quoted string
        elseif (preg_match(self::REGEX_DQ_STRING_DELIM, $this->code, $match, 0, $this->cursor)) {
            $this->brackets[] = ['"', $this->lineno];
            $this->pushState(self::STATE_STRING);
            $this->moveCursor($match[0]);
        }
        // unlexable
        else {
            throw new SyntaxError(\sprintf('Unexpected character "%s".', $this->code[$this->cursor]), $this->lineno, $this->source);
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
                    trigger_deprecation('twig/twig', '3.12', 'Character "%s" at position %d should not be escaped; the "\" character is ignored in Twig v3 but will not be in v4. Please remove the extra "\" character.', $nextChar, $i + 1);
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
                $result .= \chr(octdec($octal));
            } else {
                trigger_deprecation('twig/twig', '3.12', 'Character "%s" at position %d should not be escaped; the "\" character is ignored in Twig v3 but will not be in v4. Please remove the extra "\" character.', $nextChar, $i + 1);
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

        $this->pushToken(Token::TEXT_TYPE, $text);
    }

    private function lexComment(): void
    {
        if (!preg_match($this->regexes['lex_comment'], $this->code, $match, \PREG_OFFSET_CAPTURE, $this->cursor)) {
            throw new SyntaxError('Unclosed comment.', $this->lineno, $this->source);
        }

        $this->moveCursor(substr($this->code, $this->cursor, $match[0][1] - $this->cursor).$match[0][0]);
    }

    private function lexString(): void
    {
        if (preg_match($this->regexes['interpolation_start'], $this->code, $match, 0, $this->cursor)) {
            $this->brackets[] = [$this->options['interpolation'][0], $this->lineno];
            $this->pushToken(Token::INTERPOLATION_START_TYPE);
            $this->moveCursor($match[0]);
            $this->pushState(self::STATE_INTERPOLATION);
        } elseif (preg_match(self::REGEX_DQ_STRING_PART, $this->code, $match, 0, $this->cursor) && '' !== $match[0]) {
            $this->pushToken(Token::STRING_TYPE, $this->stripcslashes($match[0], '"'));
            $this->moveCursor($match[0]);
        } elseif (preg_match(self::REGEX_DQ_STRING_DELIM, $this->code, $match, 0, $this->cursor)) {
            [$expect, $lineno] = array_pop($this->brackets);
            if ('"' != $this->code[$this->cursor]) {
                throw new SyntaxError(\sprintf('Unclosed "%s".', $expect), $lineno, $this->source);
            }

            $this->popState();
            ++$this->cursor;
        } else {
            // unlexable
            throw new SyntaxError(\sprintf('Unexpected character "%s".', $this->code[$this->cursor]), $this->lineno, $this->source);
        }
    }

    private function lexInterpolation(): void
    {
        $bracket = end($this->brackets);
        if ($this->options['interpolation'][0] === $bracket[0] && preg_match($this->regexes['interpolation_end'], $this->code, $match, 0, $this->cursor)) {
            array_pop($this->brackets);
            $this->pushToken(Token::INTERPOLATION_END_TYPE);
            $this->moveCursor($match[0]);
            $this->popState();
        } else {
            $this->lexExpression();
        }
    }

    private function pushToken($type, $value = ''): void
    {
        // do not push empty text tokens
        if (Token::TEXT_TYPE === $type && '' === $value) {
            return;
        }

        $this->tokens[] = new Token($type, $value, $this->lineno);
    }

    private function moveCursor($text): void
    {
        $this->cursor += \strlen($text);
        $this->lineno += substr_count($text, "\n");
    }

    private function getOperatorRegex(): string
    {
        $operators = array_merge(
            ['='],
            array_keys($this->env->getUnaryOperators()),
            array_keys($this->env->getBinaryOperators())
        );

        $operators = array_combine($operators, array_map('strlen', $operators));
        arsort($operators);

        $regex = [];
        foreach ($operators as $operator => $length) {
            // an operator that ends with a character must be followed by
            // a whitespace, a parenthesis, an opening map [ or sequence {
            $r = preg_quote($operator, '/');
            if (ctype_alpha($operator[$length - 1])) {
                $r .= '(?=[\s()\[{])';
            }

            // an operator that begins with a character must not have a dot or pipe before
            if (ctype_alpha($operator[0])) {
                $r = '(?<![\.\|])'.$r;
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
}
