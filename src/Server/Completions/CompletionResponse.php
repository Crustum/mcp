<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Completions;

use Crustum\Mcp\Contracts\Arrayable;
use InvalidArgumentException;

/**
 * Base completion response value object.
 *
 * @implements \Crustum\Mcp\Contracts\Arrayable<string, mixed>
 */
abstract class CompletionResponse implements Arrayable
{
    /**
     * Maximum number of completion values returned in one response.
     */
    protected const MAX_VALUES = 100;

    /**
     * @param array<int, string> $values Completion values
     * @param bool $hasMore Whether additional values are available
     */
    public function __construct(
        protected array $values,
        protected bool $hasMore = false,
    ) {
        if (count($values) > static::MAX_VALUES) {
            throw new InvalidArgumentException(
                sprintf(
                    'Completion values cannot exceed %d items (received %d)',
                    static::MAX_VALUES,
                    count($values),
                ),
            );
        }
    }

    /**
     * Create an empty completion response.
     *
     * @return self
     */
    public static function empty(): self
    {
        return new DirectCompletionResponse([]);
    }

    /**
     * Create a completion response from an array or enum class name.
     *
     * @param array<int, string>|class-string<\UnitEnum> $items Completion source
     * @return self
     */
    public static function match(array|string $items): self
    {
        if (is_string($items)) {
            return new EnumCompletionResponse($items);
        }

        return new ArrayCompletionResponse($items);
    }

    /**
     * Create a direct completion response from values.
     *
     * @param array<int, string>|string $items Completion values
     * @return self
     */
    public static function result(array|string $items): self
    {
        if (is_array($items)) {
            $hasMore = count($items) > static::MAX_VALUES;
            $truncated = array_slice($items, 0, static::MAX_VALUES);

            return new DirectCompletionResponse($truncated, $hasMore);
        }

        return new DirectCompletionResponse([$items], false);
    }

    /**
     * Resolve completion values for the current partial input.
     *
     * @param string $value Current partial value
     * @return self
     */
    abstract public function resolve(string $value): self;

    /**
     * Get the completion values.
     *
     * @return array<int, string>
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * Determine whether additional values are available.
     *
     * @return bool
     */
    public function hasMore(): bool
    {
        return $this->hasMore;
    }

    /**
     * Convert the completion response to an array.
     *
     * @return array{values: array<int, string>, total: int, hasMore: bool}
     */
    public function toArray(): array
    {
        return [
            'values' => $this->values,
            'total' => count($this->values),
            'hasMore' => $this->hasMore,
        ];
    }
}
