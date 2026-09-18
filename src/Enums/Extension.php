<?php
declare(strict_types=1);

namespace Crustum\Mcp\Enums;

/**
 * MCP extensions advertised through the extensions capability.
 */
enum Extension: string
{
    case Ui = 'io.modelcontextprotocol/ui';
}
