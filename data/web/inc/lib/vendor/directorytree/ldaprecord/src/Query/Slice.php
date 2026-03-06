<?php

namespace LdapRecord\Query;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;

class Slice implements ArrayAccess, IteratorAggregate, JsonSerializable
{
    /**
     * All the items in the slice.
     */
    protected Collection|array $items;

    /**
     * The number of items to be shown per page.
     */
    protected int $perPage;

    /**
     * The total number of items before slicing.
     */
    protected int $total;

    /**
     * The last available page.
     */
    protected int $lastPage;

    /**
     * The current page being "viewed".
     */
    protected int $currentPage;

    /**
     * Constructor.
     */
    public function __construct(Collection|array $items, int $total, int $perPage, ?int $currentPage = null)
    {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->lastPage = max((int) ceil($total / $perPage), 1);
        $this->currentPage = $currentPage ?? 1;
    }

    /**
     * Get the slice of items being paginated.
     */
    public function items(): Collection|array
    {
        return $this->items;
    }

    /**
     * Get the total number of items being paginated.
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Get the number of items shown per page.
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Determine if there are more items in the data source.
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage() < $this->lastPage();
    }

    /**
     * Determine if there are enough items to split into multiple pages.
     */
    public function hasPages(): bool
    {
        return $this->currentPage() != 1 || $this->hasMorePages();
    }

    /**
     * Determine if the paginator is on the first page.
     */
    public function onFirstPage(): bool
    {
        return $this->currentPage() <= 1;
    }

    /**
     * Determine if the paginator is on the last page.
     */
    public function onLastPage(): bool
    {
        return ! $this->hasMorePages();
    }

    /**
     * Get the current page.
     */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Get the last page.
     */
    public function lastPage(): int
    {
        return $this->lastPage;
    }

    /**
     * Get an iterator for the items.
     */
    #[\ReturnTypeWillChange]
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Determine if the list of items is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Determine if the list of items is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Get the number of items for the current page.
     */
    #[\ReturnTypeWillChange]
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Determine if the given item exists.
     */
    #[\ReturnTypeWillChange]
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->items);
    }

    /**
     * Get the item at the given offset.
     */
    #[\ReturnTypeWillChange]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * Set the item at the given offset.
     */
    #[\ReturnTypeWillChange]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->items[$offset] = $value;
    }

    /**
     * Unset the item at the given key.
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * Convert the object into something JSON serializable.
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Get the arrayable items.
     */
    public function getArrayableItems(): array
    {
        return $this->items instanceof Collection
            ? $this->items->all()
            : $this->items;
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage(),
            'data' => $this->getArrayableItems(),
            'last_page' => $this->lastPage(),
            'per_page' => $this->perPage(),
            'total' => $this->total(),
        ];
    }
}
