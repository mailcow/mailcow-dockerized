<?php

namespace LdapRecord\Configuration\Validators;

class StringOrNullValidator extends Validator
{
    /**
     * The validation exception message.
     */
    protected string $message = 'Option [:option] must be a string or null.';

    /**
     * {@inheritdoc}
     */
    public function passes(): bool
    {
        return is_string($this->value) || is_null($this->value);
    }
}
