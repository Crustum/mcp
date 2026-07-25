<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Methods\Prompts;

use Cake\Collection\Collection;
use Crustum\Mcp\Client\Contracts\Method;
use Crustum\Mcp\Client\Primitives\Prompt;
use Crustum\Mcp\Client\Trait\PaginatesListTrait;

/**
 * MCP prompts/list JSON-RPC method.
 *
 * @implements \Crustum\Mcp\Client\Contracts\Method<\Cake\Collection\Collection<string, \Crustum\Mcp\Client\Primitives\Prompt>>
 */
final class ListPrompts implements Method
{
    use PaginatesListTrait;

    /**
     * Create a new prompts/list method.
     *
     * @param string|null $cursor List cursor
     * @param int|null $limit Maximum number of prompts to fetch
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
        return 'prompts';
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
        $prompts = [];

        foreach ($payloads as $payload) {
            $prompt = Prompt::from($payload);
            $prompts[$prompt->name] = $prompt;
        }

        return new Collection($prompts);
    }
}
