<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Methods;

use Crustum\Mcp\Exception\JsonRpcException;
use Crustum\Mcp\Response;
use Crustum\Mcp\ResponseFactory;
use Crustum\Mcp\Server\ContainerInvoker;
use Crustum\Mcp\Server\Contracts\Method;
use Crustum\Mcp\Server\McpRequestBuilder;
use Crustum\Mcp\Server\Methods\Trait\InteractsWithResponsesTrait;
use Crustum\Mcp\Server\Methods\Trait\ResolvesPromptsTrait;
use Crustum\Mcp\Server\Prompt;
use Crustum\Mcp\Server\ServerContext;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Mcp\Support\McpContainerBindings;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;
use Generator;
use InvalidArgumentException;

/**
 * MCP prompts/get method handler.
 */
class GetPrompt implements Method
{
    use InteractsWithResponsesTrait;
    use ResolvesPromptsTrait;

    /**
     * @inheritDoc
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        try {
            $prompt = $this->resolvePrompt($request->get('name'), $context);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new JsonRpcException($invalidArgumentException->getMessage(), -32602, $request->id);
        }

        $response = $this->callHandler(
            function () use ($prompt, $request): mixed {
                $container = ContainerRegistry::getInstance();
                $mcpRequest = McpRequestBuilder::build($request);
                McpContainerBindings::bindRequest($container, $mcpRequest);

                try {
                    $invoker = $container->get(ContainerInvoker::class);

                    return $invoker->call([$prompt, 'handle']);
                } finally {
                    McpContainerBindings::releaseRequest($container);
                }
            },
            $request,
        );

        return is_iterable($response)
            ? $this->toJsonRpcStreamedResponse($request, $response, $this->serializable($prompt))
            : $this->toJsonRpcResponse($request, $response, $this->serializable($prompt));
    }

    /**
     * Build the JSON-RPC serializer for prompt responses.
     *
     * @param \Crustum\Mcp\Server\Prompt $prompt Prompt instance
     * @return callable(\Crustum\Mcp\ResponseFactory): array<string, mixed>
     */
    protected function serializable(Prompt $prompt): callable
    {
        return fn(ResponseFactory $factory): array => $factory->mergeMeta([
            'description' => $prompt->description(),
            'messages' => $factory->responses()->map(fn(Response $response): array => [
                'role' => $response->role()->value,
                'content' => $response->content()->toPrompt($prompt),
            ])->toList(),
        ]);
    }
}
