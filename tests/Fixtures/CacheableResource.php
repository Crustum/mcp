<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Enums\CacheScope;
use Crustum\Mcp\Server\Attributes\Cacheable;
use Crustum\Mcp\Server\Resource;

#[Cacheable(ttlMs: 120000, scope: CacheScope::Public)]
class CacheableResource extends Resource
{
    /**
     * @inheritDoc
     */
    public function description(): string
    {
        return 'A resource that declares its own caching hint';
    }

    /**
     * @inheritDoc
     */
    public function handle(): string
    {
        return 'Cacheable contents.';
    }
}
