<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Ui\Enum;

/**
 * MCP UI visibility scopes.
 */
enum Visibility: string
{
    case Model = 'model';
    case App = 'app';
}
