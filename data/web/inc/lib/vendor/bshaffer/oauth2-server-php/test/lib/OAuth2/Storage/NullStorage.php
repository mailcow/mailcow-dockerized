<?php

namespace OAuth2\Storage;

/**
*
*/
class NullStorage extends Memory
{
    private $name;
    private $description;

    public function __construct($name, $description = null)
    {
        $this->name = $name;
        $this->description = $description;
    }

    public function __toString()
    {
        return $this->name;
    }

    public function getMessage()
    {
        if ($this->description) {
             return $this->description;
        }

        return $this->name;
    }
}
