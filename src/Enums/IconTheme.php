<?php
declare(strict_types=1);

namespace Crustum\Mcp\Enums;

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
