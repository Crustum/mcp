<?php
declare(strict_types=1);

namespace Crustum\Mcp\Enums;

/**
 * Supported MCP protocol versions.
 *
 * Tracks protocol versions supported by the MCP client and server.
 */
enum ProtocolVersion: string
{
    case V2025_11_25 = '2025-11-25';
    case V2025_06_18 = '2025-06-18';
    case V2025_03_26 = '2025-03-26';
    case V2024_11_05 = '2024-11-05';

    /**
     * Latest supported protocol version.
     */
    public const LATEST = self::V2025_11_25;

    /**
     * Get all supported protocol version strings.
     *
     * @return array<int, string>
     */
    public static function supported(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get protocol version strings supported by the MCP client.
     *
     * @return array<int, string>
     */
    public static function clientSupported(): array
    {
        return [
            self::V2025_11_25->value,
            self::V2025_06_18->value,
        ];
    }
}
