<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Contracts;

/**
 * MCP client transport contract.
 */
interface Transport
{
    /**
     * Connect the transport.
     *
     * @return void
     */
    public function connect(): void;

    /**
     * Disconnect the transport.
     *
     * @return void
     */
    public function disconnect(): void;

    /**
     * Send a message to the MCP server.
     *
     * @param string $message Serialized JSON-RPC message
     * @param array<string, string> $headers Extra request headers
     * @return void
     */
    public function send(string $message, array $headers = []): void;

    /**
     * Receive the next message from the MCP server.
     *
     * @return string Serialized JSON-RPC message
     */
    public function receive(): string;

    /**
     * Set the transport timeout in seconds.
     *
     * @param float $seconds Timeout in seconds
     * @return void
     */
    public function setTimeoutSeconds(float $seconds): void;

    /**
     * Get a serializable transport recipe.
     *
     * @return array<string, mixed>
     */
    public function recipe(): array;
}
