<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Methods;

use Crustum\Mcp\Exception\JsonRpcException;
use Crustum\Mcp\Response;
use Crustum\Mcp\ResponseFactory;
use Crustum\Mcp\Server\ContainerInvoker;
use Crustum\Mcp\Server\Contracts\Errable;
use Crustum\Mcp\Server\Contracts\Method;
use Crustum\Mcp\Server\McpRequestBuilder;
use Crustum\Mcp\Server\Methods\Trait\InteractsWithResponsesTrait;
use Crustum\Mcp\Server\ServerContext;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Mcp\Support\McpContainerBindings;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;
use Generator;

/**
 * MCP tools/call method handler.
 */
class CallTool implements Errable, Method
{
    use InteractsWithResponsesTrait;

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

        $response = $this->callHandler(
            function () use ($tool, $request): mixed {
                $container = ContainerRegistry::getInstance();
                $mcpRequest = McpRequestBuilder::build($request);
                McpContainerBindings::bindRequest($container, $mcpRequest);

                try {
                    $invoker = $container->get(ContainerInvoker::class);

                    return $invoker->call([$tool, 'handle']);
                } finally {
                    McpContainerBindings::releaseRequest($container);
                }
            },
            $request,
        );

        return is_iterable($response)
            ? $this->toJsonRpcStreamedResponse($request, $response, $this->serializable($tool))
            : $this->toJsonRpcResponse($request, $response, $this->serializable($tool));
    }

    /**
     * Build the JSON-RPC serializer for tool responses.
     *
     * @param \Crustum\Mcp\Server\Tool $tool Tool instance
     * @return callable(\Crustum\Mcp\ResponseFactory): array<string, mixed>
     */
    protected function serializable(Tool $tool): callable
    {
        return fn(ResponseFactory $factory): array => $factory->mergeStructuredContent(
            $factory->mergeMeta([
                'content' => $factory->responses()->map(
                    fn(Response $response): array => $response->content()->toTool($tool),
                )->toList(),
                'isError' => $factory->responses()->some(
                    fn(Response $response): bool => $response->isError(),
                ),
            ]),
        );
    }
}
