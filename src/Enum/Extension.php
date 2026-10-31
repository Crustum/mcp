<?php
declare(strict_types=1);

namespace Crustum\Mcp\Enum;

/**
 * MCP extensions advertised through the extensions capability.
 */
enum Extension: string
{
    case Ui = 'io.modelcontextprotocol/ui';
}
