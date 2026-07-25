<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Support;

/**
 * Session helper for OAuth client tests.
 */
final class Session
{
    /**
     * Read a session value.
     *
     * @param string $key Session key
     * @return mixed
     */
    public static function get(string $key): mixed
    {
        return mcpSession()->read($key);
    }

    /**
     * Write a session value.
     *
     * @param string $key Session key
     * @param mixed $value Session value
     * @return void
     */
    public static function put(string $key, mixed $value): void
    {
        mcpSession()->write($key, $value);
    }

    /**
     * Determine whether a session key exists.
     *
     * @param string $key Session key
     * @return bool
     */
    public static function has(string $key): bool
    {
        return mcpSession()->check($key);
    }
}
