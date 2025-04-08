<?php

namespace OAuth2\Encryption;

interface EncryptionInterface
{
    /**
     * @param $payload
     * @param $key
     * @param null $algorithm
     * @return mixed
     */
    public function encode($payload, $key, $algorithm = null);

    /**
     * @param $payload
     * @param $key
     * @param null $algorithm
     * @return mixed
     */
    public function decode($payload, $key, $algorithm = null);

    /**
     * @param $data
     * @return mixed
     */
    public function urlSafeB64Encode($data);

    /**
     * @param $b64
     * @return mixed
     */
    public function urlSafeB64Decode($b64);
}
