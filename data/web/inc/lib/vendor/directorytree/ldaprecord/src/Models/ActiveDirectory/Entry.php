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
     */
    protected array $defaultDates = [
        'whenchanged' => 'windows',
        'whencreated' => 'windows',
        'dscorepropagationdata' => 'windows',
    ];

    /**
     * The attribute key that contains the Object SID.
     */
    protected string $sidKey = 'objectsid';

    /**
     * {@inheritdoc}
     */
    public function getObjectSidKey(): string
    {
        return $this->sidKey;
    }

    /**
     * {@inheritdoc}
     */
    public function getObjectSid(): ?string
    {
        return $this->getFirstAttribute($this->sidKey);
    }

    /**
     * {@inheritdoc}
     */
    public function getConvertedSid($sid = null): ?string
    {
        try {
            return $this->newObjectSid(
                (string) ($sid ?? $this->getObjectSid())
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getBinarySid($sid = null): ?string
    {
        try {
            return $this->newObjectSid(
                $sid ?? $this->getObjectSid()
            )->getBinary();
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Make a new object Sid instance.
     */
    protected function newObjectSid(string $value): Sid
    {
        return new Sid($value);
    }

    /**
     * Create a new query builder.
     */
    public function newQueryBuilder(Connection $connection): ActiveDirectoryBuilder
    {
        return new ActiveDirectoryBuilder($connection);
    }

    /**
     * Determine if the object is deleted.
     */
    public function isDeleted(): bool
    {
        return strtoupper((string) $this->getFirstAttribute('isDeleted')) === 'TRUE';
    }

    /**
     * Restore a deleted object.
     *
     * @throws \LdapRecord\LdapRecordException
     */
    public function restore(?string $newParentDn = null): bool
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

        return true;
    }

    /**
     * Get the objects restore location.
     */
    protected function getDefaultRestoreLocation(): ?string
    {
        return $this->getFirstAttribute('lastKnownParent') ?? $this->getParentDn($this->getParentDn($this->getDn()));
    }

    /**
     * Convert the attributes for JSON serialization.
     */
    protected function convertAttributesForJson(array $attributes = []): array
    {
        $attributes = parent::convertAttributesForJson($attributes);

        // If the model has a SID set, we need to convert it to its
        // string format, due to it being in binary. Otherwise,
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
     */
    protected function convertAttributesFromJson(array $attributes = []): array
    {
        $attributes = parent::convertAttributesFromJson($attributes);

        if (isset($attributes[$this->sidKey])) {
            $attributes[$this->sidKey] = [$this->getBinarySid(
                Arr::first($attributes[$this->sidKey])
            )];
        }

        return $attributes;
    }
}
