<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Extension;

use Twig\DeprecatedCallableInfo;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\ExpressionParser\Infix\ArrowExpressionParser;
use Twig\ExpressionParser\Infix\AssignmentExpressionParser;
use Twig\ExpressionParser\Infix\BinaryOperatorExpressionParser;
use Twig\ExpressionParser\Infix\ConditionalTernaryExpressionParser;
use Twig\ExpressionParser\Infix\DotExpressionParser;
use Twig\ExpressionParser\Infix\FilterExpressionParser;
use Twig\ExpressionParser\Infix\FunctionExpressionParser;
use Twig\ExpressionParser\Infix\IsExpressionParser;
use Twig\ExpressionParser\Infix\IsNotExpressionParser;
use Twig\ExpressionParser\Infix\SquareBracketExpressionParser;
use Twig\ExpressionParser\InfixAssociativity;
use Twig\ExpressionParser\PrecedenceChange;
use Twig\ExpressionParser\Prefix\GroupingExpressionParser;
use Twig\ExpressionParser\Prefix\LiteralExpressionParser;
use Twig\ExpressionParser\Prefix\UnaryOperatorExpressionParser;
use Twig\MacroNamespace;
use Twig\Markup;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\Binary\AddBinary;
use Twig\Node\Expression\Binary\AndBinary;
use Twig\Node\Expression\Binary\BitwiseAndBinary;
use Twig\Node\Expression\Binary\BitwiseOrBinary;
use Twig\Node\Expression\Binary\BitwiseXorBinary;
use Twig\Node\Expression\Binary\ConcatBinary;
use Twig\Node\Expression\Binary\DivBinary;
use Twig\Node\Expression\Binary\ElvisBinary;
use Twig\Node\Expression\Binary\EndsWithBinary;
use Twig\Node\Expression\Binary\EqualBinary;
use Twig\Node\Expression\Binary\FloorDivBinary;
use Twig\Node\Expression\Binary\GreaterBinary;
use Twig\Node\Expression\Binary\GreaterEqualBinary;
use Twig\Node\Expression\Binary\HasEveryBinary;
use Twig\Node\Expression\Binary\HasSomeBinary;
use Twig\Node\Expression\Binary\InBinary;
use Twig\Node\Expression\Binary\LessBinary;
use Twig\Node\Expression\Binary\LessEqualBinary;
use Twig\Node\Expression\Binary\MatchesBinary;
use Twig\Node\Expression\Binary\ModBinary;
use Twig\Node\Expression\Binary\MulBinary;
use Twig\Node\Expression\Binary\NotEqualBinary;
use Twig\Node\Expression\Binary\NotInBinary;
use Twig\Node\Expression\Binary\NotSameAsBinary;
use Twig\Node\Expression\Binary\NullCoalesceBinary;
use Twig\Node\Expression\Binary\OrBinary;
use Twig\Node\Expression\Binary\PowerBinary;
use Twig\Node\Expression\Binary\RangeBinary;
use Twig\Node\Expression\Binary\SameAsBinary;
use Twig\Node\Expression\Binary\SpaceshipBinary;
use Twig\Node\Expression\Binary\StartsWithBinary;
use Twig\Node\Expression\Binary\SubBinary;
use Twig\Node\Expression\Binary\XorBinary;
use Twig\Node\Expression\BlockReferenceExpression;
use Twig\Node\Expression\Filter\DefaultFilter;
use Twig\Node\Expression\FunctionNode\EnumCasesFunction;
use Twig\Node\Expression\FunctionNode\EnumFunction;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Expression\ParentExpression;
use Twig\Node\Expression\Test\ConstantTest;
use Twig\Node\Expression\Test\DefinedTest;
use Twig\Node\Expression\Test\DivisiblebyTest;
use Twig\Node\Expression\Test\EvenTest;
use Twig\Node\Expression\Test\NullTest;
use Twig\Node\Expression\Test\OddTest;
use Twig\Node\Expression\Test\SameasTest;
use Twig\Node\Expression\Test\TrueTest;
use Twig\Node\Expression\Unary\NegUnary;
use Twig\Node\Expression\Unary\NotUnary;
use Twig\Node\Expression\Unary\PosUnary;
use Twig\Node\Expression\Unary\SpreadUnary;
use Twig\Node\Node;
use Twig\NodeVisitor\CorrectnessNodeVisitor;
use Twig\Parser;
use Twig\Sandbox\SecurityNotAllowedMethodError;
use Twig\Sandbox\SecurityNotAllowedPropertyError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;
use Twig\TokenParser\ApplyTokenParser;
use Twig\TokenParser\BlockTokenParser;
use Twig\TokenParser\DeprecatedTokenParser;
use Twig\TokenParser\DoTokenParser;
use Twig\TokenParser\EmbedTokenParser;
use Twig\TokenParser\ExtendsTokenParser;
use Twig\TokenParser\FlushTokenParser;
use Twig\TokenParser\ForTokenParser;
use Twig\TokenParser\FromTokenParser;
use Twig\TokenParser\GuardTokenParser;
use Twig\TokenParser\IfTokenParser;
use Twig\TokenParser\ImportTokenParser;
use Twig\TokenParser\IncludeTokenParser;
use Twig\TokenParser\MacroTokenParser;
use Twig\TokenParser\SetTokenParser;
use Twig\TokenParser\TypesTokenParser;
use Twig\TokenParser\UseTokenParser;
use Twig\TokenParser\WithTokenParser;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;
use Twig\Util\CallableArgumentsExtractor;

final class CoreExtension extends AbstractExtension
{
    public const ARRAY_LIKE_CLASSES = [
        'ArrayIterator',
        'ArrayObject',
        'CachingIterator',
        'RecursiveArrayIterator',
        'RecursiveCachingIterator',
        'SplDoublyLinkedList',
        'SplFixedArray',
        'SplObjectStorage',
        'SplQueue',
        'SplStack',
        'WeakMap',
    ];

    private const DEFAULT_TRIM_CHARS = " \t\n\r\0\x0B";

    private $dateFormats = ['F j, Y H:i', '%d days'];
    private $numberFormat = [0, '.', ','];
    private $timezone;

    /**
     * Sets the default format to be used by the date filter.
     *
     * @param string|null $format             The default date format string
     * @param string|null $dateIntervalFormat The default date interval format string
     */
    public function setDateFormat($format = null, $dateIntervalFormat = null): void
    {
        if (null !== $format) {
            $this->dateFormats[0] = $format;
        }

        if (null !== $dateIntervalFormat) {
            $this->dateFormats[1] = $dateIntervalFormat;
        }
    }

    /**
     * Gets the default format to be used by the date filter.
     *
     * @return array The default date format string and the default date interval format string
     */
    public function getDateFormat()
    {
        return $this->dateFormats;
    }

    /**
     * Sets the default timezone to be used by the date filter.
     *
     * @param \DateTimeZone|string $timezone The default timezone string or a \DateTimeZone object
     */
    public function setTimezone($timezone): void
    {
        $this->timezone = $timezone instanceof \DateTimeZone ? $timezone : new \DateTimeZone($timezone);
    }

    /**
     * Gets the default timezone to be used by the date filter.
     *
     * @return \DateTimeZone The default timezone currently in use
     */
    public function getTimezone()
    {
        if (null === $this->timezone) {
            $this->timezone = new \DateTimeZone(date_default_timezone_get());
        }

        return $this->timezone;
    }

    /**
     * Sets the default format to be used by the number_format filter.
     *
     * @param int    $decimal      the number of decimal places to use
     * @param string $decimalPoint the character(s) to use for the decimal point
     * @param string $thousandSep  the character(s) to use for the thousands separator
     */
    public function setNumberFormat($decimal, $decimalPoint, $thousandSep): void
    {
        $this->numberFormat = [$decimal, $decimalPoint, $thousandSep];
    }

    /**
     * Get the default format used by the number_format filter.
     *
     * @return array The arguments for number_format()
     */
    public function getNumberFormat()
    {
        return $this->numberFormat;
    }

    public function getTokenParsers(): array
    {
        return [
            new ApplyTokenParser(),
            new ForTokenParser(),
            new IfTokenParser(),
            new ExtendsTokenParser(),
            new IncludeTokenParser(),
            new BlockTokenParser(),
            new UseTokenParser(),
            new MacroTokenParser(),
            new ImportTokenParser(),
            new FromTokenParser(),
            new SetTokenParser(),
            new TypesTokenParser(),
            new FlushTokenParser(),
            new DoTokenParser(),
            new EmbedTokenParser(),
            new WithTokenParser(),
            new DeprecatedTokenParser(),
            new GuardTokenParser(),
        ];
    }

    public function getFilters(): array
    {
        return [
            // formatting filters
            new TwigFilter('date', [$this, 'formatDate']),
            new TwigFilter('date_modify', [$this, 'modifyDate']),
            new TwigFilter('format', [self::class, 'sprintf']),
            new TwigFilter('replace', [self::class, 'replace']),
            new TwigFilter('number_format', [$this, 'formatNumber']),
            new TwigFilter('abs', 'abs'),
            new TwigFilter('round', [self::class, 'round']),

            // encoding
            new TwigFilter('url_encode', [self::class, 'urlencode']),
            new TwigFilter('json_encode', 'json_encode'),
            new TwigFilter('convert_encoding', [self::class, 'convertEncoding']),

            // string filters
            new TwigFilter('title', [self::class, 'titleCase'], ['needs_charset' => true]),
            new TwigFilter('capitalize', [self::class, 'capitalize'], ['needs_charset' => true]),
            new TwigFilter('upper', [self::class, 'upper'], ['needs_charset' => true]),
            new TwigFilter('lower', [self::class, 'lower'], ['needs_charset' => true]),
            new TwigFilter('striptags', [self::class, 'striptags']),
            new TwigFilter('trim', [self::class, 'trim']),
            new TwigFilter('nl2br', [self::class, 'nl2br'], ['pre_escape' => 'html', 'is_safe' => ['html']]),
            new TwigFilter('spaceless', [self::class, 'spaceless'], ['pre_escape' => 'html', 'is_safe' => ['html'], 'deprecation_info' => new DeprecatedCallableInfo('twig/twig', '3.12')]),

            // array helpers
            new TwigFilter('join', [self::class, 'join']),
            new TwigFilter('split', [self::class, 'split'], ['needs_charset' => true]),
            new TwigFilter('sort', [self::class, 'sort'], ['needs_environment' => true, 'needs_is_sandboxed' => true]),
            new TwigFilter('merge', [self::class, 'merge']),
            new TwigFilter('batch', [self::class, 'batch']),
            new TwigFilter('column', [self::class, 'column'], ['needs_environment' => true, 'needs_is_sandboxed' => true]),
            new TwigFilter('filter', [self::class, 'filter'], ['needs_environment' => true, 'needs_is_sandboxed' => true]),
            new TwigFilter('map', [self::class, 'map'], ['needs_environment' => true, 'needs_is_sandboxed' => true]),
            new TwigFilter('reduce', [self::class, 'reduce'], ['needs_environment' => true, 'needs_is_sandboxed' => true]),
            new TwigFilter('find', [self::class, 'find'], ['needs_environment' => true, 'needs_is_sandboxed' => true]),

            // string/array filters
            new TwigFilter('reverse', [self::class, 'reverse'], ['needs_charset' => true]),
            new TwigFilter('shuffle', [self::class, 'shuffle'], ['needs_charset' => true]),
            new TwigFilter('length', [self::class, 'length'], ['needs_charset' => true]),
            new TwigFilter('slice', [self::class, 'slice'], ['needs_charset' => true]),
            new TwigFilter('first', [self::class, 'first'], ['needs_charset' => true]),
            new TwigFilter('last', [self::class, 'last'], ['needs_charset' => true]),

            // iteration and runtime
            new TwigFilter('default', [self::class, 'default'], ['node_class' => DefaultFilter::class]),
            new TwigFilter('keys', [self::class, 'keys']),
            new TwigFilter('invoke', [self::class, 'invoke']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('parent', null, ['parser_callable' => [self::class, 'parseParentFunction']]),
            new TwigFunction('block', null, ['parser_callable' => [self::class, 'parseBlockFunction']]),
            new TwigFunction('attribute', null, ['parser_callable' => [self::class, 'parseAttributeFunction']]),
            new TwigFunction('max', 'max'),
            new TwigFunction('min', 'min'),
            new TwigFunction('range', 'range'),
            new TwigFunction('constant', [self::class, 'constant']),
            new TwigFunction('cycle', [self::class, 'cycle']),
            new TwigFunction('random', [self::class, 'random'], ['needs_charset' => true]),
            new TwigFunction('date', [$this, 'convertDate']),
            new TwigFunction('include', [self::class, 'include'], ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['all']]),
            new TwigFunction('include_only', [self::class, 'includeOnly'], ['needs_environment' => true, 'is_safe' => ['all']]),
            new TwigFunction('source', [self::class, 'source'], ['needs_environment' => true, 'is_safe' => ['all']]),
            new TwigFunction('enum_cases', [self::class, 'enumCases'], ['node_class' => EnumCasesFunction::class]),
            new TwigFunction('enum', [self::class, 'enum'], ['node_class' => EnumFunction::class]),
        ];
    }

    public function getTests(): array
    {
        return [
            new TwigTest('even', null, ['node_class' => EvenTest::class, 'always_allowed_in_sandbox' => true]),
            new TwigTest('odd', null, ['node_class' => OddTest::class, 'always_allowed_in_sandbox' => true]),
            new TwigTest('defined', null, ['node_class' => DefinedTest::class, 'always_allowed_in_sandbox' => true]),
            new TwigTest('same as', null, ['node_class' => SameasTest::class, 'one_mandatory_argument' => true, 'always_allowed_in_sandbox' => true]),
            new TwigTest('none', null, ['node_class' => NullTest::class, 'always_allowed_in_sandbox' => true]),
            new TwigTest('null', null, ['node_class' => NullTest::class, 'always_allowed_in_sandbox' => true]),
            new TwigTest('divisible by', null, ['node_class' => DivisiblebyTest::class, 'one_mandatory_argument' => true, 'always_allowed_in_sandbox' => true]),
            new TwigTest('constant', null, ['node_class' => ConstantTest::class]),
            new TwigTest('empty', [self::class, 'testEmpty'], ['always_allowed_in_sandbox' => true]),
            new TwigTest('iterable', 'is_iterable', ['always_allowed_in_sandbox' => true]),
            new TwigTest('sequence', [self::class, 'testSequence'], ['always_allowed_in_sandbox' => true]),
            new TwigTest('mapping', [self::class, 'testMapping'], ['always_allowed_in_sandbox' => true]),
            new TwigTest('true', null, ['node_class' => TrueTest::class, 'always_allowed_in_sandbox' => true]),
        ];
    }

    public function getNodeVisitors(): array
    {
        return [
            new CorrectnessNodeVisitor(),
        ];
    }

    public function getExpressionParsers(): array
    {
        return [
            // unary operators
            new UnaryOperatorExpressionParser(NotUnary::class, 'not', 50, new PrecedenceChange('twig/twig', '3.15', 70)),
            new UnaryOperatorExpressionParser(SpreadUnary::class, '...', 512, description: 'Spread operator', operandPrecedence: 0),
            new UnaryOperatorExpressionParser(NegUnary::class, '-', 500),
            new UnaryOperatorExpressionParser(PosUnary::class, '+', 500),

            // binary operators
            new BinaryOperatorExpressionParser(ElvisBinary::class, '?:', 5, InfixAssociativity::Right, description: 'Elvis operator (a ?: b)', aliases: ['? :']),
            new BinaryOperatorExpressionParser(NullCoalesceBinary::class, '??', 300, InfixAssociativity::Right, new PrecedenceChange('twig/twig', '3.15', 5), description: 'Null coalescing operator (a ?? b)'),
            new BinaryOperatorExpressionParser(OrBinary::class, 'or', 10),
            new BinaryOperatorExpressionParser(XorBinary::class, 'xor', 12),
            new BinaryOperatorExpressionParser(AndBinary::class, 'and', 15),
            new BinaryOperatorExpressionParser(BitwiseOrBinary::class, 'b-or', 16),
            new BinaryOperatorExpressionParser(BitwiseXorBinary::class, 'b-xor', 17),
            new BinaryOperatorExpressionParser(BitwiseAndBinary::class, 'b-and', 18),
            new BinaryOperatorExpressionParser(EqualBinary::class, '==', 20),
            new BinaryOperatorExpressionParser(NotEqualBinary::class, '!=', 20),
            new BinaryOperatorExpressionParser(SpaceshipBinary::class, '<=>', 20),
            new BinaryOperatorExpressionParser(LessBinary::class, '<', 20),
            new BinaryOperatorExpressionParser(GreaterBinary::class, '>', 20),
            new BinaryOperatorExpressionParser(GreaterEqualBinary::class, '>=', 20),
            new BinaryOperatorExpressionParser(LessEqualBinary::class, '<=', 20),
            new BinaryOperatorExpressionParser(NotInBinary::class, 'not in', 20),
            new BinaryOperatorExpressionParser(InBinary::class, 'in', 20),
            new BinaryOperatorExpressionParser(MatchesBinary::class, 'matches', 20),
            new BinaryOperatorExpressionParser(StartsWithBinary::class, 'starts with', 20),
            new BinaryOperatorExpressionParser(EndsWithBinary::class, 'ends with', 20),
            new BinaryOperatorExpressionParser(HasSomeBinary::class, 'has some', 20),
            new BinaryOperatorExpressionParser(HasEveryBinary::class, 'has every', 20),
            new BinaryOperatorExpressionParser(SameAsBinary::class, '===', 20),
            new BinaryOperatorExpressionParser(NotSameAsBinary::class, '!==', 20),
            new BinaryOperatorExpressionParser(RangeBinary::class, '..', 25),
            new BinaryOperatorExpressionParser(AddBinary::class, '+', 30),
            new BinaryOperatorExpressionParser(SubBinary::class, '-', 30),
            new BinaryOperatorExpressionParser(ConcatBinary::class, '~', 40, precedenceChange: new PrecedenceChange('twig/twig', '3.15', 27)),
            new BinaryOperatorExpressionParser(MulBinary::class, '*', 60),
            new BinaryOperatorExpressionParser(DivBinary::class, '/', 60),
            new BinaryOperatorExpressionParser(FloorDivBinary::class, '//', 60, description: 'Floor division'),
            new BinaryOperatorExpressionParser(ModBinary::class, '%', 60),
            new BinaryOperatorExpressionParser(PowerBinary::class, '**', 200, InfixAssociativity::Right, description: 'Exponentiation operator'),

            // ternary operator
            new ConditionalTernaryExpressionParser(),

            // assignment operator
            new AssignmentExpressionParser('='),

            // Twig callables
            new IsExpressionParser(),
            new IsNotExpressionParser(),
            new FilterExpressionParser(),
            new FunctionExpressionParser(),

            // get attribute operators
            new DotExpressionParser(),
            new SquareBracketExpressionParser(),

            // group expression
            new GroupingExpressionParser(),

            // arrow function
            new ArrowExpressionParser(),

            // all literals
            new LiteralExpressionParser(),
        ];
    }

    /**
     * Cycles over a sequence.
     *
     * @param array|\ArrayAccess $values   A non-empty sequence of values
     * @param int<0, max>        $position The position of the value to return in the cycle
     *
     * @return mixed The value at the given position in the sequence, wrapping around as needed
     *
     * @internal
     */
    public static function cycle($values, $position): mixed
    {
        if (!\is_array($values)) {
            if (!$values instanceof \ArrayAccess) {
                throw new RuntimeError('The "cycle" function expects an array or "ArrayAccess" as first argument.');
            }

            if (!is_countable($values)) {
                // To be uncommented in 4.0
                // throw new RuntimeError('The "cycle" function expects a countable sequence as first argument.');

                trigger_deprecation('twig/twig', '3.12', 'Passing a non-countable sequence of values to "%s()" is deprecated.', __METHOD__);

                $values = self::toArray($values, false);
            }
        }

        if (!$count = \count($values)) {
            throw new RuntimeError('The "cycle" function expects a non-empty sequence.');
        }

        return $values[$position % $count];
    }

    /**
     * Returns a random value depending on the supplied parameter type:
     * - a random item from a \Traversable or array
     * - a random character from a string
     * - a random integer between 0 and the integer parameter.
     *
     * @param \Traversable|array|int|float|string $values The values to pick a random item from
     * @param int|null                            $max    Maximum value used when $values is an int
     *
     * @return mixed A random value from the given sequence
     *
     * @throws RuntimeError when $values is an empty array (does not apply to an empty string which is returned as is)
     *
     * @internal
     */
    public static function random(string $charset, $values = null, $max = null)
    {
        if (null === $values) {
            return null === $max ? mt_rand() : mt_rand(0, (int) $max);
        }

        if (\is_int($values) || \is_float($values)) {
            if (null === $max) {
                if ($values < 0) {
                    $max = 0;
                    $min = $values;
                } else {
                    $max = $values;
                    $min = 0;
                }
            } else {
                $min = $values;
            }

            return mt_rand((int) $min, (int) $max);
        }

        if (\is_string($values)) {
            if ('' === $values) {
                return '';
            }

            if ('UTF-8' !== $charset) {
                $values = self::convertEncoding($values, 'UTF-8', $charset);
            }

            $values = self::splitIntoCharacters($values, 'random');

            if ('UTF-8' !== $charset) {
                foreach ($values as $i => $value) {
                    $values[$i] = self::convertEncoding($value, $charset, 'UTF-8');
                }
            }
        }

        if (!is_iterable($values)) {
            return $values;
        }

        $values = self::toArray($values);

        if (0 === \count($values)) {
            throw new RuntimeError('The "random" function cannot pick from an empty sequence or mapping.');
        }

        return $values[array_rand($values, 1)];
    }

    /**
     * Formats a date.
     *
     *   {{ post.published_at|date("m/d/Y") }}
     *
     * @param \DateTimeInterface|\DateInterval|string|int|null $date     A date, a timestamp or null to use the current time
     * @param string|null                                      $format   The target format, null to use the default
     * @param \DateTimeZone|string|false|null                  $timezone The target timezone, null to use the default, false to leave unchanged
     */
    public function formatDate($date, $format = null, $timezone = null): string
    {
        if (null === $format) {
            $formats = $this->getDateFormat();
            $format = $date instanceof \DateInterval ? $formats[1] : $formats[0];
        }

        if ($date instanceof \DateInterval) {
            return $date->format($format);
        }

        return $this->convertDate($date, $timezone)->format($format);
    }

    /**
     * Returns a new date object modified.
     *
     *   {{ post.published_at|date_modify("-1day")|date("m/d/Y") }}
     *
     * @param \DateTimeInterface|string|int|null $date     A date, a timestamp or null to use the current time
     * @param string                             $modifier A modifier string
     *
     * @return \DateTime|\DateTimeImmutable
     *
     * @internal
     */
    public function modifyDate($date, $modifier)
    {
        return $this->convertDate($date, false)->modify($modifier);
    }

    /**
     * Returns a formatted string.
     *
     * @param string|null $format
     *
     * @internal
     */
    public static function sprintf($format, ...$values): string
    {
        return \sprintf($format ?? '', ...$values);
    }

    /**
     * @internal
     */
    public static function dateConverter(Environment $env, $date, $format = null, $timezone = null): string
    {
        return $env->getExtension(self::class)->formatDate($date, $format, $timezone);
    }

    /**
     * Converts an input to a \DateTime instance.
     *
     *    {% if date(user.created_at) < date('+2days') %}
     *      {# do something #}
     *    {% endif %}
     *
     * @param \DateTimeInterface|string|int|null $date     A date, a timestamp or null to use the current time
     * @param \DateTimeZone|string|false|null    $timezone The target timezone, null to use the default, false to leave unchanged
     *
     * @return \DateTime|\DateTimeImmutable
     */
    public function convertDate($date = null, $timezone = null)
    {
        // determine the timezone
        if (false !== $timezone) {
            if (null === $timezone) {
                $timezone = $this->getTimezone();
            } elseif (!$timezone instanceof \DateTimeZone) {
                $timezone = new \DateTimeZone($timezone);
            }
        }

        // immutable dates
        if ($date instanceof \DateTimeImmutable) {
            return false !== $timezone ? $date->setTimezone($timezone) : $date;
        }

        if ($date instanceof \DateTime) {
            $date = clone $date;
            if (false !== $timezone) {
                $date->setTimezone($timezone);
            }

            return $date;
        }

        if (null === $date || 'now' === $date) {
            if (null === $date) {
                $date = 'now';
            }

            return new \DateTime($date, false !== $timezone ? $timezone : $this->getTimezone());
        }

        $asString = (string) $date;
        if (ctype_digit($asString) || ('' !== $asString && '-' === $asString[0] && ctype_digit(substr($asString, 1)))) {
            $date = new \DateTime('@'.$date);
        } else {
            $date = new \DateTime($date);
        }

        if (false !== $timezone) {
            $date->setTimezone($timezone);
        }

        return $date;
    }

    /**
     * Replaces strings within a string.
     *
     * @param string|null        $str  String to replace in
     * @param array|\Traversable $from Replace values
     *
     * @internal
     */
    public static function replace($str, $from): string
    {
        if (!is_iterable($from)) {
            throw new RuntimeError(\sprintf('The "replace" filter expects a sequence or a mapping, got "%s".', get_debug_type($from)));
        }

        return strtr($str ?? '', self::toArray($from));
    }

    /**
     * Rounds a number.
     *
     * @param int|float|string|null   $value     The value to round
     * @param int|float               $precision The rounding precision
     * @param 'common'|'ceil'|'floor' $method    The method to use for rounding
     *
     * @return float The rounded number
     *
     * @internal
     */
    public static function round($value, $precision = 0, $method = 'common')
    {
        $value = (float) $value;

        if ('common' === $method) {
            return round($value, $precision);
        }

        if ('ceil' !== $method && 'floor' !== $method) {
            throw new RuntimeError('The "round" filter only supports the "common", "ceil", and "floor" methods.');
        }

        return $method($value * 10 ** $precision) / 10 ** $precision;
    }

    /**
     * Formats a number.
     *
     * All of the formatting options can be left null, in that case the defaults will
     * be used. Supplying any of the parameters will override the defaults set in the
     * environment object.
     *
     * @param mixed       $number       A float/int/string of the number to format
     * @param int|null    $decimal      the number of decimal points to display
     * @param string|null $decimalPoint the character(s) to use for the decimal point
     * @param string|null $thousandSep  the character(s) to use for the thousands separator
     */
    public function formatNumber($number, $decimal = null, $decimalPoint = null, $thousandSep = null): string
    {
        $defaults = $this->getNumberFormat();
        if (null === $decimal) {
            $decimal = $defaults[0];
        }

        if (null === $decimalPoint) {
            $decimalPoint = $defaults[1];
        }

        if (null === $thousandSep) {
            $thousandSep = $defaults[2];
        }

        return number_format((float) $number, $decimal, $decimalPoint, $thousandSep);
    }

    /**
     * URL encodes (RFC 3986) a string as a path segment or an array as a query string.
     *
     * @param string|array|null $url A URL or an array of query parameters
     *
     * @internal
     */
    public static function urlencode($url): string
    {
        if (\is_array($url)) {
            return http_build_query($url, '', '&', \PHP_QUERY_RFC3986);
        }

        return rawurlencode($url ?? '');
    }

    /**
     * Merges any number of arrays or Traversable objects.
     *
     *  {% set items = { 'apple': 'fruit', 'orange': 'fruit' } %}
     *
     *  {% set items = items|merge({ 'peugeot': 'car' }, { 'banana': 'fruit' }) %}
     *
     *  {# items now contains { 'apple': 'fruit', 'orange': 'fruit', 'peugeot': 'car', 'banana': 'fruit' } #}
     *
     * @param array|\Traversable ...$arrays Any number of arrays or Traversable objects to merge
     *
     * @internal
     */
    public static function merge(...$arrays): array
    {
        $result = [];

        foreach ($arrays as $argNumber => $array) {
            if (!is_iterable($array)) {
                throw new RuntimeError(\sprintf('The "merge" filter expects a sequence or a mapping, got "%s" for argument %d.', get_debug_type($array), $argNumber + 1));
            }

            $result = array_merge($result, self::toArray($array));
        }

        return $result;
    }

    /**
     * Slices a variable.
     *
     * @param mixed $item         A variable
     * @param int   $start        Start of the slice
     * @param int   $length       Size of the slice
     * @param bool  $preserveKeys Whether to preserve key or not (when the input is an array)
     *
     * @return mixed The sliced variable
     *
     * @internal
     */
    public static function slice(string $charset, $item, $start, $length = null, $preserveKeys = false)
    {
        if ($item instanceof \Traversable) {
            while ($item instanceof \IteratorAggregate) {
                $item = $item->getIterator();
            }

            if ($start >= 0 && $length >= 0 && $item instanceof \Iterator) {
                try {
                    return iterator_to_array(new \LimitIterator($item, $start, $length ?? -1), $preserveKeys);
                } catch (\OutOfBoundsException $e) {
                    return [];
                }
            }

            $item = iterator_to_array($item, $preserveKeys);
        }

        if (\is_array($item)) {
            return \array_slice($item, $start, $length, $preserveKeys);
        }

        return mb_substr((string) $item, $start, $length, $charset);
    }

    /**
     * Returns the first element of the item.
     *
     * @param mixed $item A variable
     *
     * @return mixed The first element of the item
     *
     * @internal
     */
    public static function first(string $charset, $item)
    {
        $elements = self::slice($charset, $item, 0, 1, false);

        return \is_string($elements) ? $elements : current($elements);
    }

    /**
     * Returns the last element of the item.
     *
     * @param mixed $item A variable
     *
     * @return mixed The last element of the item
     *
     * @internal
     */
    public static function last(string $charset, $item)
    {
        $elements = self::slice($charset, $item, -1, 1, false);

        return \is_string($elements) ? $elements : current($elements);
    }

    /**
     * Joins the values to a string.
     *
     * The separators between elements are empty strings per default, you can define them with the optional parameters.
     *
     *  {{ [1, 2, 3]|join(', ', ' and ') }}
     *  {# returns 1, 2 and 3 #}
     *
     *  {{ [1, 2, 3]|join('|') }}
     *  {# returns 1|2|3 #}
     *
     *  {{ [1, 2, 3]|join }}
     *  {# returns 123 #}
     *
     * @param iterable|array|string|float|int|bool|null $value An array
     * @param string                                    $glue  The separator
     * @param string|null                               $and   The separator for the last pair
     *
     * @internal
     */
    public static function join($value, $glue = '', $and = null): string
    {
        if (!is_iterable($value)) {
            $value = (array) $value;
        }

        $value = self::toArray($value, false);

        if (0 === \count($value)) {
            return '';
        }

        if (null === $and || $and === $glue) {
            return implode($glue, $value);
        }

        if (1 === \count($value)) {
            return $value[0];
        }

        return implode($glue, \array_slice($value, 0, -1)).$and.$value[\count($value) - 1];
    }

    /**
     * Splits the string into an array.
     *
     *  {{ "one,two,three"|split(',') }}
     *  {# returns [one, two, three] #}
     *
     *  {{ "one,two,three,four,five"|split(',', 3) }}
     *  {# returns [one, two, "three,four,five"] #}
     *
     *  {{ "123"|split('') }}
     *  {# returns [1, 2, 3] #}
     *
     *  {{ "aabbcc"|split('', 2) }}
     *  {# returns [aa, bb, cc] #}
     *
     * @param string|null $value     A string
     * @param string      $delimiter The delimiter
     * @param int|null    $limit     The limit
     *
     * @internal
     */
    public static function split(string $charset, $value, $delimiter, $limit = null): array
    {
        $value = $value ?? '';

        if ('' !== $delimiter) {
            return null === $limit ? explode($delimiter, $value) : explode($delimiter, $value, $limit);
        }

        if ($limit <= 1) {
            return self::splitIntoCharacters($value, 'split');
        }

        $length = mb_strlen($value, $charset);
        if ($length < $limit) {
            return [$value];
        }

        $r = [];
        for ($i = 0; $i < $length; $i += $limit) {
            $r[] = mb_substr($value, $i, $limit, $charset);
        }

        return $r;
    }

    /**
     * @internal
     */
    public static function default($value, $default = '')
    {
        if (self::testEmpty($value)) {
            return $default;
        }

        return $value;
    }

    /**
     * Returns the keys for the given array.
     *
     * It is useful when you want to iterate over the keys of an array:
     *
     *  {% for key in array|keys %}
     *      {# ... #}
     *  {% endfor %}
     *
     * @internal
     */
    public static function keys($array): array
    {
        if ($array instanceof \Traversable) {
            while ($array instanceof \IteratorAggregate) {
                $array = $array->getIterator();
            }

            $keys = [];
            if ($array instanceof \Iterator) {
                $array->rewind();
                while ($array->valid()) {
                    $keys[] = $array->key();
                    $array->next();
                }

                return $keys;
            }

            foreach ($array as $key => $item) {
                $keys[] = $key;
            }

            return $keys;
        }

        if (!\is_array($array)) {
            return [];
        }

        return array_keys($array);
    }

    /**
     * Invokes a callable.
     *
     * @internal
     */
    public static function invoke(\Closure $arrow, ...$arguments): mixed
    {
        return $arrow(...$arguments);
    }

    /**
     * Reverses a variable.
     *
     * @param array|\Traversable|string|null $item         An array, a \Traversable instance, or a string
     * @param bool                           $preserveKeys Whether to preserve key or not
     *
     * @return mixed The reversed input
     *
     * @internal
     */
    public static function reverse(string $charset, $item, $preserveKeys = false)
    {
        if ($item instanceof \Traversable) {
            return array_reverse(iterator_to_array($item), $preserveKeys);
        }

        if (\is_array($item)) {
            return array_reverse($item, $preserveKeys);
        }

        $string = (string) $item;

        if ('UTF-8' !== $charset) {
            $string = self::convertEncoding($string, 'UTF-8', $charset);
        }

        $string = implode('', array_reverse(self::splitIntoCharacters($string, 'reverse')));

        if ('UTF-8' !== $charset) {
            $string = self::convertEncoding($string, $charset, 'UTF-8');
        }

        return $string;
    }

    /**
     * Shuffles an array, a \Traversable instance, or a string.
     * The function does not preserve keys.
     *
     * @param array|\Traversable|string|null $item
     *
     * @internal
     */
    public static function shuffle(string $charset, $item)
    {
        if (\is_string($item)) {
            if ('UTF-8' !== $charset) {
                $item = self::convertEncoding($item, 'UTF-8', $charset);
            }

            $item = self::splitIntoCharacters($item, 'shuffle');
            shuffle($item);
            $item = implode('', $item);

            if ('UTF-8' !== $charset) {
                $item = self::convertEncoding($item, $charset, 'UTF-8');
            }

            return $item;
        }

        if (is_iterable($item)) {
            $item = self::toArray($item, false);
            shuffle($item);
        }

        return $item;
    }

    /**
     * Sorts an array.
     *
     * @param array|\Traversable $array
     * @param ?\Closure          $arrow
     *
     * @internal
     */
    public static function sort(Environment $env, bool $isSandboxed, $array, $arrow = null): array
    {
        if ($array instanceof \Traversable) {
            $array = iterator_to_array($array);
        } elseif (!\is_array($array)) {
            throw new RuntimeError(\sprintf('The "sort" filter expects a sequence or a mapping, got "%s".', get_debug_type($array)));
        }

        if (null !== $arrow) {
            self::checkArrow($isSandboxed, $arrow, 'sort', 'filter');

            uasort($array, $arrow);
        } else {
            asort($array);
        }

        return $array;
    }

    /**
     * @internal
     */
    public static function inFilter($value, $compare)
    {
        if ($value instanceof Markup) {
            $value = (string) $value;
        }
        if ($compare instanceof Markup) {
            $compare = (string) $compare;
        }

        if (\is_string($compare)) {
            if (\is_string($value) || \is_int($value) || \is_float($value)) {
                return '' === $value || str_contains($compare, (string) $value);
            }

            return false;
        }

        if (!is_iterable($compare)) {
            return false;
        }

        if (\is_object($value) || \is_resource($value)) {
            if (!\is_array($compare)) {
                foreach ($compare as $item) {
                    if ($item === $value) {
                        return true;
                    }
                }

                return false;
            }

            return \in_array($value, $compare, true);
        }

        foreach ($compare as $item) {
            if (0 === self::compare($value, $item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compares two values using a more strict version of the PHP non-strict comparison operator.
     *
     * @see https://wiki.php.net/rfc/string_to_number_comparison
     * @see https://wiki.php.net/rfc/trailing_whitespace_numerics
     *
     * @internal
     */
    public static function compare($a, $b)
    {
        // int <=> string
        if (\is_int($a) && \is_string($b)) {
            $bTrim = trim($b, " \t\n\r\v\f");
            if (!is_numeric($bTrim)) {
                return (string) $a <=> $b;
            }
            if ((int) $bTrim == $bTrim) {
                return $a <=> (int) $bTrim;
            }

            return (float) $a <=> (float) $bTrim;
        }
        if (\is_string($a) && \is_int($b)) {
            $aTrim = trim($a, " \t\n\r\v\f");
            if (!is_numeric($aTrim)) {
                return $a <=> (string) $b;
            }
            if ((int) $aTrim == $aTrim) {
                return (int) $aTrim <=> $b;
            }

            return (float) $aTrim <=> (float) $b;
        }

        // float <=> string
        if (\is_float($a) && \is_string($b)) {
            if (is_nan($a)) {
                return 1;
            }
            $bTrim = trim($b, " \t\n\r\v\f");
            if (!is_numeric($bTrim)) {
                return (string) $a <=> $b;
            }

            return $a <=> (float) $bTrim;
        }
        if (\is_string($a) && \is_float($b)) {
            if (is_nan($b)) {
                return 1;
            }
            $aTrim = trim($a, " \t\n\r\v\f");
            if (!is_numeric($aTrim)) {
                return $a <=> (string) $b;
            }

            return (float) $aTrim <=> $b;
        }

        // fallback to <=>
        return $a <=> $b;
    }

    /**
     * @throws RuntimeError When the regular expression cannot be evaluated
     *
     * @internal
     */
    public static function matches(string $regexp, ?string $str): int
    {
        set_error_handler(static function ($t, $m) use ($regexp) {
            throw new RuntimeError(\sprintf('Regexp "%s" passed to "matches" is not valid', $regexp).substr($m, 12));
        });
        try {
            if (false === $result = preg_match($regexp, $str ?? '')) {
                throw new RuntimeError(\sprintf('Regexp "%s" passed to "matches" failed: %s.', $regexp, preg_last_error_msg()));
            }

            return $result;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Returns a trimmed string.
     *
     * @param string|\Stringable|null $string
     * @param string|null             $characterMask
     * @param string                  $side          left, right, or both
     *
     * @throws RuntimeError When an invalid trimming side is used
     *
     * @internal
     */
    public static function trim($string, $characterMask = null, $side = 'both'): string|\Stringable
    {
        if (null === $characterMask) {
            $characterMask = self::DEFAULT_TRIM_CHARS;
        }

        $trimmed = match ($side) {
            'both' => trim($string ?? '', $characterMask),
            'left' => ltrim($string ?? '', $characterMask),
            'right' => rtrim($string ?? '', $characterMask),
            default => throw new RuntimeError('Trimming side must be "left", "right" or "both".'),
        };

        // trimming a safe string with the default character mask always returns a safe string (independently of the context)
        return $string instanceof Markup && self::DEFAULT_TRIM_CHARS === $characterMask ? new Markup($trimmed, $string->getCharset()) : $trimmed;
    }

    /**
     * Inserts HTML line breaks before all newlines in a string.
     *
     * @param string|null $string
     *
     * @internal
     */
    public static function nl2br($string): string
    {
        return nl2br($string ?? '');
    }

    /**
     * Removes whitespaces between HTML tags.
     *
     * @param string|null $content
     *
     * @internal
     */
    public static function spaceless($content): string
    {
        return trim(preg_replace('/>\s+</', '><', $content ?? ''));
    }

    /**
     * @param string|null $string
     * @param string      $to
     * @param string      $from
     *
     * @internal
     */
    public static function convertEncoding($string, $to, $from): string
    {
        if (!\function_exists('iconv')) {
            throw new RuntimeError('Unable to convert encoding: required function iconv() does not exist. You should install ext-iconv or symfony/polyfill-iconv.');
        }

        return iconv($from, $to, $string ?? '');
    }

    /**
     * Unicode version of str_split(), an empty string giving a single empty character.
     *
     * @return non-empty-list<string>
     *
     * @throws RuntimeError When the string cannot be split into characters
     */
    private static function splitIntoCharacters(string $string, string $name): array
    {
        if (false === preg_match_all('/./us', $string, $matches)) {
            throw new RuntimeError(\sprintf('Unable to split the string passed to "%s" into characters: %s.', $name, preg_last_error_msg()));
        }

        return $matches[0] ?: [''];
    }

    /**
     * Returns the length of a variable.
     *
     * @param mixed $thing A variable
     *
     * @internal
     */
    public static function length(string $charset, $thing): int
    {
        if (null === $thing) {
            return 0;
        }

        if (\is_scalar($thing)) {
            return mb_strlen($thing, $charset);
        }

        if ($thing instanceof \Countable || \is_array($thing) || $thing instanceof \SimpleXMLElement) {
            return \count($thing);
        }

        if ($thing instanceof \Traversable) {
            return iterator_count($thing);
        }

        if ($thing instanceof \Stringable) {
            return mb_strlen((string) $thing, $charset);
        }

        return 1;
    }

    /**
     * Converts a string to uppercase.
     *
     * @param string|null $string A string
     *
     * @internal
     */
    public static function upper(string $charset, $string): string
    {
        return mb_strtoupper($string ?? '', $charset);
    }

    /**
     * Converts a string to lowercase.
     *
     * @param string|null $string A string
     *
     * @internal
     */
    public static function lower(string $charset, $string): string
    {
        return mb_strtolower($string ?? '', $charset);
    }

    /**
     * Strips HTML and PHP tags from a string.
     *
     * @param string|null          $string
     * @param string[]|string|null $allowable_tags
     *
     * @internal
     */
    public static function striptags($string, $allowable_tags = null): string
    {
        return strip_tags($string ?? '', $allowable_tags);
    }

    /**
     * Returns a titlecased string.
     *
     * @param string|null $string A string
     *
     * @internal
     */
    public static function titleCase(string $charset, $string): string
    {
        return mb_convert_case($string ?? '', \MB_CASE_TITLE, $charset);
    }

    /**
     * Returns a capitalized string.
     *
     * @param string|null $string A string
     *
     * @internal
     */
    public static function capitalize(string $charset, $string): string
    {
        return mb_strtoupper(mb_substr($string ?? '', 0, 1, $charset), $charset).mb_strtolower(mb_substr($string ?? '', 1, null, $charset), $charset);
    }

    /**
     * @internal
     *
     * to be removed in 4.0
     */
    public static function callMacro(MacroNamespace $namespace, string $method, array $args, int $lineno, array $context, Source $source)
    {
        return $namespace->call(substr($method, \strlen('macro_')), $args, $context, $lineno, $source);
    }

    /**
     * @template TSequence
     *
     * @param TSequence $seq
     *
     * @return ($seq is iterable ? TSequence : array{})
     *
     * @internal
     */
    public static function ensureTraversable($seq)
    {
        if (is_iterable($seq)) {
            return $seq;
        }

        return [];
    }

    /**
     * @internal
     */
    public static function toArray($seq, $preserveKeys = true)
    {
        if ($seq instanceof \Traversable) {
            return iterator_to_array($seq, $preserveKeys);
        }

        if (!\is_array($seq)) {
            return $seq;
        }

        return $preserveKeys ? $seq : array_values($seq);
    }

    /**
     * @param list<string|null> $names
     *
     * @internal
     */
    public static function destructureSequence(array &$context, array $names, \Traversable $sequence): \Traversable
    {
        $count = \count($names);
        if (0 === $count) {
            return $sequence;
        }

        $i = 0;
        foreach ($sequence as $value) {
            $name = $names[$i];
            if (null !== $name) {
                $context[$name] = $value;
            }
            if (++$i === $count) {
                return $sequence;
            }
        }

        for (; $i < $count; ++$i) {
            $name = $names[$i];
            if (null !== $name) {
                $context[$name] = null;
            }
        }

        return $sequence;
    }

    /**
     * Checks if a variable is empty.
     *
     *    {# evaluates to true if the foo variable is null, false, or the empty string #}
     *    {% if foo is empty %}
     *        {# ... #}
     *    {% endif %}
     *
     * @param mixed $value A variable
     *
     * @internal
     */
    public static function testEmpty($value): bool
    {
        if ($value instanceof \Countable) {
            return 0 === \count($value);
        }

        if ($value instanceof \Traversable) {
            return !iterator_count($value);
        }

        if ($value instanceof \Stringable) {
            return '' === (string) $value;
        }

        return '' === $value || false === $value || null === $value || [] === $value;
    }

    /**
     * Checks if a variable is a sequence.
     *
     *    {# evaluates to true if the foo variable is a sequence #}
     *    {% if foo is sequence %}
     *        {# ... #}
     *    {% endif %}
     *
     * @internal
     */
    public static function testSequence($value): bool
    {
        if ($value instanceof \ArrayObject) {
            $value = $value->getArrayCopy();
        }

        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value);
        }

        return \is_array($value) && array_is_list($value);
    }

    /**
     * Checks if a variable is a mapping.
     *
     *    {# evaluates to true if the foo variable is a mapping #}
     *    {% if foo is mapping %}
     *        {# ... #}
     *    {% endif %}
     *
     * @internal
     */
    public static function testMapping($value): bool
    {
        if ($value instanceof \ArrayObject) {
            $value = $value->getArrayCopy();
        }

        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value);
        }

        return (\is_array($value) && !array_is_list($value)) || \is_object($value);
    }

    /**
     * Renders a template.
     *
     * @param array                        $context
     * @param string|array|TemplateWrapper $template      The template to render or an array of templates to try consecutively
     * @param array                        $variables     The variables to pass to the template
     * @param bool                         $withContext
     * @param bool                         $ignoreMissing Whether to ignore missing templates or not
     * @param bool                         $sandboxed     Whether to sandbox the template or not
     *
     * @return string|Markup
     *
     * @internal
     */
    public static function include(Environment $env, $context, $template, $variables = [], $withContext = true, $ignoreMissing = false, $sandboxed = false)
    {
        if (\func_num_args() >= 7) {
            if ($sandboxed) {
                trigger_deprecation('twig/twig', '3.29', 'The "sandboxed" argument of the "include" function is deprecated, use the "render_sandboxed" function to render untrusted templates instead.');
            } else {
                trigger_deprecation('twig/twig', '3.29', 'The "sandboxed" argument of the "include" function is deprecated, remove the argument as "false" has no effect.');
            }
        }

        $alreadySandboxed = false;
        $sandbox = null;
        if ($withContext) {
            $variables = array_merge($context, $variables);
        }

        if ($isSandboxed = $sandboxed && $env->hasExtension(SandboxExtension::class)) {
            $sandbox = $env->getExtension(SandboxExtension::class)->getChecker();
            if (!$alreadySandboxed = $sandbox->isSandboxed()) {
                $sandbox->setSandboxed(true);
            }
        }

        try {
            $loaded = null;
            try {
                $loaded = $env->resolveTemplate($template);
            } catch (LoaderError $e) {
                if (!$ignoreMissing) {
                    throw $e;
                }

                return '';
            }

            $rendered = $loaded->render($variables);

            return '' === $rendered ? '' : new Markup($rendered, $env->getCharset());
        } finally {
            if ($isSandboxed && !$alreadySandboxed) {
                $sandbox->setSandboxed(false);
            }
        }
    }

    /**
     * Renders a template without giving it access to the current context.
     *
     * @param string|array<string|TemplateWrapper>|TemplateWrapper $template      The template to render or an array of templates to try consecutively
     * @param array<string, mixed>                                 $variables     The variables to pass to the template
     * @param bool                                                 $ignoreMissing Whether to ignore missing templates or not
     *
     * @return string|Markup
     *
     * @internal
     */
    public static function includeOnly(Environment $env, $template, array $variables = [], bool $ignoreMissing = false)
    {
        return self::include($env, [], $template, $variables, false, $ignoreMissing);
    }

    /**
     * Returns a template content without rendering it.
     *
     * @param string $name          The template name
     * @param bool   $ignoreMissing Whether to ignore missing templates or not
     *
     * @internal
     */
    public static function source(Environment $env, $name, $ignoreMissing = false): string
    {
        $loader = $env->getLoader();
        try {
            return $loader->getSourceContext($name)->getCode();
        } catch (LoaderError $e) {
            if (!$ignoreMissing) {
                throw $e;
            }

            return '';
        }
    }

    /**
     * Returns the list of cases of the enum.
     *
     * @template T of \UnitEnum
     *
     * @param class-string<T> $enum
     *
     * @return list<T>
     *
     * @internal
     */
    public static function enumCases(string $enum): array
    {
        if (!enum_exists($enum)) {
            throw new RuntimeError(\sprintf('Enum "%s" does not exist.', $enum));
        }

        return $enum::cases();
    }

    /**
     * Provides the ability to access enums by their class names.
     *
     * @template T of \UnitEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     *
     * @internal
     */
    public static function enum(string $enum): \UnitEnum
    {
        if (!enum_exists($enum)) {
            throw new RuntimeError(\sprintf('"%s" is not an enum.', $enum));
        }

        if (!$cases = $enum::cases()) {
            throw new RuntimeError(\sprintf('"%s" is an empty enum.', $enum));
        }

        return $cases[0];
    }

    /**
     * Provides the ability to get constants from instances as well as class/global constants.
     *
     * @param string      $constant     The name of the constant
     * @param object|null $object       The object to get the constant from
     * @param bool        $checkDefined Whether to check if the constant is defined or not
     *
     * @return mixed Class constants can return many types like scalars, arrays, and
     *               objects depending on the PHP version (\BackedEnum, \UnitEnum, etc.)
     *               When $checkDefined is true, returns true when the constant is defined, false otherwise
     *
     * @internal
     */
    public static function constant($constant, $object = null, bool $checkDefined = false)
    {
        if (null !== $object) {
            if ('class' === $constant) {
                return $checkDefined ? true : $object::class;
            }

            $constant = $object::class.'::'.$constant;
        }

        if (!\defined($constant)) {
            if ($checkDefined) {
                return false;
            }

            if ('::class' === strtolower(substr($constant, -7))) {
                throw new RuntimeError(\sprintf('You cannot use the Twig function "constant" to access "%s". You could provide an object and call constant("class", $object) or use the class name directly as a string.', $constant));
            }

            throw new RuntimeError(\sprintf('Constant "%s" is undefined.', $constant));
        }

        return $checkDefined ? true : \constant($constant);
    }

    /**
     * Batches item.
     *
     * @param array $items An array of items
     * @param int   $size  The size of the batch
     * @param mixed $fill  A value used to fill missing items
     *
     * @internal
     */
    public static function batch($items, $size, $fill = null, $preserveKeys = true): array
    {
        if (!is_iterable($items)) {
            throw new RuntimeError(\sprintf('The "batch" filter expects a sequence or a mapping, got "%s".', get_debug_type($items)));
        }

        $size = (int) ceil($size);

        $result = array_chunk(self::toArray($items, $preserveKeys), $size, $preserveKeys);

        if (null !== $fill && $result) {
            $last = \count($result) - 1;
            if ($fillCount = $size - \count($result[$last])) {
                for ($i = 0; $i < $fillCount; ++$i) {
                    $result[$last][] = $fill;
                }
            }
        }

        return $result;
    }

    /**
     * Returns the attribute value for a given array/object.
     *
     * @param mixed  $object            The object or array from where to get the item
     * @param mixed  $item              The item to get from the array or object
     * @param array  $arguments         An array of arguments to pass if the item is an object method
     * @param string $type              The type of attribute (@see \Twig\Template constants)
     * @param bool   $isDefinedTest     Whether this is only a defined check
     * @param bool   $ignoreStrictCheck Whether to ignore the strict attribute check or not
     * @param int    $lineno            The template line where the attribute was called
     *
     * @return mixed The attribute value, or a Boolean when $isDefinedTest is true, or null when the attribute is not set and $ignoreStrictCheck is true
     *
     * @throws RuntimeError if the attribute does not exist and Twig is running in strict mode and $isDefinedTest is false
     *
     * @internal
     */
    public static function getAttribute(Environment $env, Source $source, $object, $item, array $arguments = [], $type = Template::ANY_CALL, $isDefinedTest = false, $ignoreStrictCheck = false, $sandboxed = false, int $lineno = -1)
    {
        $propertyNotAllowedError = null;
        if ($sandboxed && $item instanceof \Stringable) {
            $env->getExtension(SandboxExtension::class)->getChecker()->ensureToStringAllowed($item, $lineno, $source);
        }

        // array
        if (Template::METHOD_CALL !== $type) {
            $arrayItem = \is_bool($item) || \is_float($item) ? (int) $item : $item;

            if ($arrayItem instanceof \Stringable && ($object instanceof \ArrayObject || $object instanceof \ArrayIterator)) {
                $arrayItem = (string) $arrayItem;
            }

            if ($sandboxed && $object instanceof \ArrayAccess && !\in_array($object::class, self::ARRAY_LIKE_CLASSES, true)) {
                try {
                    $env->getExtension(SandboxExtension::class)->getChecker()->checkPropertyAllowed($object, $arrayItem, $lineno, $source);
                } catch (SecurityNotAllowedPropertyError $propertyNotAllowedError) {
                    // The methodCheck path expects $item to be a string; stringify it here
                    // to avoid PHP 8.1+ implicit float-to-int deprecations on downstream
                    // array key lookups (e.g. isset($cache[$class][$item])).
                    $item = (string) $item;
                    goto methodCheck;
                }
            }

            if (match (true) {
                \is_array($object) => \array_key_exists($arrayItem = (string) $arrayItem, $object),
                $object instanceof \ArrayAccess => $object->offsetExists($arrayItem),
                default => false,
            }) {
                if ($isDefinedTest) {
                    return true;
                }

                return $object[$arrayItem];
            }

            if (Template::ARRAY_CALL === $type || !\is_object($object)) {
                if ($isDefinedTest) {
                    return false;
                }

                if ($ignoreStrictCheck || !$env->isStrictVariables()) {
                    return;
                }

                if ($object instanceof \ArrayAccess) {
                    if (\is_object($arrayItem) || \is_array($arrayItem)) {
                        $message = \sprintf('Key of type "%s" does not exist in ArrayAccess-able object of class "%s".', get_debug_type($arrayItem), get_debug_type($object));
                    } else {
                        $message = \sprintf('Key "%s" does not exist in ArrayAccess-able object of class "%s".', $arrayItem, get_debug_type($object));
                    }
                } elseif (\is_object($object)) {
                    $message = \sprintf('Impossible to access a key "%s" on an object of class "%s" that does not implement ArrayAccess interface.', $item, get_debug_type($object));
                } elseif (\is_array($object)) {
                    if (!$object) {
                        $message = \sprintf('Key "%s" does not exist as the sequence/mapping is empty.', $arrayItem);
                    } else {
                        $message = \sprintf('Key "%s" for sequence/mapping with keys "%s" does not exist.', $arrayItem, implode(', ', array_keys($object)));
                    }
                } elseif (Template::ARRAY_CALL === $type) {
                    if (null === $object) {
                        $message = \sprintf('Impossible to access a key ("%s") on a null variable.', $item);
                    } else {
                        $message = \sprintf('Impossible to access a key ("%s") on a %s variable ("%s").', $item, get_debug_type($object), $object);
                    }
                } elseif (null === $object) {
                    $message = \sprintf('Impossible to access an attribute ("%s") on a null variable.', $item);
                } else {
                    $message = \sprintf('Impossible to access an attribute ("%s") on a %s variable ("%s").', $item, get_debug_type($object), $object);
                }

                throw new RuntimeError($message, $lineno, $source);
            }
        }

        $item = (string) $item;

        if (!\is_object($object)) {
            if ($isDefinedTest) {
                return false;
            }

            if ($ignoreStrictCheck || !$env->isStrictVariables()) {
                return;
            }

            if (null === $object) {
                $message = \sprintf('Impossible to invoke a method ("%s") on a null variable.', $item);
            } elseif (\is_array($object)) {
                $message = \sprintf('Impossible to invoke a method ("%s") on a sequence/mapping.', $item);
            } else {
                $message = \sprintf('Impossible to invoke a method ("%s") on a %s variable ("%s").', $item, get_debug_type($object), $object);
            }

            throw new RuntimeError($message, $lineno, $source);
        }

        if ($object instanceof Template) {
            throw new RuntimeError('Accessing \Twig\Template attributes is forbidden.', $lineno, $source);
        }

        // object property
        if (Template::METHOD_CALL !== $type) {
            if ($sandboxed) {
                try {
                    $env->getExtension(SandboxExtension::class)->getChecker()->checkPropertyAllowed($object, $item, $lineno, $source);
                } catch (SecurityNotAllowedPropertyError $propertyNotAllowedError) {
                    goto methodCheck;
                }
            }

            static $propertyCheckers = [];

            if (isset($object->$item)
                || ($propertyCheckers[$object::class][$item] ??= self::getPropertyChecker($object::class, $item))($object, $item)
            ) {
                if ($isDefinedTest) {
                    return true;
                }

                return $object->$item;
            }

            if ($object instanceof \DateTimeInterface && \in_array($item, ['date', 'timezone', 'timezone_type'], true)) {
                if ($isDefinedTest) {
                    return true;
                }

                return ((array) $object)[$item];
            }

            if (\defined($object::class.'::'.$item)) {
                if ($isDefinedTest) {
                    return true;
                }

                return \constant($object::class.'::'.$item);
            }
        }

        methodCheck:

        static $cache = [];

        $class = $object::class;

        // object method
        // precedence: getXxx() > isXxx() > hasXxx()
        if (!isset($cache[$class])) {
            $methods = get_class_methods($object);
            if ($object instanceof \Closure) {
                $methods[] = '__invoke';
            }
            sort($methods);
            $lcMethods = array_map('strtolower', $methods);
            $classCache = [];
            foreach ($methods as $i => $method) {
                $classCache[$method] = $method;
                $classCache[$lcName = $lcMethods[$i]] = $method;

                if ('g' === $lcName[0] && str_starts_with($lcName, 'get')) {
                    $prefixLength = 3;
                    $lcName = substr($lcName, $prefixLength);
                } elseif ('i' === $lcName[0] && str_starts_with($lcName, 'is')) {
                    $prefixLength = 2;
                    $lcName = substr($lcName, $prefixLength);
                } elseif ('h' === $lcName[0] && str_starts_with($lcName, 'has')) {
                    $prefixLength = 3;
                    $lcName = substr($lcName, $prefixLength);
                    if (\in_array('is'.$lcName, $lcMethods, true)) {
                        continue;
                    }
                } else {
                    continue;
                }

                // skip get(), is() and has() methods (in which case, $lcName is empty)
                if ($lcName) {
                    // camelCase name (e.g. getFooBar() -> fooBar)
                    $name = $lcName[0].substr($method, $prefixLength + 1);

                    if (!isset($classCache[$name])) {
                        $classCache[$name] = $method;
                    }

                    if (!isset($classCache[$lcName])) {
                        $classCache[$lcName] = $method;
                    }
                }
            }
            $cache[$class] = $classCache;
        }

        $call = false;
        if (isset($cache[$class][$item])) {
            $method = $cache[$class][$item];
        } elseif (isset($cache[$class][$lcItem = strtolower($item)])) {
            $method = $cache[$class][$lcItem];
        } elseif (isset($cache[$class]['__call'])) {
            $method = $item;
            $call = true;
        } else {
            if ($isDefinedTest) {
                return false;
            }

            if ($propertyNotAllowedError) {
                throw $propertyNotAllowedError;
            }

            if ($ignoreStrictCheck || !$env->isStrictVariables()) {
                return;
            }

            throw new RuntimeError(\sprintf('Neither the property "%1$s" nor one of the methods "%1$s()", "get%1$s()", "is%1$s()", "has%1$s()" or "__call()" exist and have public access in class "%2$s".', $item, $class), $lineno, $source);
        }

        if ($sandboxed) {
            try {
                $env->getExtension(SandboxExtension::class)->getChecker()->checkMethodAllowed($object, $method, $lineno, $source);
            } catch (SecurityNotAllowedMethodError $e) {
                if ($isDefinedTest) {
                    return false;
                }

                if ($propertyNotAllowedError) {
                    throw $propertyNotAllowedError;
                }

                throw $e;
            }
        }

        if ($isDefinedTest) {
            return true;
        }

        // Some objects throw exceptions when they have __call, and the method we try
        // to call is not supported. If ignoreStrictCheck is true, we should return null.
        try {
            $ret = $object->$method(...$arguments);
        } catch (\BadMethodCallException $e) {
            if ($call && ($ignoreStrictCheck || !$env->isStrictVariables())) {
                return;
            }
            throw $e;
        }

        return $ret;
    }

    /**
     * Returns the values from a single column in the input array.
     *
     * <pre>
     *  {% set items = [{ 'fruit' : 'apple'}, {'fruit' : 'orange' }] %}
     *
     *  {% set fruits = items|column('fruit') %}
     *
     *  {# fruits now contains ['apple', 'orange'] #}
     * </pre>
     *
     * @param array|\Traversable $array An array
     * @param int|string         $name  The column name
     * @param int|string|null    $index The column to use as the index/keys for the returned array
     *
     * @return array The array of values
     *
     * @internal
     */
    public static function column(Environment $env, bool $isSandboxed, $array, $name, $index = null): array
    {
        if (!is_iterable($array)) {
            throw new RuntimeError(\sprintf('The "column" filter expects a sequence or a mapping, got "%s".', get_debug_type($array)));
        }

        if ($array instanceof \Traversable) {
            $array = iterator_to_array($array);
        }

        if ($isSandboxed) {
            // The sandbox might be enabled via a SourcePolicyInterface, in which case the SandboxExtension
            // would not consider the sandbox active without the current Source: $isSandboxed is already
            // computed against the call-site source, so check the policy directly to honor that decision.
            $policy = $env->getExtension(SandboxExtension::class)->getChecker()->getSecurityPolicy();
            foreach ($array as $item) {
                if (\is_object($item)) {
                    $policy->checkPropertyAllowed($item, (string) $name);
                    if (null !== $index) {
                        $policy->checkPropertyAllowed($item, (string) $index);
                    }
                }
            }
        }

        return array_column($array, $name, $index);
    }

    /**
     * @param \Closure $arrow
     *
     * @internal
     */
    public static function filter(Environment $env, bool $isSandboxed, $array, $arrow)
    {
        if (!is_iterable($array)) {
            throw new RuntimeError(\sprintf('The "filter" filter expects a sequence/mapping or "Traversable", got "%s".', get_debug_type($array)));
        }

        self::checkArrow($isSandboxed, $arrow, 'filter', 'filter');

        if (\is_array($array)) {
            return array_filter($array, $arrow, \ARRAY_FILTER_USE_BOTH);
        }

        // the IteratorIterator wrapping is needed as some internal PHP classes are \Traversable but do not implement \Iterator
        return new \CallbackFilterIterator(new \IteratorIterator($array), $arrow);
    }

    /**
     * @param \Closure $arrow
     *
     * @internal
     */
    public static function find(Environment $env, bool $isSandboxed, $array, $arrow)
    {
        if (!is_iterable($array)) {
            throw new RuntimeError(\sprintf('The "find" filter expects a sequence or a mapping, got "%s".', get_debug_type($array)));
        }

        self::checkArrow($isSandboxed, $arrow, 'find', 'filter');

        foreach ($array as $k => $v) {
            if ($arrow($v, $k)) {
                return $v;
            }
        }

        return null;
    }

    /**
     * @param \Closure $arrow
     *
     * @internal
     */
    public static function map(Environment $env, bool $isSandboxed, $array, $arrow)
    {
        if (!is_iterable($array)) {
            throw new RuntimeError(\sprintf('The "map" filter expects a sequence or a mapping, got "%s".', get_debug_type($array)));
        }

        self::checkArrow($isSandboxed, $arrow, 'map', 'filter');

        $r = [];
        foreach ($array as $k => $v) {
            $r[$k] = $arrow($v, $k);
        }

        return $r;
    }

    /**
     * @param \Closure $arrow
     *
     * @internal
     */
    public static function reduce(Environment $env, bool $isSandboxed, $array, $arrow, $initial = null)
    {
        if (!is_iterable($array)) {
            throw new RuntimeError(\sprintf('The "reduce" filter expects a sequence or a mapping, got "%s".', get_debug_type($array)));
        }

        self::checkArrow($isSandboxed, $arrow, 'reduce', 'filter');

        $accumulator = $initial;
        foreach ($array as $key => $value) {
            $accumulator = $arrow($accumulator, $value, $key);
        }

        return $accumulator;
    }

    /**
     * @param \Closure $arrow
     *
     * @internal
     */
    public static function arraySome(Environment $env, $array, $arrow, bool $isSandboxed = false)
    {
        if (!is_iterable($array)) {
            throw new RuntimeError(\sprintf('The "has some" test expects a sequence or a mapping, got "%s".', get_debug_type($array)));
        }

        self::checkArrow($isSandboxed, $arrow, 'has some', 'operator');

        foreach ($array as $k => $v) {
            if ($arrow($v, $k)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \Closure $arrow
     *
     * @internal
     */
    public static function arrayEvery(Environment $env, $array, $arrow, bool $isSandboxed = false)
    {
        if (!is_iterable($array)) {
            throw new RuntimeError(\sprintf('The "has every" test expects a sequence or a mapping, got "%s".', get_debug_type($array)));
        }

        self::checkArrow($isSandboxed, $arrow, 'has every', 'operator');

        foreach ($array as $k => $v) {
            if (!$arrow($v, $k)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @internal
     */
    public static function checkArrow(bool $isSandboxed, $arrow, $thing, $type): void
    {
        if ($arrow instanceof \Closure) {
            return;
        }

        if ($isSandboxed) {
            throw new RuntimeError(\sprintf('The callable passed to the "%s" %s must be a Closure in sandbox mode.', $thing, $type));
        }

        trigger_deprecation('twig/twig', '3.15', 'Passing a callable that is not a PHP \Closure as an argument to the "%s" %s is deprecated.', $thing, $type);
    }

    /**
     * @internal to be removed in Twig 4
     */
    public static function captureOutput(iterable $body): string
    {
        $level = ob_get_level();
        ob_start();

        try {
            foreach ($body as $data) {
                echo $data;
            }
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $e;
        }

        return ob_get_clean();
    }

    /**
     * @internal
     */
    public static function parseParentFunction(Parser $parser, Node $fakeNode, $args, int $line): AbstractExpression
    {
        if (!$blockName = $parser->peekBlockStack()) {
            throw new SyntaxError('Calling the "parent" function outside of a block is forbidden.', $line, $parser->getStream()->getSourceContext());
        }

        if (!$parser->hasInheritance()) {
            throw new SyntaxError('Calling the "parent" function on a template that does not call "extends" or "use" is forbidden.', $line, $parser->getStream()->getSourceContext());
        }

        return new ParentExpression($blockName, $line);
    }

    /**
     * @internal
     */
    public static function parseBlockFunction(Parser $parser, Node $fakeNode, $args, int $line): AbstractExpression
    {
        $fakeFunction = new TwigFunction('block', static fn ($name, $template = null) => null);
        $args = (new CallableArgumentsExtractor($fakeNode, $fakeFunction))->extractArguments($args);

        return new BlockReferenceExpression($args[0], $args[1] ?? null, $line);
    }

    /**
     * @internal
     */
    public static function parseAttributeFunction(Parser $parser, Node $fakeNode, $args, int $line): AbstractExpression
    {
        $fakeFunction = new TwigFunction('attribute', static fn ($variable, $attribute, $arguments = null) => null);
        $args = (new CallableArgumentsExtractor($fakeNode, $fakeFunction))->extractArguments($args);

        /*
        Deprecation to uncomment sometimes during the lifetime of the 4.x branch
        $src = $parser->getStream()->getSourceContext();
        $dep = new DeprecatedCallableInfo('twig/twig', '3.15', 'The "attribute" function is deprecated, use the "." notation instead.');
        $dep->setName('attribute');
        $dep->setType('function');
        $dep->triggerDeprecation($src->getPath() ?: $src->getName(), $line);
        */

        return new GetAttrExpression($args[0], $args[1], $args[2] ?? null, Template::ANY_CALL, $line);
    }

    private static function getPropertyChecker(string $class, string $property): \Closure
    {
        static $classReflectors = [];

        $class = $classReflectors[$class] ??= new \ReflectionClass($class);

        if (!$class->hasProperty($property)) {
            static $propertyExists;

            return $propertyExists ??= \Closure::fromCallable('property_exists');
        }

        $property = $class->getProperty($property);

        if (!$property->isPublic() || $property->isStatic()) {
            static $false;

            return $false ??= static fn () => false;
        }

        return static fn ($object) => $property->isInitialized($object);
    }
}
