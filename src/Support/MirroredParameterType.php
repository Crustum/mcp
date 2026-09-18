<?php
declare(strict_types=1);

namespace Crustum\Mcp\Support;

/**
 * Types that can be mirrored into a request header.
 */
enum MirroredParameterType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';

    /**
     * Largest integer that can be safely carried in a header.
     */
    public const SAFE_INTEGER = 9007199254740991;

    /**
     * Resolve a type from a schema type string.
     *
     * @param mixed $type Schema type value
     * @return self|null
     */
    public static function fromSchema(mixed $type): ?self
    {
        return is_string($type) ? self::tryFrom($type) : null;
    }

    /**
     * Convert a value to its header representation.
     *
     * @param mixed $value Argument value
     * @return string|null
     */
    public function stringify(mixed $value): ?string
    {
        return match ($this) {
            self::String => is_string($value) ? $value : null,
            self::Boolean => is_bool($value) ? ($value ? 'true' : 'false') : null,
            self::Integer => $this->stringifyInteger($value),
        };
    }

    /**
     * Convert an integer value to its header representation.
     *
     * @param mixed $value Argument value
     * @return string|null
     */
    protected function stringifyInteger(mixed $value): ?string
    {
        if (is_float($value) && $value === floor($value) && abs($value) <= self::SAFE_INTEGER) {
            $value = (int)$value;
        }

        if (!is_int($value) || abs($value) > self::SAFE_INTEGER) {
            return null;
        }

        return (string)$value;
    }
}
