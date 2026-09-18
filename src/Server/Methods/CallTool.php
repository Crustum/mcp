<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Methods;

use Crustum\Mcp\Exception\JsonRpcException;
use Crustum\Mcp\Server\Contracts\Errable;
use Crustum\Mcp\Server\Contracts\Method;
use Crustum\Mcp\Server\ServerContext;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\ToolInvoker;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;
use Generator;

/**
 * MCP tools/call method handler.
 */
class CallTool implements Errable, Method
{
    /**
     * @inheritDoc
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        if (is_null($request->get('name'))) {
            throw new JsonRpcException(
                'Missing [name] parameter.',
                -32602,
                $request->id,
            );
        }

        $tool = $context->tools()->filter(
            fn(Tool $tool): bool => $tool->name() === $request->params['name'],
        )->first();

        if ($tool === null) {
            throw new JsonRpcException(
                "Tool [{$request->params['name']}] not found.",
                -32602,
                $request->id,
            );
        }

        return (new ToolInvoker())->invoke($tool, $request);
    }
}
