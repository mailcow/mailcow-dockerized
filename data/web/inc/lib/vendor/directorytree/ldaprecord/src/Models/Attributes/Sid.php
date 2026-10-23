<?php

namespace LdapRecord\Models\Attributes;

use InvalidArgumentException;
use Stringable;

class Sid implements Stringable
{
    /**
     * The string SID value.
     */
    protected string $value;

    /**
     * Determine if the specified SID is valid.
     */
    public static function isValid(string $sid): bool
    {
        return (bool) preg_match("/^S-\d(-\d{1,10}){1,16}$/i", $sid);
    }

    /**
     * Constructor.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(string $value)
    {
        if (static::isValid($value)) {
            $this->value = $value;
        } elseif ($value = $this->binarySidToString($value)) {
            $this->value = $value;
        } else {
            throw new InvalidArgumentException('Invalid Binary / String SID.');
        }
    }

    /**
     * Get the string value of the SID.
     */
    public function __toString(): string
    {
        return $this->getValue();
    }

    /**
     * Get the string value of the SID.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get the binary variant of the SID.
     */
    public function getBinary(): string
    {
        $sid = explode('-', ltrim($this->value, 'S-'));

        $level = (int) array_shift($sid);

        $authority = (int) array_shift($sid);

        $subAuthorities = array_map('intval', $sid);

        $params = array_merge(
            ['C2xxNV*', $level, count($subAuthorities), $authority],
            $subAuthorities
        );

        return call_user_func_array('pack', $params);
    }

    /**
     * Get the string variant of a binary SID.
     */
    protected function binarySidToString(string $binary): ?string
    {
        if (trim($binary) === '') {
            return null;
        }

        // Revision - 8bit unsigned int (C1)
        // Count - 8bit unsigned int (C1)
        // 2 null bytes
        // ID - 32bit unsigned long, big-endian order
        $sid = @unpack('C1rev/C1count/x2/N1id', $binary);

        if (! isset($sid['id']) || ! isset($sid['rev'])) {
            return null;
        }

        $revisionLevel = $sid['rev'];

        $identifierAuthority = $sid['id'];

        $subs = $sid['count'] ?? 0;

        $sidHex = $subs ? bin2hex($binary) : '';

        $subAuthorities = [];

        // The sub-authorities depend on the count, so only get as
        // many as the count, regardless of data beyond it.
        for ($i = 0; $i < $subs; $i++) {
            $data = implode(array_reverse(
                str_split(
                    substr($sidHex, 16 + ($i * 8), 8),
                    2
                )
            ));

            $subAuthorities[] = hexdec($data);
        }

        // Tack on the 'S-' and glue it all together...
        return 'S-'.$revisionLevel.'-'.$identifierAuthority.implode(
            preg_filter('/^/', '-', $subAuthorities)
        );
    }
}
