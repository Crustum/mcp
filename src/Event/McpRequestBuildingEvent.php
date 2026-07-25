<?php
declare(strict_types=1);

namespace Crustum\Mcp\Event;

use Cake\Event\Event;
use Crustum\Mcp\Request;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Psr\Http\Message\ServerRequestInterface;

/**
 * MCP request building lifecycle event.
 *
 * Host applications attach listeners to enrich the MCP request from the HTTP
 * context (identity, authorization, custom attributes) after middleware runs.
 *
 * @extends \Cake\Event\Event<\Crustum\Mcp\Request>
 * @property \Crustum\Mcp\Request $subject
 * @property array{
 *     jsonRpcRequest: \Crustum\Mcp\Transport\JsonRpcRequest,
 *     serverRequest: \Psr\Http\Message\ServerRequestInterface|null
 * } $data
 */
class McpRequestBuildingEvent extends Event
{
    /**
     * Event name.
     */
    public const NAME = 'Mcp.RequestBuilding';

    /**
     * @param \Crustum\Mcp\Request $mcpRequest MCP request under construction
     * @param \Crustum\Mcp\Transport\JsonRpcRequest $jsonRpcRequest Source JSON-RPC request
     * @param \Psr\Http\Message\ServerRequestInterface|null $serverRequest HTTP request when available
     */
    public function __construct(
        Request $mcpRequest,
        JsonRpcRequest $jsonRpcRequest,
        ?ServerRequestInterface $serverRequest = null,
    ) {
        parent::__construct(self::NAME, $mcpRequest, [
            'jsonRpcRequest' => $jsonRpcRequest,
            'serverRequest' => $serverRequest,
        ]);
    }

    /**
     * Get the MCP request being built.
     *
     * @return \Crustum\Mcp\Request
     */
    public function getMcpRequest(): Request
    {
        return $this->getSubject();
    }

    /**
     * Get the source JSON-RPC request.
     *
     * @return \Crustum\Mcp\Transport\JsonRpcRequest
     */
    public function getJsonRpcRequest(): JsonRpcRequest
    {
        return $this->getData('jsonRpcRequest');
    }

    /**
     * Get the HTTP server request when the MCP server runs over HTTP.
     *
     * @return \Psr\Http\Message\ServerRequestInterface|null
     */
    public function getServerRequest(): ?ServerRequestInterface
    {
        $serverRequest = $this->getData('serverRequest');

        return $serverRequest instanceof ServerRequestInterface ? $serverRequest : null;
    }
}
