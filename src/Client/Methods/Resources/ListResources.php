<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Methods\Resources;

use Cake\Collection\Collection;
use Crustum\Mcp\Client\Contracts\Method;
use Crustum\Mcp\Client\Primitives\Resource;
use Crustum\Mcp\Client\Trait\PaginatesListTrait;

/**
 * MCP resources/list JSON-RPC method.
 *
 * @implements \Crustum\Mcp\Client\Contracts\Method<\Cake\Collection\Collection<string, \Crustum\Mcp\Client\Primitives\Resource>>
 */
final class ListResources implements Method
{
    use PaginatesListTrait;

    /**
     * Create a new resources/list method.
     *
     * @param string|null $cursor List cursor
     * @param int|null $limit Maximum number of resources to fetch
     */
    public function __construct(
        ?string $cursor = null,
        ?int $limit = null,
    ) {
        $this->cursor = $cursor;
        $this->limit = $limit;
    }

    /**
     * @inheritDoc
     */
    protected function listType(): string
    {
        return 'resources';
    }

    /**
     * @inheritDoc
     */
    protected function nextPage(?string $cursor): static
    {
        return new self($cursor, $this->limit);
    }

    /**
     * @inheritDoc
     */
    protected function hydrate(array $payloads): Collection
    {
        $resources = [];

        foreach ($payloads as $payload) {
            $resource = Resource::from($payload);
            $resources[$resource->uri] = $resource;
        }

        return new Collection($resources);
    }
}
