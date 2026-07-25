<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Methods;

use Cake\Utility\Hash;
use Crustum\Mcp\Exception\JsonRpcException;
use Crustum\Mcp\Server;
use Crustum\Mcp\Server\Completions\CompletionResponse;
use Crustum\Mcp\Server\ContainerInvoker;
use Crustum\Mcp\Server\Contracts\Completable;
use Crustum\Mcp\Server\Contracts\HasUriTemplate;
use Crustum\Mcp\Server\Contracts\Method;
use Crustum\Mcp\Server\Methods\Trait\ResolvesPromptsTrait;
use Crustum\Mcp\Server\Methods\Trait\ResolvesResourcesTrait;
use Crustum\Mcp\Server\Prompt;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Server\ServerContext;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;
use Exception;
use InvalidArgumentException;

/**
 * MCP completion/complete method handler.
 */
class CompletionComplete implements Method
{
    use ResolvesPromptsTrait;
    use ResolvesResourcesTrait;

    /**
     * @inheritDoc
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        if (!$context->hasCapability(Server::CAPABILITY_COMPLETIONS)) {
            throw new JsonRpcException(
                'Server does not support completions capability.',
                -32601,
                $request->id,
            );
        }

        $ref = $request->get('ref');
        $argument = $request->get('argument');

        if (is_null($ref) || is_null($argument)) {
            throw new JsonRpcException(
                'Missing required parameters: ref and argument',
                -32602,
                $request->id,
            );
        }

        try {
            $primitive = $this->resolvePrimitive($ref, $context);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new JsonRpcException($invalidArgumentException->getMessage(), -32602, $request->id);
        }

        if (!$primitive instanceof Completable) {
            $result = CompletionResponse::empty();

            return JsonRpcResponse::result($request->id, [
                'completion' => $result->toArray(),
            ]);
        }

        $argumentName = Hash::get($argument, 'name');
        $argumentValue = Hash::get($argument, 'value', '');

        if (is_null($argumentName)) {
            throw new JsonRpcException(
                'Missing argument name.',
                -32602,
                $request->id,
            );
        }

        $contextArguments = Hash::get($request->get('context') ?? [], 'arguments', []);

        try {
            $result = $this->invokeCompletion($primitive, $argumentName, $argumentValue, $contextArguments);
        } catch (Exception) {
            $result = CompletionResponse::empty();
        }

        return JsonRpcResponse::result($request->id, [
            'completion' => $result->toArray(),
        ]);
    }

    /**
     * Resolve the completion target primitive from a reference payload.
     *
     * @param array<string, mixed> $ref Reference payload
     * @param \Crustum\Mcp\Server\ServerContext $context Server context
     * @return \Crustum\Mcp\Server\Prompt|\Crustum\Mcp\Server\Resource|\Crustum\Mcp\Server\Contracts\HasUriTemplate
     * @throws \InvalidArgumentException
     */
    protected function resolvePrimitive(array $ref, ServerContext $context): Prompt|Resource|HasUriTemplate
    {
        return match (Hash::get($ref, 'type')) {
            'ref/prompt' => $this->resolvePrompt(Hash::get($ref, 'name'), $context),
            'ref/resource' => $this->resolveResource(Hash::get($ref, 'uri'), $context),
            default => throw new InvalidArgumentException('Invalid reference type. Expected ref/prompt or ref/resource.'),
        };
    }

    /**
     * Invoke a completion handler on a primitive.
     *
     * @param \Crustum\Mcp\Server\Contracts\Completable $primitive Completable primitive
     * @param string $argumentName Argument name
     * @param string $argumentValue Partial argument value
     * @param array<string, mixed> $context Completion context arguments
     * @return mixed
     */
    protected function invokeCompletion(
        Completable $primitive,
        string $argumentName,
        string $argumentValue,
        array $context,
    ): mixed {
        $invoker = ContainerRegistry::getInstance()->get(ContainerInvoker::class);
        $result = $invoker->call($primitive->complete(...), [
            'argument' => $argumentName,
            'value' => $argumentValue,
            'context' => $context,
        ]);

        return $result->resolve($argumentValue);
    }
}
