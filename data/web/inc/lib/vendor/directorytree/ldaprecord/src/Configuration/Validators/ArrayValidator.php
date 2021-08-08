<?php

namespace LdapRecord\Configuration\Validators;

class ArrayValidator extends Validator
{
    /**
     * The validation exception message.
     *
     * @var string
     */
    protected $message = 'Option [:option] must be an array.';

    /**
     * @inheritdoc
     */
    public function passes()
    {
        return is_array($this->value);
    }
}
