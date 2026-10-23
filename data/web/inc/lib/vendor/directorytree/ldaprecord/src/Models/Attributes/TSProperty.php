<?php

namespace LdapRecord\Models\Attributes;

class TSProperty
{
    /**
     * Nibble control values. The first value for each is if the nibble is <= 9, otherwise the second value is used.
     */
    public const NIBBLE_CONTROL = [
        'X' => ['001011', '011010'],
        'Y' => ['001110', '011010'],
    ];

    /**
     * The nibble header.
     */
    public const NIBBLE_HEADER = '1110';

    /**
     * Conversion factor needed for time values in the TSPropertyArray (stored in microseconds).
     */
    public const TIME_CONVERSION = 60 * 1000;

    /**
     * A simple map to help determine how the property needs to be decoded/encoded from/to its binary value.
     *
     * There are some names that are simple repeats but have 'W' at the end. Not sure as to what that signifies. I
     * cannot find any information on them in Microsoft documentation. However, their values appear to stay in sync with
     * their non 'W' counterparts. But not doing so when manipulating the data manually does not seem to affect anything.
     * This probably needs more investigation.
     */
    protected array $propTypes = [
        'string' => [
            'CtxWFHomeDir',
            'CtxWFHomeDirW',
            'CtxWFHomeDirDrive',
            'CtxWFHomeDirDriveW',
            'CtxInitialProgram',
            'CtxInitialProgramW',
            'CtxWFProfilePath',
            'CtxWFProfilePathW',
            'CtxWorkDirectory',
            'CtxWorkDirectoryW',
            'CtxCallbackNumber',
        ],
        'time' => [
            'CtxMaxDisconnectionTime',
            'CtxMaxConnectionTime',
            'CtxMaxIdleTime',
        ],
        'int' => [
            'CtxCfgFlags1',
            'CtxCfgPresent',
            'CtxKeyboardLayout',
            'CtxMinEncryptionLevel',
            'CtxNWLogonServer',
            'CtxShadow',
        ],
    ];

    /**
     * The property name.
     */
    protected ?string $name = null;

    /**
     * The property value.
     */
    protected string|int|null $value = null;

    /**
     * The property value type.
     */
    protected int $valueType = 1;

    /**
     * Pass binary TSProperty data to construct its object representation.
     */
    public function __construct(string|int|null $value = null)
    {
        if ($value) {
            $this->decode(bin2hex($value));
        }
    }

    /**
     * Set the name for the TSProperty.
     */
    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the name for the TSProperty.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set the value for the TSProperty.
     */
    public function setValue(string|int $value): static
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get the value for the TSProperty.
     */
    public function getValue(): string|int|null
    {
        return $this->value;
    }

    /**
     * Convert the TSProperty name/value back to its binary
     * representation for the userParameters blob.
     */
    public function toBinary(): string
    {
        $name = bin2hex($this->name);

        $binValue = $this->getEncodedValueForProp($this->name, $this->value);

        $valueLen = strlen(bin2hex($binValue)) / 3;

        $binary = hex2bin(
            $this->dec2hex(strlen($name))
            .$this->dec2hex($valueLen)
            .$this->dec2hex($this->valueType)
            .$name
        );

        return $binary.$binValue;
    }

    /**
     * Given a TSProperty blob, decode the name/value/type/etc.
     */
    protected function decode(string $tsProperty): void
    {
        $nameLength = hexdec(substr($tsProperty, 0, 2));

        // 1 data byte is 3 encoded bytes
        $valueLength = hexdec(substr($tsProperty, 2, 2)) * 3;

        $this->valueType = hexdec(substr($tsProperty, 4, 2));
        $this->name = pack('H*', substr($tsProperty, 6, $nameLength));
        $this->value = $this->getDecodedValueForProp($this->name, substr($tsProperty, 6 + $nameLength, $valueLength));
    }

    /**
     * Based on the property name/value in question, get its encoded form.
     */
    protected function getEncodedValueForProp(string $propName, string|int $propValue): string
    {
        if (in_array($propName, $this->propTypes['string'])) {
            // Simple strings are null terminated. Unsure if this is
            // needed or simply a product of how ADUC does stuff?
            $value = $this->encodePropValue($propValue."\0", true);
        } elseif (in_array($propName, $this->propTypes['time'])) {
            // Needs to be in microseconds (assuming it is in minute format)...
            $value = $this->encodePropValue($propValue * self::TIME_CONVERSION);
        } else {
            $value = $this->encodePropValue($propValue);
        }

        return $value;
    }

    /**
     * Based on the property name in question, get its actual value from the binary blob value.
     */
    protected function getDecodedValueForProp(string $propName, string $propValue): string|int
    {
        if (in_array($propName, $this->propTypes['string'])) {
            // Strip away null terminators. I think this should
            // be desired, otherwise it just ends in confusion.
            $value = str_replace("\0", '', $this->decodePropValue($propValue, true));
        } elseif (in_array($propName, $this->propTypes['time'])) {
            // Convert from microseconds to minutes (how ADUC displays
            // it anyway, and seems the most practical).
            $value = hexdec($this->decodePropValue($propValue)) / self::TIME_CONVERSION;
        } elseif (in_array($propName, $this->propTypes['int'])) {
            $value = hexdec($this->decodePropValue($propValue));
        } else {
            $value = $this->decodePropValue($propValue);
        }

        return $value;
    }

    /**
     * Decode the property by inspecting the nibbles of each blob, checking
     * the control, and adding up the results into a final value.
     *
     * @param  bool  $string  Whether this is simple string data.
     */
    protected function decodePropValue(string $hex, bool $string = false): string
    {
        $decodePropValue = '';

        $blobs = str_split($hex, 6);

        foreach ($blobs as $blob) {
            $bin = decbin(hexdec($blob));

            $controlY = substr($bin, 4, 6);
            $nibbleY = substr($bin, 10, 4);
            $controlX = substr($bin, 14, 6);
            $nibbleX = substr($bin, 20, 4);

            $byte = $this->nibbleControl($nibbleX, $controlX).$this->nibbleControl($nibbleY, $controlY);

            if ($string) {
                $decodePropValue .= MbString::chr(bindec($byte));
            } else {
                $decodePropValue = $this->dec2hex(bindec($byte)).$decodePropValue;
            }
        }

        return $decodePropValue;
    }

    /**
     * Get the encoded property value as a binary blob.
     */
    protected function encodePropValue(string $value, bool $string = false): string
    {
        // An int must be properly padded. (then split and reversed).
        // For a string, we just split the chars. This seems
        // to be the easiest way to handle UTF-8 characters
        // instead of trying to work with their hex values.
        $chars = $string ? MbString::split($value) : array_reverse(str_split($this->dec2hex($value, 8), 2));

        $encoded = '';

        foreach ($chars as $char) {
            // Get the bits for the char. Using this method to ensure it is fully padded.
            $bits = sprintf('%08b', $string ? MbString::ord($char) : hexdec($char));
            $nibbleX = substr($bits, 0, 4);
            $nibbleY = substr($bits, 4, 4);

            // Construct the value with the header, high nibble, then low nibble.
            $value = self::NIBBLE_HEADER;

            foreach (['Y' => $nibbleY, 'X' => $nibbleX] as $nibbleType => $nibble) {
                $value .= $this->getNibbleWithControl($nibbleType, $nibble);
            }

            // Convert it back to a binary bit stream
            foreach ([0, 8, 16] as $start) {
                $encoded .= $this->packBitString(substr($value, $start, 8), 8);
            }
        }

        return $encoded;
    }

    /**
     * PHP's pack() function has no 'b' or 'B' template. This is
     * a workaround that turns a literal bit-string into a
     * packed byte-string with 8 bits per byte.
     */
    protected function packBitString(string $bits, int $len): string
    {
        $bits = substr($bits, 0, $len);
        // Pad input with zeros to next multiple of 4 above $len
        $bits = str_pad($bits, 4 * (int) (($len + 3) / 4), '0');

        // Split input into chunks of 4 bits, convert each to hex and pack them
        $nibbles = str_split($bits, 4);
        foreach ($nibbles as $i => $nibble) {
            $nibbles[$i] = base_convert($nibble, 2, 16);
        }

        return pack('H*', implode('', $nibbles));
    }

    /**
     * Based on the control, adjust the nibble accordingly.
     */
    protected function nibbleControl(string $nibble, string $control): string
    {
        // This control stays constant for the low/high nibbles,
        // so it doesn't matter which we compare to
        if ($control == self::NIBBLE_CONTROL['X'][1]) {
            $dec = bindec($nibble);
            $dec += 9;
            $nibble = str_pad(decbin($dec), 4, '0', STR_PAD_LEFT);
        }

        return $nibble;
    }

    /**
     * Get the nibble value with the control prefixed.
     *
     * If the nibble dec is <= 9, the control X equals 001011 and Y equals 001110, otherwise if the nibble dec is > 9
     * the control for X or Y equals 011010. Additionally, if the dec value of the nibble is > 9, then the nibble value
     * must be subtracted by 9 before the final value is constructed.
     *
     * @param  string  $nibbleType  Either X or Y
     */
    protected function getNibbleWithControl(string $nibbleType, string $nibble): string
    {
        $dec = bindec($nibble);

        if ($dec > 9) {
            $dec -= 9;
            $control = self::NIBBLE_CONTROL[$nibbleType][1];
        } else {
            $control = self::NIBBLE_CONTROL[$nibbleType][0];
        }

        return $control.sprintf('%04d', decbin($dec));
    }

    /**
     * Need to make sure hex values are always an even length, so pad as needed.
     *
     * @param  int  $padLength  The hex string must be padded to this length (with zeros).
     */
    protected function dec2hex(int $int, int $padLength = 2): string
    {
        return str_pad(dechex($int), $padLength, 0, STR_PAD_LEFT);
    }
}
