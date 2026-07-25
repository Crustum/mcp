<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Methods;

use Crustum\Mcp\Exception\JsonRpcException;
use Crustum\Mcp\Server\Contracts\Method;
use Crustum\Mcp\Server\ServerContext;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;

/**
 * MCP initialize method handler.
 */
class Initialize implements Method
{
    /**
     * @inheritDoc
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $requestedVersion = $request->params['protocolVersion'] ?? null;

        if (!is_null($requestedVersion) && !in_array($requestedVersion, $context->supportedProtocolVersions, true)) {
            throw new JsonRpcException(
                message: 'Unsupported protocol version',
                code: -32602,
                requestId: $request->id,
                data: [
                    'supported' => $context->supportedProtocolVersions,
                    'requested' => $requestedVersion,
                ],
            );
        }

        $protocolVersion = $requestedVersion ?? $context->supportedProtocolVersions[0];

        return JsonRpcResponse::result($request->id, [
            'protocolVersion' => $protocolVersion,
            'capabilities' => $context->serverCapabilities,
            'serverInfo' => $context->implementation->toArray(),
            'instructions' => $context->instructions,
        ]);
    }
}
