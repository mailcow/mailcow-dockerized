<?php

namespace LdapRecord\Models\ActiveDirectory\Relations;

use LdapRecord\Models\Model;
use LdapRecord\Models\Relations\HasOne;

class HasOnePrimaryGroup extends HasOne
{
    /**
     * Get the foreign model by the given value.
     */
    protected function getForeignModelByValue(string $value): ?Model
    {
        return $this->query->findBySid(
            $this->getParentModelObjectSid()
        );
    }

    /**
     * Get the foreign value from the given model.
     *
     * Retrieves the last RID from the models Object SID.
     */
    protected function getForeignValueFromModel(Model $model): ?string
    {
        $objectSidComponents = explode('-', $model->getConvertedSid());

        return end($objectSidComponents);
    }

    /**
     * Get the parent relationship models converted object sid.
     */
    protected function getParentModelObjectSid(): string
    {
        return preg_replace(
            '/\d+$/',
            $this->parent->getFirstAttribute($this->relationKey),
            $this->parent->getConvertedSid()
        );
    }
}
