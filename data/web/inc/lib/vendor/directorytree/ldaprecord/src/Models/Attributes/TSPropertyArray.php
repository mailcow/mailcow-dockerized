<?php

namespace LdapRecord\Models\Attributes;

use InvalidArgumentException;

class TSPropertyArray
{
    /**
     * Represents that the TSPropertyArray data is valid.
     */
    public const VALID_SIGNATURE = 'P';

    /**
     * The default values for the TSPropertyArray structure.
     *
     * @var array
     */
    public const DEFAULTS = [
        'CtxCfgPresent' => 2953518677,
        'CtxWFProfilePath' => '',
        'CtxWFProfilePathW' => '',
        'CtxWFHomeDir' => '',
        'CtxWFHomeDirW' => '',
        'CtxWFHomeDirDrive' => '',
        'CtxWFHomeDirDriveW' => '',
        'CtxShadow' => 1,
        'CtxMaxDisconnectionTime' => 0,
        'CtxMaxConnectionTime' => 0,
        'CtxMaxIdleTime' => 0,
        'CtxWorkDirectory' => '',
        'CtxWorkDirectoryW' => '',
        'CtxCfgFlags1' => 2418077696,
        'CtxInitialProgram' => '',
        'CtxInitialProgramW' => '',
    ];

    /**
     * The default data that occurs before the TSPropertyArray (CtxCfgPresent with a bunch of spaces...?).
     */
    protected string $defaultPreBinary = '43747843666750726573656e742020202020202020202020202020202020202020202020202020202020202020202020';

    /**
     * The TSProperty array.
     *
     * @var TSProperty[]
     */
    protected array $tsProperty = [];

    /**
     * The TSProperty signature.
     */
    protected string $signature = self::VALID_SIGNATURE;

    /**
     * Binary data that occurs before the TSPropertyArray data in userParameters.
     */
    protected string $preBinary = '';

    /**
     * Binary data that occurs after the TSPropertyArray data in userParameters.
     */
    protected string $postBinary = '';

    /**
     * Construct in one of the following ways:.
     *
     *   - Pass an array of TSProperty key => value pairs (See DEFAULTS constant).
     *   - Pass the userParameters binary value. The object representation of that will be decoded and constructed.
     *   - Pass nothing and a default set of TSProperty key => value pairs will be used (See DEFAULTS constant).
     */
    public function __construct(mixed $tsPropertyArray = null)
    {
        $this->preBinary = hex2bin($this->defaultPreBinary);

        if (is_null($tsPropertyArray) || is_array($tsPropertyArray)) {
            $tsPropertyArray = $tsPropertyArray ?: self::DEFAULTS;

            foreach ($tsPropertyArray as $key => $value) {
                $tsProperty = new TSProperty;

                $this->tsProperty[$key] = $tsProperty->setName($key)->setValue($value);
            }
        } else {
            $this->decodeUserParameters($tsPropertyArray);
        }
    }

    /**
     * Check if a specific TSProperty exists by its property name.
     */
    public function has(string $propName): bool
    {
        return array_key_exists(strtolower($propName), array_change_key_case($this->tsProperty));
    }

    /**
     * Get a TSProperty object by its property name (ie. CtxWFProfilePath).
     */
    public function get(string $propName): TSProperty
    {
        $this->validateProp($propName);

        return $this->getTsPropObj($propName);
    }

    /**
     * Add a TSProperty object. If it already exists, it will be overwritten.
     */
    public function add(TSProperty $tsProperty): static
    {
        $this->tsProperty[$tsProperty->getName()] = $tsProperty;

        return $this;
    }

    /**
     * Remove a TSProperty by its property name (i.e. CtxMinEncryptionLevel).
     */
    public function remove(string $propName): static
    {
        foreach (array_keys($this->tsProperty) as $property) {
            if (strtolower($propName) == strtolower($property)) {
                unset($this->tsProperty[$property]);
            }
        }

        return $this;
    }

    /**
     * Set the value for a specific TSProperty by its name.
     */
    public function set(string $propName, string|int $propValue): static
    {
        $this->validateProp($propName);

        $this->getTsPropObj($propName)->setValue($propValue);

        return $this;
    }

    /**
     * Get the full binary representation of the userParameters containing the TSPropertyArray data.
     */
    public function toBinary(): string
    {
        $binary = $this->preBinary;

        $binary .= hex2bin(str_pad(dechex(MbString::ord($this->signature)), 2, 0, STR_PAD_LEFT));

        $binary .= hex2bin(str_pad(dechex(count($this->tsProperty)), 2, 0, STR_PAD_LEFT));

        foreach ($this->tsProperty as $tsProperty) {
            $binary .= $tsProperty->toBinary();
        }

        return $binary.$this->postBinary;
    }

    /**
     * Get a simple associative array containing of all TSProperty names and values.
     */
    public function toArray(): array
    {
        $userParameters = [];

        foreach ($this->tsProperty as $property => $tsPropObj) {
            $userParameters[$property] = $tsPropObj->getValue();
        }

        return $userParameters;
    }

    /**
     * Get all TSProperty objects.
     *
     * @return TSProperty[]
     */
    public function getTSProperties(): array
    {
        return $this->tsProperty;
    }

    /**
     * Validates that the given property name exists.
     */
    protected function validateProp(string $propName): void
    {
        if (! $this->has($propName)) {
            throw new InvalidArgumentException(sprintf('TSProperty for "%s" does not exist.', $propName));
        }
    }

    /**
     * Get the TS property object for the given property.
     */
    protected function getTsPropObj(string $propName): TSProperty
    {
        return array_change_key_case($this->tsProperty)[strtolower($propName)];
    }

    /**
     * Get an associative array with all the userParameters property names and values.
     */
    protected function decodeUserParameters(string $userParameters): void
    {
        $userParameters = bin2hex($userParameters);

        // Save the 96-byte array of reserved data, so as to not ruin anything that may be stored there.
        $this->preBinary = hex2bin(substr($userParameters, 0, 96));
        // The signature is a 2-byte unicode character at the front
        $this->signature = MbString::chr(hexdec(substr($userParameters, 96, 2)));
        // This asserts the validity of the tsPropertyArray data. For some reason 'P' means valid...
        if ($this->signature != self::VALID_SIGNATURE) {
            throw new InvalidArgumentException('Invalid TSPropertyArray data');
        }

        // The property count is a 2-byte unsigned integer indicating the number of elements for the tsPropertyArray
        // It starts at position 98. The actual variable data begins at position 100.
        $length = $this->addTSPropData(substr($userParameters, 100), hexdec(substr($userParameters, 98, 2)));

        // Reserved data length + (count and sig length == 4) + the added lengths of the TSPropertyArray
        // This saves anything after that variable TSPropertyArray data, so as to not squash anything stored there
        if (strlen($userParameters) > (96 + 4 + $length)) {
            $this->postBinary = hex2bin(substr($userParameters, 96 + 4 + $length));
        }
    }

    /**
     * Given the start of TSPropertyArray hex data, and the count for the number
     * of TSProperty structures in contains, parse and split out the
     * individual TSProperty structures. Return the full length
     * of the TSPropertyArray data.
     *
     * @return int The length of the data in the TSPropertyArray
     */
    protected function addTSPropData(string $tsPropertyArray, int $tsPropCount): int
    {
        $length = 0;

        for ($i = 0; $i < $tsPropCount; $i++) {
            // Prop length = name length + value length + type length + the space for the length data.
            $propLength = hexdec(substr($tsPropertyArray, $length, 2)) + (hexdec(substr($tsPropertyArray, $length + 2, 2)) * 3) + 6;

            $tsProperty = new TSProperty(hex2bin(substr($tsPropertyArray, $length, $propLength)));

            $this->tsProperty[$tsProperty->getName()] = $tsProperty;

            $length += $propLength;
        }

        return $length;
    }
}
