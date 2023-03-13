<?php

namespace Stevenmaguire\OAuth2\Client\Provider\Exception;

use Exception;

class EncryptionConfigurationException extends Exception
{
    /**
     * Returns properly formatted exception when response decryption fails.
     *
     * @return \Stevenmaguire\OAuth2\Client\Provider\Exception\EncryptionConfigurationException
     */
    public static function undeterminedEncryption()
    {
        return new static(
            'The given response may be encrypted and sufficient '.
            'encryption configuration has not been provided.',
            400
        );
    }
}
