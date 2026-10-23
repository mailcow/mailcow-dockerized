<?php

namespace LdapRecord\Models\Attributes;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTime;
use DateTimeZone;
use LdapRecord\LdapRecordException;

class Timestamp
{
    public const TYPE_LDAP = 'ldap';

    public const TYPE_WINDOWS = 'windows';

    public const TYPE_WINDOWS_INT = 'windows-int';

    public const WINDOWS_INT_MAX = 9223372036854775807;

    /**
     * The current timestamp type.
     */
    protected string $type;

    /**
     * The available timestamp types.
     */
    protected array $types = [
        Timestamp::TYPE_LDAP,
        Timestamp::TYPE_WINDOWS,
        Timestamp::TYPE_WINDOWS_INT,
    ];

    /**
     * Constructor.
     *
     * @throws LdapRecordException
     */
    public function __construct(string $type)
    {
        $this->setType($type);
    }

    /**
     * Set the type of timestamp to convert from / to.
     *
     * @throws LdapRecordException
     */
    public function setType(string $type): void
    {
        if (! in_array($type, $this->types)) {
            throw new LdapRecordException("Unrecognized LDAP date type [$type]");
        }

        $this->type = $type;
    }

    /**
     * Converts the value to an LDAP date string.
     *
     * @throws LdapRecordException
     */
    public function fromDateTime(mixed $value): int|string
    {
        $value = is_array($value) ? reset($value) : $value;

        // If the value is being converted to a windows integer format, but it
        // is already in that format, we will simply return the value back.
        if ($this->type === Timestamp::TYPE_WINDOWS_INT && $this->valueIsWindowsIntegerType($value)) {
            return $value;
        }
        // If the value is numeric, we will assume it's a UNIX timestamp.
        elseif (is_numeric($value)) {
            $value = Carbon::createFromTimestamp($value);
        }
        // If a string is given, we will pass it into a new carbon instance.
        elseif (is_string($value)) {
            $value = Carbon::parse($value);
        }
        // If a date object is given, we will convert it to a carbon instance.
        elseif ($value instanceof DateTime) {
            $value = Carbon::instance($value);
        }

        return match ($this->type) {
            Timestamp::TYPE_LDAP => $this->convertDateTimeToLdapTime($value),
            Timestamp::TYPE_WINDOWS => $this->convertDateTimeToWindows($value),
            Timestamp::TYPE_WINDOWS_INT => $this->convertDateTimeToWindowsInteger($value),
            default => throw new LdapRecordException("Unrecognized date type [{$this->type}]"),
        };
    }

    /**
     * Determine if the value given is in Windows Integer (NTFS Filetime) format.
     */
    protected function valueIsWindowsIntegerType(mixed $value): bool
    {
        return is_numeric($value) && in_array(strlen((string) $value), [18, 19]);
    }

    /**
     * Converts the LDAP timestamp value to a Carbon instance.
     *
     * @throws LdapRecordException
     */
    public function toDateTime(mixed $value): Carbon|int|false
    {
        $value = is_array($value) ? reset($value) : $value;

        if ($value instanceof CarbonInterface || $value instanceof DateTime) {
            return Carbon::instance($value);
        }

        $value = match ($this->type) {
            Timestamp::TYPE_LDAP => $this->convertLdapTimeToDateTime($value),
            Timestamp::TYPE_WINDOWS => $this->convertWindowsTimeToDateTime($value),
            Timestamp::TYPE_WINDOWS_INT => $this->convertWindowsIntegerTimeToDateTime($value),
            default => throw new LdapRecordException("Unrecognized date type [{$this->type}]"),
        };

        return $value instanceof DateTime ? Carbon::instance($value) : $value;
    }

    /**
     * Converts standard LDAP timestamps to a date time object.
     */
    protected function convertLdapTimeToDateTime(string $value): DateTime|false
    {
        return DateTime::createFromFormat(match (true) {
            str_ends_with($value, '.000Z') => 'YmdHis.000\Z',
            str_ends_with($value, '.0Z') => 'YmdHis.0\Z',
            str_ends_with($value, 'Z') => 'YmdHis\Z',
            default => 'YmdHisT',
        }, $value);
    }

    /**
     * Converts date objects to a standard LDAP timestamp.
     */
    protected function convertDateTimeToLdapTime(DateTime $date): string
    {
        return $date->format(
            $date->getOffset() == 0
                ? 'YmdHis\Z'
                : 'YmdHisO'
        );
    }

    /**
     * Converts standard windows timestamps to a date time object.
     */
    protected function convertWindowsTimeToDateTime(string $value): DateTime|false
    {
        return DateTime::createFromFormat(match (true) {
            str_ends_with($value, '.0Z') => 'YmdHis.0\Z',
            default => 'YmdHis.0T'
        }, $value, new DateTimeZone('UTC'));
    }

    /**
     * Converts date objects to a windows timestamp.
     */
    protected function convertDateTimeToWindows(DateTime $date): string
    {
        return $date->format(
            $date->getOffset() == 0
                ? 'YmdHis.0\Z'
                : 'YmdHis.0O'
        );
    }

    /**
     * Converts standard windows integer dates to a date time object.
     *
     * @throws \Exception
     */
    protected function convertWindowsIntegerTimeToDateTime(string|int|null $value = null): DateTime|int|false
    {
        if (is_null($value) || $value === '') {
            return false;
        }

        if ($value == 0) {
            return (int) $value;
        }

        if ($value == static::WINDOWS_INT_MAX) {
            return (int) $value;
        }

        return (new DateTime)->setTimestamp(
            (int) ($value / 10000000) - 11644473600
        );
    }

    /**
     * Converts date objects to a windows integer timestamp.
     */
    protected function convertDateTimeToWindowsInteger(DateTime $date): int
    {
        return ($date->getTimestamp() + 11644473600) * 10000000;
    }
}
