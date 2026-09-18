<?php
declare(strict_types=1);

namespace Crustum\Mcp\Enums;

/**
 * HTTP headers that mirror MCP protocol values onto requests.
 */
enum RequestHeader: string
{
    case PROTOCOL_VERSION = 'MCP-Protocol-Version';
    case METHOD = 'Mcp-Method';
    case NAME = 'Mcp-Name';
}
