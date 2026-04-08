<?php

namespace LdapRecord\Query;

use Illuminate\Support\Collection as BaseCollection;
use LdapRecord\Models\Model;

class Collection extends BaseCollection
{
    /**
     * {@inheritdoc}
     */
    protected function valueRetriever(mixed $value): callable
    {
        if ($this->useAsCallable($value)) {
            /** @var callable $value */
            return $value;
        }

        return fn ($item) => $item instanceof Model
                ? $item->getFirstAttribute($value)
                : data_get($item, $value);
    }
}
