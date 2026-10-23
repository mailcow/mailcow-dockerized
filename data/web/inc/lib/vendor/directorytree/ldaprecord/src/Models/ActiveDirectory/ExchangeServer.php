<?php

namespace LdapRecord\Models\ActiveDirectory;

class ExchangeServer extends Entry
{
    /**
     * {@inheritdoc}
     */
    public static array $objectClasses = ['msExchExchangeServer'];

    /**
     * {@inheritdoc}
     */
    public static function boot(): void
    {
        parent::boot();

        static::addGlobalScope(new Scopes\HasServerRoleAttribute);
        static::addGlobalScope(new Scopes\InConfigurationContext);
    }
}
