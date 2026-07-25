<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Methods;

use Crustum\Mcp\Server\Contracts\Method;
use Crustum\Mcp\Server\Pagination\CursorPaginator;
use Crustum\Mcp\Server\ServerContext;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;

/**
 * MCP prompts/list method handler.
 */
class ListPrompts implements Method
{
    /**
     * @inheritDoc
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $paginator = new CursorPaginator(
            items: $context->prompts(),
            perPage: $context->perPage($request->get('per_page')),
            cursor: $request->cursor(),
        );

        return JsonRpcResponse::result($request->id, $paginator->paginate('prompts'));
    }
}
