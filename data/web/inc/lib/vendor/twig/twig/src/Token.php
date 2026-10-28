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

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Token
{
    public const EOF_TYPE = -1;
    public const TEXT_TYPE = 0;
    public const BLOCK_START_TYPE = 1;
    public const VAR_START_TYPE = 2;
    public const BLOCK_END_TYPE = 3;
    public const VAR_END_TYPE = 4;
    public const NAME_TYPE = 5;
    public const NUMBER_TYPE = 6;
    public const STRING_TYPE = 7;
    public const OPERATOR_TYPE = 8;
    public const PUNCTUATION_TYPE = 9;
    public const INTERPOLATION_START_TYPE = 10;
    public const INTERPOLATION_END_TYPE = 11;
    /**
     * @deprecated since Twig 3.21, "arrow" is now an operator
     */
    public const ARROW_TYPE = 12;
    /**
     * @deprecated since Twig 3.21, "spread" is now an operator
     */
    public const SPREAD_TYPE = 13;

    /**
     * @param non-negative-int|null $offset
     */
    public function __construct(
        private int $type,
        private $value,
        private int $lineno,
        private ?int $offset = null,
        private ?string $documentation = null,
    ) {
        if (self::ARROW_TYPE === $type) {
            trigger_deprecation('twig/twig', '3.21', 'The "%s" token type is deprecated, "arrow" is now an operator.', self::ARROW_TYPE);
        }
        if (self::SPREAD_TYPE === $type) {
            trigger_deprecation('twig/twig', '3.21', 'The "%s" token type is deprecated, "spread" is now an operator.', self::SPREAD_TYPE);
        }
    }

    public function __toString(): string
    {
        return \sprintf('%s(%s)', self::typeToString($this->type, true), $this->value);
    }

    /**
     * Tests the current token for a type and/or a value.
     *
     * Parameters may be:
     *  * just type
     *  * type and value (or array of possible values)
     *  * just value (or array of possible values) (NAME_TYPE is used as type)
     *
     * @param array|string|int  $type   The type to test
     * @param array|string|null $values The token value
     */
    public function test($type, $values = null): bool
    {
        if (null === $values && !\is_int($type)) {
            $values = $type;
            $type = self::NAME_TYPE;
        }

        if (self::ARROW_TYPE === $type) {
            trigger_deprecation('twig/twig', '3.21', 'The "%s" token type is deprecated, "arrow" is now an operator.', self::typeToEnglish(self::ARROW_TYPE));

            return self::OPERATOR_TYPE === $this->type && '=>' === $this->value;
        }
        if (self::SPREAD_TYPE === $type) {
            trigger_deprecation('twig/twig', '3.21', 'The "%s" token type is deprecated, "spread" is now an operator.', self::typeToEnglish(self::SPREAD_TYPE));

            return self::OPERATOR_TYPE === $this->type && '...' === $this->value;
        }

        $typeMatches = $this->type === $type;
        if ($typeMatches && self::PUNCTUATION_TYPE === $type && \in_array($this->value, ['(', '[', '|', '.', '?', '?:'], true) && $values) {
            foreach ((array) $values as $value) {
                if (\in_array($value, ['(', '[', '|', '.', '?', '?:'], true)) {
                    trigger_deprecation('twig/twig', '3.21', 'The "%s" token is now an "%s" token instead of a "%s" one.', $this->value, self::typeToEnglish(self::OPERATOR_TYPE), $this->toEnglish());

                    break;
                }
            }
        }
        if (!$typeMatches) {
            if (self::OPERATOR_TYPE === $type && self::PUNCTUATION_TYPE === $this->type) {
                if ($values) {
                    foreach ((array) $values as $value) {
                        if (\in_array($value, ['(', '[', '|', '.', '?', '?:'], true)) {
                            $typeMatches = true;

                            break;
                        }
                    }
                } else {
                    $typeMatches = true;
                }
            }
        }

        return $typeMatches && (
            null === $values
            || (\is_array($values) && \in_array($this->value, $values, true))
            || $this->value == $values
        );
    }

    public function getLine(): int
    {
        return $this->lineno;
    }

    /**
     * Returns the 0-based byte offset of the token in the source code.
     *
     * Returns null for tokens that are not tied to a source position (e.g.
     * tokens synthesized by a token parser).
     *
     * @return non-negative-int|null
     */
    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function getDocumentation(): ?string
    {
        return $this->documentation;
    }

    /**
     * @deprecated since Twig 3.19
     */
    public function getType(): int
    {
        trigger_deprecation('twig/twig', '3.19', \sprintf('The "%s()" method is deprecated.', __METHOD__));

        return $this->type;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function toEnglish(): string
    {
        return self::typeToEnglish($this->type);
    }

    public static function typeToString(int $type, bool $short = false): string
    {
        switch ($type) {
            case self::EOF_TYPE:
                $name = 'EOF_TYPE';
                break;
            case self::TEXT_TYPE:
                $name = 'TEXT_TYPE';
                break;
            case self::BLOCK_START_TYPE:
                $name = 'BLOCK_START_TYPE';
                break;
            case self::VAR_START_TYPE:
                $name = 'VAR_START_TYPE';
                break;
            case self::BLOCK_END_TYPE:
                $name = 'BLOCK_END_TYPE';
                break;
            case self::VAR_END_TYPE:
                $name = 'VAR_END_TYPE';
                break;
            case self::NAME_TYPE:
                $name = 'NAME_TYPE';
                break;
            case self::NUMBER_TYPE:
                $name = 'NUMBER_TYPE';
                break;
            case self::STRING_TYPE:
                $name = 'STRING_TYPE';
                break;
            case self::OPERATOR_TYPE:
                $name = 'OPERATOR_TYPE';
                break;
            case self::PUNCTUATION_TYPE:
                $name = 'PUNCTUATION_TYPE';
                break;
            case self::INTERPOLATION_START_TYPE:
                $name = 'INTERPOLATION_START_TYPE';
                break;
            case self::INTERPOLATION_END_TYPE:
                $name = 'INTERPOLATION_END_TYPE';
                break;
            case self::ARROW_TYPE:
                $name = 'ARROW_TYPE';
                break;
            case self::SPREAD_TYPE:
                $name = 'SPREAD_TYPE';
                break;
            default:
                throw new \LogicException(\sprintf('Token of type "%s" does not exist.', $type));
        }

        return $short ? $name : 'Twig\Token::'.$name;
    }

    public static function typeToEnglish(int $type): string
    {
        switch ($type) {
            case self::EOF_TYPE:
                return 'end of template';
            case self::TEXT_TYPE:
                return 'text';
            case self::BLOCK_START_TYPE:
                return 'begin of statement block';
            case self::VAR_START_TYPE:
                return 'begin of print statement';
            case self::BLOCK_END_TYPE:
                return 'end of statement block';
            case self::VAR_END_TYPE:
                return 'end of print statement';
            case self::NAME_TYPE:
                return 'name';
            case self::NUMBER_TYPE:
                return 'number';
            case self::STRING_TYPE:
                return 'string';
            case self::OPERATOR_TYPE:
                return 'operator';
            case self::PUNCTUATION_TYPE:
                return 'punctuation';
            case self::INTERPOLATION_START_TYPE:
                return 'begin of string interpolation';
            case self::INTERPOLATION_END_TYPE:
                return 'end of string interpolation';
            case self::ARROW_TYPE:
                return 'arrow function';
            case self::SPREAD_TYPE:
                return 'spread operator';
            default:
                throw new \LogicException(\sprintf('Token of type "%s" does not exist.', $type));
        }
    }
}
