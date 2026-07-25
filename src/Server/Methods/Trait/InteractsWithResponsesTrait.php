<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Methods\Trait;

use Authentication\Authenticator\UnauthenticatedException;
use Cake\Collection\Collection;
use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Crustum\Mcp\Exception\JsonRpcException;
use Crustum\Mcp\Exception\ValidationException;
use Crustum\Mcp\Response;
use Crustum\Mcp\ResponseFactory;
use Crustum\Mcp\Server\Contracts\Errable;
use Crustum\Mcp\Support\ValidationMessages;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;
use Generator;
use InvalidArgumentException;
use Throwable;

/**
 * Converts MCP handler responses into JSON-RPC payloads.
 */
trait InteractsWithResponsesTrait
{
    /**
     * Convert a handler response into a JSON-RPC result response.
     *
     * @param \Crustum\Mcp\Transport\JsonRpcRequest $request JSON-RPC request
     * @param \Crustum\Mcp\Response|\Crustum\Mcp\ResponseFactory|array<int, \Crustum\Mcp\Response|\Crustum\Mcp\ResponseFactory|string>|string $response Handler response
     * @param callable(\Crustum\Mcp\ResponseFactory): array<string, mixed> $serializable Result serializer
     * @return \Crustum\Mcp\Transport\JsonRpcResponse
     * @throws \Crustum\Mcp\Exception\JsonRpcException
     */
    protected function toJsonRpcResponse(
        JsonRpcRequest $request,
        Response|ResponseFactory|array|string $response,
        callable $serializable,
    ): JsonRpcResponse {
        $responseFactory = $this->toResponseFactory($response);

        $responseFactory->responses()->each(function (Response $response) use ($request): void {
            if (!$this instanceof Errable && $response->isError()) {
                throw new JsonRpcException(
                    $response->content()->__toString(),
                    -32603,
                    $request->id,
                );
            }
        });

        return JsonRpcResponse::result($request->id, $serializable($responseFactory));
    }

    /**
     * Convert a streamed handler response into JSON-RPC payloads.
     *
     * @param \Crustum\Mcp\Transport\JsonRpcRequest $request JSON-RPC request
     * @param iterable<\Crustum\Mcp\Response|\Crustum\Mcp\ResponseFactory|string> $responses Handler responses
     * @param callable(\Crustum\Mcp\ResponseFactory): array<string, mixed> $serializable Result serializer
     * @return \Generator<int, \Crustum\Mcp\Transport\JsonRpcResponse>
     */
    protected function toJsonRpcStreamedResponse(
        JsonRpcRequest $request,
        iterable $responses,
        callable $serializable,
    ): Generator {
        /** @var array<int, \Crustum\Mcp\Response|\Crustum\Mcp\ResponseFactory|string> $pendingResponses */
        $pendingResponses = [];

        try {
            foreach ($responses as $response) {
                if ($response instanceof Response && $response->isNotification()) {
                    /** @var \Crustum\Mcp\Server\Content\Notification $content */
                    $content = $response->content();

                    yield JsonRpcResponse::notification(
                        ...$content->toArray(),
                    );

                    continue;
                }

                $pendingResponses[] = $response;
            }
        } catch (Throwable $throwable) {
            if ($this instanceof Errable) {
                yield $this->toJsonRpcResponse(
                    $request,
                    $this->toErrorResponse($throwable),
                    $serializable,
                );

                return;
            }

            throw $this->toJsonRpcException($throwable, $request->id);
        }

        yield $this->toJsonRpcResponse($request, $pendingResponses, $serializable);
    }

    /**
     * Execute a handler callback and convert failures into MCP responses.
     *
     * @param callable(): mixed $handler Handler callback
     * @param \Crustum\Mcp\Transport\JsonRpcRequest $request JSON-RPC request
     * @return mixed
     * @throws \Crustum\Mcp\Exception\JsonRpcException
     */
    protected function callHandler(callable $handler, JsonRpcRequest $request): mixed
    {
        try {
            return $handler();
        } catch (Throwable $throwable) {
            if ($this instanceof Errable) {
                return $this->toErrorResponse($throwable);
            }

            throw $this->toJsonRpcException($throwable, $request->id);
        }
    }

    /**
     * Convert a throwable into a JSON-RPC exception.
     *
     * @param \Throwable $exception Exception instance
     * @param mixed $requestId Request identifier
     * @return \Crustum\Mcp\Exception\JsonRpcException
     */
    protected function toJsonRpcException(Throwable $exception, mixed $requestId): JsonRpcException
    {
        if ($exception instanceof ValidationException) {
            return new JsonRpcException(ValidationMessages::from($exception), -32602, $requestId);
        }

        return new JsonRpcException($this->toErrorMessage($exception), -32603, $requestId);
    }

    /**
     * Convert a throwable into an MCP error response.
     *
     * @param \Throwable $exception Exception instance
     * @return \Crustum\Mcp\Response
     */
    protected function toErrorResponse(Throwable $exception): Response
    {
        if ($exception instanceof ValidationException) {
            return Response::error(ValidationMessages::from($exception));
        }

        if ($exception instanceof UnauthenticatedException || $exception instanceof ForbiddenException) {
            return Response::error($exception->getMessage());
        }

        return Response::error($this->toErrorMessage($exception));
    }

    /**
     * Build a user-facing error message for an exception.
     *
     * @param \Throwable $exception Exception instance
     * @return string
     */
    protected function toErrorMessage(Throwable $exception): string
    {
        if (Configure::read('debug')) {
            return $exception->getMessage();
        }

        return 'An internal server error occurred.';
    }

    /**
     * Determine whether content should be treated as binary.
     *
     * @param string $content Content string
     * @return bool
     */
    protected function isBinary(string $content): bool
    {
        return str_contains($content, "\0");
    }

    /**
     * Normalize a handler response into a response factory.
     *
     * @param \Crustum\Mcp\Response|\Crustum\Mcp\ResponseFactory|array<int, \Crustum\Mcp\Response|\Crustum\Mcp\ResponseFactory|string>|string $response Handler response
     * @return \Crustum\Mcp\ResponseFactory
     */
    private function toResponseFactory(Response|ResponseFactory|array|string $response): ResponseFactory
    {
        $responseFactory = is_array($response) && count($response) === 1
            ? reset($response)
            : $response;

        if ($responseFactory instanceof ResponseFactory) {
            return $responseFactory;
        }

        $items = is_array($responseFactory) ? $responseFactory : [$responseFactory];

        $responses = (new Collection($items))
            ->map(function (mixed $item): Response {
                if ($item instanceof Response) {
                    return $item;
                }

                if (!is_string($item)) {
                    throw new InvalidArgumentException('Response must be a Response instance or string');
                }

                return $this->isBinary($item)
                    ? Response::blob($item)
                    : Response::text($item);
            });

        return new ResponseFactory($responses->toList());
    }
}
