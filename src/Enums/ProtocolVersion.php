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
    case V2026_07_28 = '2026-07-28';
    case V2025_11_25 = '2025-11-25';
    case V2025_06_18 = '2025-06-18';
    case V2025_03_26 = '2025-03-26';
    case V2024_11_05 = '2024-11-05';

    /**
     * Latest supported protocol version.
     */
    public const LATEST = self::V2026_07_28;

    /**
     * Resolve the handshake style used by this protocol version.
     *
     * @return \Crustum\Mcp\Enums\ProtocolHandshake
     */
    public function handshake(): ProtocolHandshake
    {
        return match ($this) {
            self::V2026_07_28 => ProtocolHandshake::Discovery,
            self::V2025_11_25, self::V2025_06_18, self::V2025_03_26, self::V2024_11_05 => ProtocolHandshake::Initialize,
        };
    }

    /**
     * Get protocol version strings supported by the MCP server.
     *
     * @return list<string>
     */
    public static function serverSupported(): array
    {
        return [self::LATEST->value];
    }

    /**
     * Get protocol version strings supported by the MCP client.
     *
     * @return list<string>
     */
    public static function clientSupported(): array
    {
        return [
            self::V2026_07_28->value,
            self::V2025_11_25->value,
            self::V2025_06_18->value,
        ];
    }

    /**
     * Get protocol version strings supported by the initialize handshake.
     *
     * @return list<string>
     */
    public static function initializeSupported(): array
    {
        return [
            self::V2025_11_25->value,
            self::V2025_06_18->value,
        ];
    }

    /**
     * Pick the client's preferred version among those offered by a server.
     *
     * @param string ...$versions Versions offered by the server
     * @return self|null
     */
    public static function preferredFrom(string ...$versions): ?self
    {
        foreach (self::cases() as $version) {
            if (in_array($version->value, self::clientSupported(), true) && in_array($version->value, $versions, true)) {
                return $version;
            }
        }

        return null;
    }
}
