<?php
declare(strict_types=1);

namespace Crustum\Mcp\Support;

use Cake\Utility\Inflector;
use Stringable as PhpStringable;

/**
 * String helper utilities for MCP data accessors.
 */
class Str
{
    /**
     * Create a new stringable instance from a value.
     *
     * @param mixed $value Value to stringify
     * @return \Crustum\Mcp\Support\Stringable
     */
    public static function of(mixed $value): Stringable
    {
        if ($value === null) {
            return new Stringable('');
        }

        if (is_string($value)) {
            return new Stringable($value);
        }

        if (is_scalar($value) || $value instanceof PhpStringable) {
            return new Stringable((string)$value);
        }

        return new Stringable('');
    }

    /**
     * Convert a string to kebab-case.
     *
     * @param string $value Input string
     * @return string
     */
    public static function kebab(string $value): string
    {
        return Inflector::dasherize($value);
    }

    /**
     * Convert a string to headline case.
     *
     * @param string $value Input string
     * @return string
     */
    public static function headline(string $value): string
    {
        return ucwords(str_replace(['-', '_'], ' ', Inflector::dasherize($value)));
    }
}
