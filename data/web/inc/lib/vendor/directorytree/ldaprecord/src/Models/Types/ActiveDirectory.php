<?php

namespace LdapRecord\Models\Types;

interface ActiveDirectory extends TypeInterface
{
    /**
     * Get the models object SID key.
     */
    public function getObjectSidKey(): string;

    /**
     * Get the model's hex object SID.
     *
     * @see https://msdn.microsoft.com/en-us/library/ms679024(v=vs.85).aspx
     */
    public function getObjectSid(): ?string;

    /**
     * Get the model's SID.
     */
    public function getConvertedSid(?string $sid = null): ?string;

    /**
     * Get the model's binary SID.
     */
    public function getBinarySid(?string $sid = null): ?string;
}
