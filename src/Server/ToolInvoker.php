<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server;

use Crustum\Mcp\Response;
use Crustum\Mcp\ResponseFactory;
use Crustum\Mcp\Server\Contracts\Errable;
use Crustum\Mcp\Server\Methods\Trait\InteractsWithResponsesTrait;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Mcp\Support\McpContainerBindings;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;
use Generator;

/**
 * Invokes a tool handler and converts its output into JSON-RPC responses.
 */
class ToolInvoker implements Errable
{
    use InteractsWithResponsesTrait;

    /**
     * Invoke a tool handler and serialize its response.
     *
     * @param \Crustum\Mcp\Server\Tool $tool Tool instance
     * @param \Crustum\Mcp\Transport\JsonRpcRequest $request JSON-RPC request
     * @return \Crustum\Mcp\Transport\JsonRpcResponse|\Generator<int, \Crustum\Mcp\Transport\JsonRpcResponse>
     */
    public function invoke(Tool $tool, JsonRpcRequest $request): Generator|JsonRpcResponse
    {
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
