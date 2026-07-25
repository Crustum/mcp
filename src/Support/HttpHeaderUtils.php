<?php
declare(strict_types=1);

namespace Crustum\Mcp\Support;

/**
 * HTTP header parsing helpers.
 */
class HttpHeaderUtils
{
    /**
     * Remove surrounding quotes from a header parameter value.
     *
     * @param string $value Header parameter value
     * @return string Unquoted value
     */
    public static function unquote(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        if ($value[0] !== '"') {
            return $value;
        }

        if (!str_ends_with($value, '"')) {
            return $value;
        }

        return stripcslashes(substr($value, 1, -1));
    }
}
