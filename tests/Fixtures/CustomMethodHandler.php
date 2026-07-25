<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Server\Contracts\Method;
use Crustum\Mcp\Server\ServerContext;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;

class CustomMethodHandler implements Method
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        return JsonRpcResponse::result($request->id, ['message' => 'Custom method executed successfully!']);
    }
}
