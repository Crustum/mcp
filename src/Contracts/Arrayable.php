<?php
declare(strict_types=1);

namespace Crustum\Mcp\Contracts;

/**
 * Array conversion contract for MCP value objects.
 *
 * @template TKey of array-key
 * @template TValue
 */
interface Arrayable
{
    /**
     * Get the instance as an array.
     *
     * @return array<TKey, TValue>
     */
    public function toArray(): array;
}
