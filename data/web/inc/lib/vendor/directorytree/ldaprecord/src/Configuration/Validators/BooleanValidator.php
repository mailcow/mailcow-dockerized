<?php

namespace LdapRecord\Configuration\Validators;

class BooleanValidator extends Validator
{
    /**
     * The validation exception message.
     *
     * @var string
     */
    protected $message = 'Option [:option] must be a boolean.';

    /**
     * @inheritdoc
     */
    public function passes()
    {
        return is_bool($this->value);
    }
}
