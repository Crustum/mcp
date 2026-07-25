<?php
declare(strict_types=1);

namespace Crustum\Mcp\Support;

use Stringable as PhpStringable;

/**
 * Fluent string wrapper for MCP request input accessors.
 */
class Stringable implements PhpStringable
{
    /**
     * Create a new string wrapper.
     *
     * @param string $value String value
     */
    public function __construct(
        protected string $value,
    ) {
    }

    /**
     * Get the underlying string value.
     *
     * @return string
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Limit the string to the given number of characters.
     *
     * @param int $limit Maximum number of characters
     * @param string $end Trailing suffix when truncated
     * @return self
     */
    public function limit(int $limit, string $end = ''): self
    {
        if (mb_strlen($this->value) <= $limit) {
            return new self($this->value);
        }

        return new self(mb_substr($this->value, 0, $limit) . $end);
    }

    /**
     * Append one or more values to the string.
     *
     * @param string ...$values Values to append
     * @return self
     */
    public function append(string ...$values): self
    {
        return new self($this->value . implode('', $values));
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
