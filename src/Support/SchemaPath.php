<?php
declare(strict_types=1);

namespace Crustum\Mcp\Support;

use Stringable;

/**
 * A statically reachable path through an input schema, rendered as a JSON pointer.
 */
class SchemaPath implements Stringable
{
    /**
     * Create a new schema path.
     *
     * @param list<string> $segments Path segments
     */
    public function __construct(public readonly array $segments = [])
    {
    }

    /**
     * Create a root schema path.
     *
     * @return self
     */
    public static function root(): self
    {
        return new self();
    }

    /**
     * Create a child schema path.
     *
     * @param string $segment Child path segment
     * @return self
     */
    public function child(string $segment): self
    {
        return new self([...$this->segments, $segment]);
    }

    /**
     * Resolve the value at this path within an arguments array.
     *
     * @param array<array-key, mixed> $arguments Tool arguments
     * @return mixed
     */
    public function valueIn(array $arguments): mixed
    {
        $value = $arguments;

        foreach ($this->segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Render the path as a JSON pointer.
     *
     * @return string
     */
    public function __toString(): string
    {
        return implode('', array_map(
            static fn(string $segment): string => '/' . str_replace(['~', '/'], ['~0', '~1'], $segment),
            $this->segments,
        ));
    }
}
