<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Methods;

use Crustum\Mcp\Exception\JsonRpcException;
use Crustum\Mcp\Response;
use Crustum\Mcp\ResponseFactory;
use Crustum\Mcp\Server\AppResource;
use Crustum\Mcp\Server\ContainerInvoker;
use Crustum\Mcp\Server\Contracts\HasUriTemplate;
use Crustum\Mcp\Server\Contracts\Method;
use Crustum\Mcp\Server\McpRequestBuilder;
use Crustum\Mcp\Server\Methods\Trait\InteractsWithResponsesTrait;
use Crustum\Mcp\Server\Methods\Trait\ResolvesResourcesTrait;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Server\ServerContext;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Mcp\Support\McpContainerBindings;
use Crustum\Mcp\Support\McpSdk;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;
use Generator;
use InvalidArgumentException;

/**
 * MCP resources/read method handler.
 */
class ReadResource implements Method
{
    use InteractsWithResponsesTrait;
    use ResolvesResourcesTrait;

    /**
     * @inheritDoc
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        $uri = $request->get('uri');

        try {
            $resource = $this->resolveResource($uri, $context);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new JsonRpcException($invalidArgumentException->getMessage(), -32002, $request->id);
        }

        $response = $this->callHandler(
            fn(): mixed => $this->invokeResource($resource, $uri, $request),
            $request,
        );

        return is_iterable($response)
            ? $this->toJsonRpcStreamedResponse($request, $response, $this->serializable($resource, $uri))
            : $this->toJsonRpcResponse($request, $response, $this->serializable($resource, $uri));
    }

    /**
     * Invoke a resource handler with URI context.
     *
     * @param \Crustum\Mcp\Server\Resource $resource Resource instance
     * @param string $uri Resource URI
     * @param \Crustum\Mcp\Transport\JsonRpcRequest $jsonRpcRequest JSON-RPC request
     * @return mixed
     */
    protected function invokeResource(Resource $resource, string $uri, JsonRpcRequest $jsonRpcRequest): mixed
    {
        $container = ContainerRegistry::getInstance();
        $request = $jsonRpcRequest->toRequest();
        $request->setUri($uri);

        if ($resource instanceof HasUriTemplate) {
            $variables = $resource->uriTemplate()->match($uri) ?? [];
            $request->merge($variables);
        }

        $request = McpRequestBuilder::build($jsonRpcRequest, $request);

        McpContainerBindings::bindRequest($container, $request);

        if ($resource instanceof AppResource) {
            McpContainerBindings::bind($container, McpContainerBindings::SDK, McpSdk::contents());
            McpContainerBindings::bind($container, McpContainerBindings::LIBRARY_SCRIPTS, $resource->libraryScripts());
        }

        try {
            $invoker = $container->get(ContainerInvoker::class);

            return $invoker->call([$resource, 'handle']);
        } finally {
            McpContainerBindings::releaseRequest($container);
            McpContainerBindings::release(
                $container,
                McpContainerBindings::SDK,
                McpContainerBindings::LIBRARY_SCRIPTS,
            );
        }
    }

    /**
     * Build the JSON-RPC serializer for resource responses.
     *
     * @param \Crustum\Mcp\Server\Resource $resource Resource instance
     * @param string $uri Resource URI
     * @return callable(\Crustum\Mcp\ResponseFactory): array<string, mixed>
     */
    protected function serializable(Resource $resource, string $uri): callable
    {
        $appMeta = $resource instanceof AppResource ? $resource->resolvedAppMeta() : null;

        return fn(ResponseFactory $factory): array => $factory->mergeMeta([
            'contents' => $factory->responses()->map(
                function (Response $response) use ($resource, $uri, $appMeta): array {
                    $content = [
                        ...$response->content()->toResource($resource),
                        'uri' => $uri,
                    ];

                    if ($appMeta !== null && $appMeta !== []) {
                        $content['_meta'] = array_merge($content['_meta'] ?? [], [
                            'ui' => $appMeta,
                        ]);
                    }

                    return $content;
                },
            )->toList(),
        ]);
    }
}
