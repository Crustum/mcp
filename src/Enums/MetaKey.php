<?php
declare(strict_types=1);

namespace Crustum\Mcp\Enums;

/**
 * MCP protocol metadata keys carried in request and result `_meta` members.
 */
enum MetaKey: string
{
    case PROTOCOL_VERSION = 'io.modelcontextprotocol/protocolVersion';
    case CLIENT_CAPABILITIES = 'io.modelcontextprotocol/clientCapabilities';
    case CLIENT_INFO = 'io.modelcontextprotocol/clientInfo';
    case SERVER_INFO = 'io.modelcontextprotocol/serverInfo';
    case SUBSCRIPTION_ID = 'io.modelcontextprotocol/subscriptionId';
}
