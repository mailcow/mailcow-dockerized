<?php

namespace LdapRecord\Models;

use LdapRecord\LdapRecordException;

class ModelDoesNotExistException extends LdapRecordException
{
    /**
     * The instance of the model that does not exist.
     */
    protected Model $model;

    /**
     * Create a new exception for the given model.
     */
    public static function forModel(Model $model): static
    {
        return (new static)->setModel($model);
    }

    /**
     * Set the model instance that does not exist.
     */
    public function setModel(Model $model): static
    {
        $this->model = $model;

        $class = get_class($model);

        $this->message = "Model [{$class}] does not exist.";

        return $this;
    }

    /**
     * Get the instance of the model that does not exist.
     */
    public function getModel(): Model
    {
        return $this->model;
    }
}
