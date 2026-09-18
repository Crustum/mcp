<?php
declare(strict_types=1);

namespace Crustum\Mcp\Enums;

/**
 * Protocol handshake style for a protocol version era.
 */
enum ProtocolHandshake
{
    case Initialize;
    case Discovery;
}
