<?php

namespace LdapRecord\Configuration\Validators;

class BooleanValidator extends Validator
{
    /**
     * The validation exception message.
     */
    protected string $message = 'Option [:option] must be a boolean.';

    /**
     * {@inheritdoc}
     */
    public function passes(): bool
    {
        return is_bool($this->value);
    }
}
