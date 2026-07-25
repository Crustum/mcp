<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server;

use Cake\Event\EventManager;
use Crustum\Mcp\Event\McpRequestBuildingEvent;
use Crustum\Mcp\Request;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Builds MCP request DTOs and dispatches enrichment events.
 */
final class McpRequestBuilder
{
    /**
     * HTTP request context for the current MCP dispatch.
     *
     * @var \Psr\Http\Message\ServerRequestInterface|null
     */
    protected static ?ServerRequestInterface $httpRequest = null;

    /**
     * Bind the HTTP request context for subsequent builds in this dispatch.
     *
     * @param \Psr\Http\Message\ServerRequestInterface|null $httpRequest HTTP request
     * @return void
     */
    public static function usingHttpRequest(?ServerRequestInterface $httpRequest): void
    {
        self::$httpRequest = $httpRequest;
    }

    /**
     * Clear the HTTP request context after dispatch.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$httpRequest = null;
    }

    /**
     * Build an MCP request and dispatch the building event for host listeners.
     *
     * @param \Crustum\Mcp\Transport\JsonRpcRequest $jsonRpcRequest JSON-RPC request
     * @param \Crustum\Mcp\Request|null $mcpRequest Existing MCP request instance
     * @param \Psr\Http\Message\ServerRequestInterface|null $serverRequest HTTP request override
     * @return \Crustum\Mcp\Request
     */
    public static function build(
        JsonRpcRequest $jsonRpcRequest,
        ?Request $mcpRequest = null,
        ?ServerRequestInterface $serverRequest = null,
    ): Request {
        $mcpRequest ??= $jsonRpcRequest->toRequest();
        $serverRequest ??= self::$httpRequest;

        EventManager::instance()->dispatch(new McpRequestBuildingEvent(
            $mcpRequest,
            $jsonRpcRequest,
            $serverRequest,
        ));

        return $mcpRequest;
    }
}
