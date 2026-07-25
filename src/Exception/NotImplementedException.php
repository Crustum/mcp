<?php
declare(strict_types=1);

namespace Crustum\Mcp\Exception;

use Exception;

/**
 * MCP method not implemented exception.
 */
class NotImplementedException extends Exception
{
    /**
     * Create an exception for an unimplemented method.
     *
     * @param string $class Class name
     * @param string $method Method name
     * @return self
     */
    public static function forMethod(string $class, string $method): self
    {
        return new self("The method [{$class}@{$method}] is not implemented yet.");
    }
}
