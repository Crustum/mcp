<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Contracts;

use Cake\Http\Response;
use Closure;
use Psr\Http\Message\ServerRequestInterface;

/**
 * MCP server transport contract.
 */
interface Transport
{
    /**
     * Register the message receive handler.
     *
     * @param \Closure(string): void $handler Message handler
     * @return void
     */
    public function onReceive(Closure $handler): void;

    /**
     * Run the transport loop.
     *
     * @return \Cake\Http\Response|null
     */
    public function run(): ?Response;

    /**
     * Send a serialized JSON-RPC message.
     *
     * @param string $message Serialized JSON-RPC message
     * @param string|null $sessionId Optional MCP session identifier
     * @return void
     */
    public function send(string $message, ?string $sessionId = null): void;

    /**
     * Get the active MCP session identifier.
     *
     * @return string|null
     */
    public function sessionId(): ?string;

    /**
     * Register a streaming callback for outbound messages.
     *
     * @param \Closure(): iterable<string> $stream Stream callback
     * @return void
     */
    public function stream(Closure $stream): void;

    /**
     * Get the HTTP server request when this transport handles HTTP traffic.
     *
     * @return \Psr\Http\Message\ServerRequestInterface|null
     */
    public function httpRequest(): ?ServerRequestInterface;
}
