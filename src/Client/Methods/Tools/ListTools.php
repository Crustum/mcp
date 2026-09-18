<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Methods\Tools;

use Cake\Collection\Collection;
use Cake\Log\Log;
use Crustum\Mcp\Client;
use Crustum\Mcp\Client\Contracts\Method;
use Crustum\Mcp\Client\Primitives\Tool;
use Crustum\Mcp\Client\Trait\PaginatesListTrait;
use Crustum\Mcp\Exception\MirroredParameterException;

/**
 * MCP tools/list JSON-RPC method.
 *
 * @implements \Crustum\Mcp\Client\Contracts\Method<\Cake\Collection\Collection<string, \Crustum\Mcp\Client\Primitives\Tool>>
 */
final class ListTools implements Method
{
    use PaginatesListTrait;

    /**
     * Create a new tools/list method.
     *
     * @param \Crustum\Mcp\Client|null $client Bound MCP client
     * @param string|null $cursor List cursor
     * @param int|null $limit Maximum number of tools to fetch
     */
    public function __construct(
        protected ?Client $client = null,
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
        return 'tools';
    }

    /**
     * @inheritDoc
     */
    protected function nextPage(?string $cursor): static
    {
        return new self($this->client, $cursor, $this->limit);
    }

    /**
     * @inheritDoc
     */
    protected function hydrate(array $payloads): Collection
    {
        $tools = [];

        foreach ($payloads as $payload) {
            try {
                $tool = Tool::from($this->client, $payload);
            } catch (MirroredParameterException $mirroredParameterException) {
                $name = is_string($payload['name'] ?? null) ? $payload['name'] : 'unknown';

                Log::warning("Excluded the MCP tool [{$name}] from the tool list because {$mirroredParameterException->getMessage()}.");

                continue;
            }

            $tools[$tool->name] = $tool;
        }

        return new Collection($tools);
    }
}
