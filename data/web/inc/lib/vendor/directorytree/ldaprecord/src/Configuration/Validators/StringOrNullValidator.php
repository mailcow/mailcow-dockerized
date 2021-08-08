<?php

namespace LdapRecord\Configuration\Validators;

class StringOrNullValidator extends Validator
{
    /**
     * The validation exception message.
     *
     * @var string
     */
    protected $message = 'Option [:option] must be a string or null.';

    /**
     * @inheritdoc
     */
    public function passes()
    {
        return is_string($this->value) || is_null($this->value);
    }
}
