<?php

namespace LdapRecord\Models\ActiveDirectory;

class ExchangeDatabase extends Entry
{
    /**
     * {@inheritdoc}
     */
    public static array $objectClasses = ['msExchMDB'];

    /**
     * {@inheritdoc}
     */
    public static function boot(): void
    {
        parent::boot();

        static::addGlobalScope(new Scopes\InConfigurationContext);
    }
}
