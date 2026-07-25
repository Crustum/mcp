<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Ui\Enums;

/**
 * MCP UI permission values.
 */
enum Permission: string
{
    case Camera = 'camera';
    case Microphone = 'microphone';
    case Geolocation = 'geolocation';
    case ClipboardWrite = 'clipboardWrite';
}
