<?php

namespace LdapRecord\Models\Types;

interface ActiveDirectory extends TypeInterface
{
    /**
     * Returns the models object SID key.
     *
     * @return string
     */
    public function getObjectSidKey();

    /**
     * Returns the model's hex object SID.
     *
     * @see https://msdn.microsoft.com/en-us/library/ms679024(v=vs.85).aspx
     *
     * @return string
     */
    public function getObjectSid();

    /**
     * Returns the model's SID.
     *
     * @param  string|null  $sid
     * @return string|null
     */
    public function getConvertedSid($sid = null);

    /**
     * Returns the model's binary SID.
     *
     * @param  string|null  $sid
     * @return string|null
     */
    public function getBinarySid($sid = null);
}
