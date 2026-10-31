<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Methods;

use Crustum\Mcp\Enum\ProtocolVersion;
use Crustum\Mcp\Server\Contracts\Method;
use Crustum\Mcp\Server\ServerContext;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;

/**
 * Legacy initialize handshake method handler.
 */
class Initialize implements Method
{
    /**
     * @inheritDoc
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $requested = $request->params['protocolVersion'] ?? null;

        $supported = ProtocolVersion::initializeSupported();

        return JsonRpcResponse::result($request->id, [
            'protocolVersion' => in_array($requested, $supported, true)
                ? $requested
                : $supported[0],
            'capabilities' => $context->serverCapabilities ?: (object)[],
            'serverInfo' => $context->implementation->toArray(),
            'instructions' => $context->instructions,
        ]);
    }
}
