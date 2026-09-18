<?php
declare(strict_types=1);

namespace Crustum\Mcp\Enums;

/**
 * MCP and JSON-RPC error codes.
 *
 * Standard JSON-RPC codes plus the codes the 2026-07-28 revision of the MCP
 * specification reserves in the -32020 to -32099 range.
 */
enum ErrorCode: int
{
    case PARSE_ERROR = -32700;
    case INVALID_REQUEST = -32600;
    case METHOD_NOT_FOUND = -32601;
    case INVALID_PARAMS = -32602;
    case INTERNAL_ERROR = -32603;
    case HEADER_MISMATCH = -32020;
    case MISSING_REQUIRED_CLIENT_CAPABILITY = -32021;
    case UNSUPPORTED_PROTOCOL_VERSION = -32022;
}
