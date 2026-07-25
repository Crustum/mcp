<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Enums;

/**
 * MCP message role values.
 *
 * Defines the role of a message in MCP prompt and tool responses.
 */
enum Role: string
{
    case Assistant = 'assistant';
    case User = 'user';
}
