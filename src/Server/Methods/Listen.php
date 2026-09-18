<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Methods;

use Crustum\Mcp\Enums\MetaKey;
use Crustum\Mcp\Server\Contracts\Method;
use Crustum\Mcp\Server\ServerContext;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;
use Generator;

/**
 * Acknowledge and gracefully close subscriptions/listen requests.
 */
class Listen implements Method
{
    /**
     * @param \Crustum\Mcp\Transport\JsonRpcRequest $request Incoming request
     * @param \Crustum\Mcp\Server\ServerContext $context Server context
     * @return \Generator<int, \Crustum\Mcp\Transport\JsonRpcResponse>
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator
    {
        yield JsonRpcResponse::notification('notifications/subscriptions/acknowledged', [
            '_meta' => [MetaKey::SUBSCRIPTION_ID->value => $request->id],
            'notifications' => (object)[],
        ]);

        yield JsonRpcResponse::result($request->id, [
            '_meta' => [MetaKey::SUBSCRIPTION_ID->value => $request->id],
        ]);
    }
}
