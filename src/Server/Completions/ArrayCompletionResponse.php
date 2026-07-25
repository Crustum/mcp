<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Completions;

/**
 * Completion response backed by a static array of values.
 */
class ArrayCompletionResponse extends CompletionResponse
{
    /**
     * @param array<int, string> $items Completion source values
     */
    public function __construct(private array $items)
    {
        parent::__construct([]);
    }

    /**
     * Resolve completion values for the current partial input.
     *
     * @param string $value Current partial value
     * @return \Crustum\Mcp\Server\Completions\CompletionResponse
     */
    public function resolve(string $value): CompletionResponse
    {
        $filtered = CompletionHelper::filterByPrefix($this->items, $value);
        $hasMore = count($filtered) > static::MAX_VALUES;
        $truncated = array_slice($filtered, 0, static::MAX_VALUES);

        return new DirectCompletionResponse($truncated, $hasMore);
    }
}
