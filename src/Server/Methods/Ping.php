<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Methods;

use Crustum\Mcp\Server\Contracts\Method;
use Crustum\Mcp\Server\ServerContext;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;

/**
 * MCP ping method handler.
 */
class Ping implements Method
{
    /**
     * @inheritDoc
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        return JsonRpcResponse::result($request->id, []);
    }
}
