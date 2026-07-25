<?php
declare(strict_types=1);

namespace Crustum\Mcp\Event;

use Cake\Event\Event;
use Crustum\Mcp\Server;

/**
 * MCP session initialized lifecycle event.
 *
 * @extends \Cake\Event\Event<\Crustum\Mcp\Server>
 * @property \Crustum\Mcp\Server $subject
 * @property array{
 *     sessionId: string,
 *     clientInfo: array<string, mixed>|null,
 *     protocolVersion: string|null,
 *     clientCapabilities: array<string, mixed>|null
 * } $data
 * @see https://modelcontextprotocol.io/specification/2025-06-18/basic/lifecycle#initialization
 */
class SessionInitializedEvent extends Event
{
    /**
     * Event name.
     */
    public const NAME = 'Mcp.SessionInitialized';

    /**
     * @param \Crustum\Mcp\Server $server Server instance
     * @param string $sessionId MCP session identifier
     * @param array<string, mixed>|null $clientInfo Client implementation metadata
     * @param string|null $protocolVersion Negotiated protocol version
     * @param array<string, mixed>|null $clientCapabilities Client capability payload
     */
    public function __construct(
        Server $server,
        string $sessionId,
        ?array $clientInfo = null,
        ?string $protocolVersion = null,
        ?array $clientCapabilities = null,
    ) {
        parent::__construct(self::NAME, $server, [
            'sessionId' => $sessionId,
            'clientInfo' => $clientInfo,
            'protocolVersion' => $protocolVersion,
            'clientCapabilities' => $clientCapabilities,
        ]);
    }

    /**
     * Get the MCP session identifier.
     *
     * @return string
     */
    public function getSessionId(): string
    {
        return $this->getData('sessionId');
    }

    /**
     * Get client implementation metadata.
     *
     * @return array<string, mixed>|null
     */
    public function getClientInfo(): ?array
    {
        return $this->getData('clientInfo');
    }

    /**
     * Get the negotiated protocol version.
     *
     * @return string|null
     */
    public function getProtocolVersion(): ?string
    {
        return $this->getData('protocolVersion');
    }

    /**
     * Get client capability metadata.
     *
     * @return array<string, mixed>|null
     */
    public function getClientCapabilities(): ?array
    {
        return $this->getData('clientCapabilities');
    }

    /**
     * Get the client name from client info when available.
     *
     * @return string|null
     */
    public function clientName(): ?string
    {
        $name = $this->getClientInfo()['name'] ?? null;

        return is_string($name) ? $name : null;
    }

    /**
     * Get the client title from client info when available.
     *
     * @return string|null
     */
    public function clientTitle(): ?string
    {
        $title = $this->getClientInfo()['title'] ?? null;

        return is_string($title) ? $title : null;
    }

    /**
     * Get the client version from client info when available.
     *
     * @return string|null
     */
    public function clientVersion(): ?string
    {
        $version = $this->getClientInfo()['version'] ?? null;

        return is_string($version) ? $version : null;
    }
}
