<?php
declare(strict_types=1);

namespace Crustum\Mcp\Support;

use InvalidArgumentException;

/**
 * Provides inlined MCP UI SDK contents for app resource views.
 */
final class McpSdk
{
    /**
     * Get the minified MCP SDK JavaScript contents.
     *
     * @return string
     */
    public static function contents(): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'mcp-sdk.min.js';

        if (!is_file($path)) {
            throw new InvalidArgumentException("MCP SDK file not found at [{$path}].");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new InvalidArgumentException("Unable to read MCP SDK file at [{$path}].");
        }

        return $contents;
    }
}
