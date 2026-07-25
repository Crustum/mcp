<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Enums;

/**
 * MCP icon theme values.
 *
 * Defines supported icon appearance themes for MCP metadata.
 */
enum IconTheme: string
{
    case Light = 'light';
    case Dark = 'dark';
}
