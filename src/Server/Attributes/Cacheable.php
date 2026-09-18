<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Attributes;

use Attribute;
use Crustum\Mcp\Enums\CacheScope;

/**
 * Declares caching hints for a cacheable MCP result.
 *
 * @attribute
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Cacheable
{
    /**
     * Create a new cacheable attribute.
     *
     * @param int $ttlMs Time-to-live in milliseconds
     * @param \Crustum\Mcp\Enums\CacheScope $scope Caching scope
     */
    public function __construct(
        public int $ttlMs = 0,
        public CacheScope $scope = CacheScope::Private,
    ) {
    }

    /**
     * Get the caching hint as an array.
     *
     * @return array{ttlMs: int, cacheScope: string}
     */
    public function toArray(): array
    {
        return [
            'ttlMs' => max(0, $this->ttlMs),
            'cacheScope' => $this->scope->value,
        ];
    }
}
