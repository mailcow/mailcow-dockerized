<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

final class Query
{
    private function __construct()
    {
    }

    /**
     * Parse a query string into an associative array.
     *
     * If multiple values are found for the same key, the value of that
     * key-value pair becomes an array. This function does not parse nested PHP
     * style arrays into an associative array. For example, `foo[a]=1&foo[b]=2`
     * will be parsed into `['foo[a]' => '1', 'foo[b]' => '2']`.
     *
     * @param string   $str         Query string to parse
     * @param int|bool $urlEncoding How the query string is encoded
     */
    public static function parse(string $str, $urlEncoding = true): array
    {
        $result = [];

        if ($str === '') {
            return $result;
        }

        if ($urlEncoding === true) {
            $decoder = function (string $value): string {
                return \rawurldecode(str_replace('+', ' ', $value));
            };
        } elseif ($urlEncoding === PHP_QUERY_RFC3986) {
            $decoder = static function (string $value): string {
                return \rawurldecode($value);
            };
        } elseif ($urlEncoding === PHP_QUERY_RFC1738) {
            $decoder = static function (string $value): string {
                return \urldecode($value);
            };
        } else {
            $decoder = function (string $str): string {
                return $str;
            };
        }

        foreach (explode('&', $str) as $kvp) {
            $parts = explode('=', $kvp, 2);
            $key = $decoder($parts[0]);
            $value = isset($parts[1]) ? $decoder($parts[1]) : null;
            if (!array_key_exists($key, $result)) {
                $result[$key] = $value;
            } else {
                if (!is_array($result[$key])) {
                    $result[$key] = [$result[$key]];
                }
                $result[$key][] = $value;
            }
        }

        return $result;
    }

    /**
     * Build a query string from an array of key-value pairs.
     *
     * This function can use the return value of `parse()` to build a query
     * string. This function does not modify the provided keys when an array is
     * encountered, unlike `http_build_query()`.
     *
     * @param array     $params           Query string parameters.
     * @param int|false $encoding         Set to false to not encode,
     *                                    PHP_QUERY_RFC3986 to encode using
     *                                    RFC3986, or PHP_QUERY_RFC1738 to
     *                                    encode using RFC1738.
     * @param bool      $treatBoolsAsInts Set to true to encode as 0/1, and
     *                                    false as false/true.
     */
    public static function build(array $params, $encoding = PHP_QUERY_RFC3986, bool $treatBoolsAsInts = true): string
    {
        if (!$params) {
            return '';
        }

        if ($encoding === false) {
            $encoder = function (string $str): string {
                return $str;
            };
        } elseif ($encoding === PHP_QUERY_RFC3986) {
            $encoder = static function (string $value): string {
                return \rawurlencode($value);
            };
        } elseif ($encoding === PHP_QUERY_RFC1738) {
            $encoder = static function (string $value): string {
                return \urlencode($value);
            };
        } else {
            throw new \InvalidArgumentException('Invalid type');
        }

        $qs = '';
        foreach ($params as $k => $v) {
            $k = $encoder((string) $k);
            if (!is_array($v)) {
                $qs .= $k;
                $v = self::normalizeValue($v, $treatBoolsAsInts);
                if ($v !== null) {
                    $qs .= '='.$encoder($v);
                }
                $qs .= '&';
            } else {
                foreach ($v as $vv) {
                    $qs .= $k;
                    $vv = self::normalizeValue($vv, $treatBoolsAsInts);
                    if ($vv !== null) {
                        $qs .= '='.$encoder($vv);
                    }
                    $qs .= '&';
                }
            }
        }

        return $qs ? (string) substr($qs, 0, -1) : '';
    }

    /**
     * @param mixed $value
     */
    private static function normalizeValue($value, bool $treatBoolsAsInts): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $treatBoolsAsInts ? (string) (int) $value : ($value ? 'true' : 'false');
        }

        if (is_float($value) && !is_finite($value)) {
            throw new \InvalidArgumentException('Query string values must be finite; non-finite floats are not supported.');
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return $value->__toString();
        }

        throw new \InvalidArgumentException('Query string values must be scalar, null, or stringable objects');
    }
}
