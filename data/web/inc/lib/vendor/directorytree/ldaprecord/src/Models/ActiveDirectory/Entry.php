<?php

namespace LdapRecord\Models\ActiveDirectory;

use InvalidArgumentException;
use LdapRecord\Connection;
use LdapRecord\Models\Attributes\Sid;
use LdapRecord\Models\Entry as BaseEntry;
use LdapRecord\Models\Events\Updated;
use LdapRecord\Models\Types\ActiveDirectory;
use LdapRecord\Query\Model\ActiveDirectoryBuilder;
use LdapRecord\Support\Arr;

/** @mixin ActiveDirectoryBuilder */
class Entry extends BaseEntry implements ActiveDirectory
{
    /**
     * The default attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $defaultDates = [
        'whenchanged' => 'windows',
        'whencreated' => 'windows',
        'dscorepropagationdata' => 'windows',
    ];

    /**
     * The attribute key that contains the Object SID.
     *
     * @var string
     */
    protected $sidKey = 'objectsid';

    /**
     * @inheritdoc
     */
    public function getObjectSidKey()
    {
        return $this->sidKey;
    }

    /**
     * @inheritdoc
     */
    public function getObjectSid()
    {
        return $this->getFirstAttribute($this->sidKey);
    }

    /**
     * @inheritdoc
     */
    public function getConvertedSid($sid = null)
    {
        try {
            return (string) $this->newObjectSid(
                $sid ?? $this->getObjectSid()
            );
        } catch (InvalidArgumentException $e) {
            return;
        }
    }

    /**
     * @inheritdoc
     */
    public function getBinarySid($sid = null)
    {
        try {
            return $this->newObjectSid(
                $sid ?? $this->getObjectSid()
            )->getBinary();
        } catch (InvalidArgumentException $e) {
            return;
        }
    }

    /**
     * Make a new object Sid instance.
     *
     * @param  string  $value
     * @return Sid
     */
    protected function newObjectSid($value)
    {
        return new Sid($value);
    }

    /**
     * Create a new query builder.
     *
     * @param  Connection  $connection
     * @return ActiveDirectoryBuilder
     */
    public function newQueryBuilder(Connection $connection)
    {
        return new ActiveDirectoryBuilder($connection);
    }

    /**
     * Determine if the object is deleted.
     *
     * @return bool
     */
    public function isDeleted()
    {
        return strtoupper((string) $this->getFirstAttribute('isDeleted')) === 'TRUE';
    }

    /**
     * Restore a deleted object.
     *
     * @param  string|null  $newParentDn
     * @return bool
     *
     * @throws \LdapRecord\LdapRecordException
     */
    public function restore($newParentDn = null)
    {
        if (! $this->isDeleted()) {
            return false;
        }

        $root = $newParentDn ?? $this->getDefaultRestoreLocation();
        $rdn = explode('\0A', $this->getDn(), 2)[0];
        $newDn = implode(',', [$rdn, $root]);

        // We will initialize a model listener for the "updated" event to set
        // the models distinguished name so all attributes are synchronized
        // properly after the model has been successfully restored.
        $this->listenForModelEvent(Updated::class, function (Updated $event) use ($newDn) {
            if ($this->is($event->getModel())) {
                $this->setDn($newDn);
            }
        });

        $this->setRawAttribute('distinguishedname', $newDn);

        $this->save(['isDeleted' => null]);
    }

    /**
     * Get the objects restore location.
     *
     * @return string
     */
    protected function getDefaultRestoreLocation()
    {
        return $this->getFirstAttribute('lastKnownParent') ?? $this->getParentDn($this->getParentDn($this->getDn()));
    }

    /**
     * Convert the attributes for JSON serialization.
     *
     * @param  array  $attributes
     * @return array
     */
    protected function convertAttributesForJson(array $attributes = [])
    {
        $attributes = parent::convertAttributesForJson($attributes);

        // If the model has a SID set, we need to convert it to its
        // string format, due to it being in binary. Otherwise
        // we will receive a JSON serialization exception.
        if (isset($attributes[$this->sidKey])) {
            $attributes[$this->sidKey] = [$this->getConvertedSid(
                Arr::first($attributes[$this->sidKey])
            )];
        }

        return $attributes;
    }

    /**
     * Convert the attributes from JSON serialization.
     *
     * @param  array  $attributes
     * @return array
     */
    protected function convertAttributesFromJson(array $attributes = [])
    {
        $attributes = parent::convertAttributesFromJson($attributes);

        // Here we are converting the model's GUID and SID attributes
        // back to their original values from serialization, so that
        // their original value may be used and compared against.
        if (isset($attributes[$this->guidKey])) {
            $attributes[$this->guidKey] = [$this->getBinaryGuid(
                Arr::first($attributes[$this->guidKey])
            )];
        }

        if (isset($attributes[$this->sidKey])) {
            $attributes[$this->sidKey] = [$this->getBinarySid(
                Arr::first($attributes[$this->sidKey])
            )];
        }

        return $attributes;
    }
}
