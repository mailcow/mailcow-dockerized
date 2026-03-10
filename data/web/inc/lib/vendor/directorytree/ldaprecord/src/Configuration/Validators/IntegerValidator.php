<?php

namespace LdapRecord\Configuration\Validators;

class IntegerValidator extends Validator
{
    /**
     * The validation exception message.
     */
    protected string $message = 'Option [:option] must be an integer.';

    /**
     * {@inheritdoc}
     */
    public function passes(): bool
    {
        return is_numeric($this->value);
    }
}
