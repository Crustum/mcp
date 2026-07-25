<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Contracts;

use Crustum\Mcp\Server\ServerContext;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;

/**
 * MCP server JSON-RPC method contract.
 */
interface Method
{
    /**
     * Handle an incoming JSON-RPC request.
     *
     * @param \Crustum\Mcp\Transport\JsonRpcRequest $request JSON-RPC request
     * @param \Crustum\Mcp\Server\ServerContext $context Server context
     * @return \Crustum\Mcp\Transport\JsonRpcResponse|iterable<\Crustum\Mcp\Transport\JsonRpcResponse>
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse|iterable;
}
