<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Trait;

use Cake\Collection\Collection;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Crustum\Mcp\Client\Protocol;
use Crustum\Mcp\Exception\ClientException;

/**
 * Paginates MCP list endpoints until all items are fetched.
 */
trait PaginatesListTrait
{
    /**
     * Current list cursor.
     *
     * @var string|null
     */
    protected ?string $cursor = null;

    /**
     * Maximum number of items to fetch.
     *
     * @var int|null
     */
    protected ?int $limit = null;

    /**
     * Get the plural primitive name used as the list key and method prefix.
     *
     * @return string
     */
    abstract protected function listType(): string;

    /**
     * Build the request for the next page at the given cursor.
     *
     * @param string|null $cursor List cursor
     * @return static
     */
    abstract protected function nextPage(?string $cursor): static;

    /**
     * Hydrate list payloads into primitive objects keyed by identifier.
     *
     * @param array<int, array<string, mixed>> $payloads Raw list payloads
     * @return \Cake\Collection\Collection<string, mixed>
     */
    abstract protected function hydrate(array $payloads): Collection;

    /**
     * Get the JSON-RPC method name for the list request.
     *
     * @return string
     */
    public function method(): string
    {
        return "{$this->listType()}/list";
    }

    /**
     * Get JSON-RPC parameters for the current list page.
     *
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return $this->cursor === null ? [] : ['cursor' => $this->cursor];
    }

    /**
     * Fetch and hydrate all list pages.
     *
     * @param \Crustum\Mcp\Client\Protocol $protocol Client protocol instance
     * @return \Cake\Collection\Collection<string, mixed>
     */
    public function handle(Protocol $protocol): Collection
    {
        return $this->hydrate($this->fetch($protocol));
    }

    /**
     * Fetch raw list payloads across all pages.
     *
     * @param \Crustum\Mcp\Client\Protocol $protocol Client protocol instance
     * @return array<int, array<string, mixed>>
     */
    protected function fetch(Protocol $protocol): array
    {
        $type = $this->listType();
        $singular = Inflector::singularize($type);

        if ($this->limit === 0) {
            return [];
        }

        if ($this->limit !== null && $this->limit < 0) {
            throw new ClientException(ucfirst($singular) . ' list limit must be greater than or equal to zero.');
        }

        $payloads = [];
        $cursor = $this->cursor;
        $seenCursors = [];

        while (true) {
            if ($cursor !== null && $cursor !== '') {
                if (isset($seenCursors[$cursor])) {
                    throw new ClientException("Repeated {$type}/list cursor [{$cursor}] received from server.");
                }

                $seenCursors[$cursor] = true;
            }

            $result = $protocol->dispatch($this->nextPage($cursor));
            $page = Hash::get($result, $type);

            if (!is_array($page)) {
                throw new ClientException("Invalid {$type}/list response from server.");
            }

            foreach ($page as $payload) {
                if (!is_array($payload)) {
                    throw new ClientException("Invalid {$singular} payload from server.");
                }

                if ($this->limit !== null && count($payloads) >= $this->limit) {
                    return $payloads;
                }

                $payloads[] = $payload;
            }

            $next = Hash::get($result, 'nextCursor');

            if ($next !== null && !is_string($next)) {
                throw new ClientException("Invalid {$type}/list cursor from server.");
            }

            if ($next === null || $next === '') {
                return $payloads;
            }

            $cursor = $next;
        }
    }
}
