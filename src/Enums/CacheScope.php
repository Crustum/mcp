<?php
declare(strict_types=1);

namespace Crustum\Mcp\Enums;

/**
 * Caching scope for MCP result caching hints.
 */
enum CacheScope: string
{
    case Public = 'public';
    case Private = 'private';
}
