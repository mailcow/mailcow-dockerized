<?php

namespace LdapRecord\Configuration\Validators;

class IntegerValidator extends Validator
{
    /**
     * The validation exception message.
     *
     * @var string
     */
    protected $message = 'Option [:option] must be an integer.';

    /**
     * @inheritdoc
     */
    public function passes()
    {
        return is_numeric($this->value);
    }
}
