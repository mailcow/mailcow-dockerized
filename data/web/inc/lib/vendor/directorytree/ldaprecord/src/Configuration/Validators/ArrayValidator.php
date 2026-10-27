<?php

namespace LdapRecord\Configuration\Validators;

class ArrayValidator extends Validator
{
    /**
     * The validation exception message.
     */
    protected string $message = 'Option [:option] must be an array.';

    /**
     * {@inheritdoc}
     */
    public function passes(): bool
    {
        return is_array($this->value);
    }
}
