<?php

namespace LdapRecord\Models\Events;

use LdapRecord\Models\Model;

abstract class Event
{
    /**
     * The model that the event is being triggered on.
     */
    protected Model $model;

    /**
     * Constructor.
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Get the model that generated the event.
     */
    public function getModel(): Model
    {
        return $this->model;
    }
}
