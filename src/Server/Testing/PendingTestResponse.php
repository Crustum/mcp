<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Testing;

use Cake\Event\EventManager;
use Crustum\Mcp\Event\McpRequestBuildingEvent;
use Crustum\Mcp\Exception\JsonRpcException;
use Crustum\Mcp\Server;
use Crustum\Mcp\Server\Contracts\HasUriTemplate;
use Crustum\Mcp\Server\McpRequestBuilder;
use Crustum\Mcp\Server\Primitive;
use Crustum\Mcp\Server\Prompt;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Transport\FakeTransporter;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Mcp\Support\UriTemplate;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;
use InvalidArgumentException;
use Stringable;

/**
 * Pending MCP server test response builder.
 */
class PendingTestResponse
{
    /**
     * @param class-string<\Crustum\Mcp\Server> $serverClass Server class name
     */
    public function __construct(
        protected string $serverClass,
        protected mixed $actingAs = null,
    ) {
    }

    /**
     * Act as an authenticated principal for the next test request.
     *
     * @param mixed $principal Authenticated principal
     * @return static
     */
    public function actingAs(mixed $principal): static
    {
        $clone = clone $this;
        $clone->actingAs = $principal;

        return $clone;
    }

    /**
     * Execute a tools/call test request.
     *
     * @param \Crustum\Mcp\Server\Tool|class-string<\Crustum\Mcp\Server\Tool> $tool Tool instance or class name
     * @param array<string, mixed> $arguments Tool arguments
     * @return \Crustum\Mcp\Server\Testing\TestResponse
     */
    public function tool(Tool|string $tool, array $arguments = []): TestResponse
    {
        return $this->run('tools/call', $tool, $arguments);
    }

    /**
     * Execute a prompts/get test request.
     *
     * @param \Crustum\Mcp\Server\Prompt|class-string<\Crustum\Mcp\Server\Prompt> $prompt Prompt instance or class name
     * @param array<string, mixed> $arguments Prompt arguments
     * @return \Crustum\Mcp\Server\Testing\TestResponse
     */
    public function prompt(Prompt|string $prompt, array $arguments = []): TestResponse
    {
        return $this->run('prompts/get', $prompt, $arguments);
    }

    /**
     * Execute a resources/read test request.
     *
     * @param \Crustum\Mcp\Server\Resource|class-string<\Crustum\Mcp\Server\Resource> $resource Resource instance or class name
     * @param array<string, mixed> $arguments Resource arguments
     * @return \Crustum\Mcp\Server\Testing\TestResponse
     */
    public function resource(Resource|string $resource, array $arguments = []): TestResponse
    {
        return $this->run('resources/read', $resource, $arguments);
    }

    /**
     * Execute a completion/complete test request.
     *
     * @param \Crustum\Mcp\Server\Primitive|class-string<\Crustum\Mcp\Server\Primitive> $primitive Completable primitive
     * @param string $argumentName Argument name
     * @param string $argumentValue Partial argument value
     * @param array<string, mixed> $currentArgs Current argument values
     * @return \Crustum\Mcp\Server\Testing\TestResponse
     */
    public function completion(
        Primitive|string $primitive,
        string $argumentName,
        string $argumentValue = '',
        array $currentArgs = [],
    ): TestResponse {
        $primitive = $this->resolvePrimitive($primitive);
        $server = $this->initializeServer();

        $request = new JsonRpcRequest(
            uniqid(),
            'completion/complete',
            [
                'ref' => $this->buildCompletionRef($primitive),
                'argument' => [
                    'name' => $argumentName,
                    'value' => $argumentValue,
                ],
                'context' => [
                    'arguments' => $currentArgs,
                ],
            ],
        );

        $response = $this->executeRequest($server, $request);

        return new TestResponse($primitive, $response, $this->actingAs);
    }

    /**
     * Build a completion reference payload for a primitive.
     *
     * @param \Crustum\Mcp\Server\Primitive $primitive Primitive instance
     * @return array<string, mixed>
     */
    protected function buildCompletionRef(Primitive $primitive): array
    {
        return match (true) {
            $primitive instanceof Prompt => [
                'type' => 'ref/prompt',
                'name' => $primitive->name(),
            ],
            $primitive instanceof Resource => [
                'type' => 'ref/resource',
                'uri' => $primitive->uri(),
            ],
            default => throw new InvalidArgumentException('Unsupported primitive type for completion.'),
        };
    }

    /**
     * Resolve a primitive instance from a class name when needed.
     *
     * @param \Crustum\Mcp\Server\Primitive|class-string<\Crustum\Mcp\Server\Primitive> $primitive Primitive instance or class name
     * @return \Crustum\Mcp\Server\Primitive
     */
    protected function resolvePrimitive(Primitive|string $primitive): Primitive
    {
        if (!is_string($primitive)) {
            return $primitive;
        }

        $container = ContainerRegistry::getInstance();

        if ($container->has($primitive)) {
            return $container->get($primitive);
        }

        return new $primitive();
    }

    /**
     * Initialize the server under test.
     *
     * @return \Crustum\Mcp\Server
     */
    protected function initializeServer(): Server
    {
        $server = new ($this->serverClass)(new FakeTransporter());
        $server->start();

        return $server;
    }

    /**
     * Execute a JSON-RPC request against the server under test.
     *
     * @param \Crustum\Mcp\Server $server Server instance
     * @param \Crustum\Mcp\Transport\JsonRpcRequest $request JSON-RPC request
     * @return \Crustum\Mcp\Transport\JsonRpcResponse|iterable<\Crustum\Mcp\Transport\JsonRpcResponse>
     */
    protected function executeRequest(Server $server, JsonRpcRequest $request): iterable|JsonRpcResponse
    {
        $listener = $this->registerIdentityListener();

        try {
            return (fn(): iterable|JsonRpcResponse => $this->runMethodHandle(
                $request,
                $this->createContext(),
            ))->call($server);
        } catch (JsonRpcException $jsonRpcException) {
            return $jsonRpcException->toJsonRpcResponse();
        } finally {
            $this->unregisterIdentityListener($listener);
            McpRequestBuilder::reset();
        }
    }

    /**
     * Register a one-shot listener that applies the test principal.
     *
     * @return callable(\Crustum\Mcp\Event\McpRequestBuildingEvent): void|null
     */
    protected function registerIdentityListener(): ?callable
    {
        if ($this->actingAs === null) {
            return null;
        }

        $principal = $this->actingAs;
        $listener = static function (McpRequestBuildingEvent $event) use ($principal): void {
            $event->getMcpRequest()->setIdentity($principal);
        };

        EventManager::instance()->on(McpRequestBuildingEvent::NAME, $listener);

        return $listener;
    }

    /**
     * Remove the test identity listener after a request.
     *
     * @param callable(\Crustum\Mcp\Event\McpRequestBuildingEvent): void|null $listener Registered listener
     * @return void
     */
    protected function unregisterIdentityListener(?callable $listener): void
    {
        if ($listener === null) {
            return;
        }

        EventManager::instance()->off(McpRequestBuildingEvent::NAME, $listener);
    }

    /**
     * Execute a JSON-RPC method test request for a primitive.
     *
     * @param string $method JSON-RPC method name
     * @param \Crustum\Mcp\Server\Primitive|class-string<\Crustum\Mcp\Server\Primitive> $primitive Primitive instance or class name
     * @param array<string, mixed> $arguments Request arguments
     * @return \Crustum\Mcp\Server\Testing\TestResponse
     */
    protected function run(string $method, Primitive|string $primitive, array $arguments = []): TestResponse
    {
        $primitive = $this->resolvePrimitive($primitive);
        $server = $this->initializeServer();

        $params = [
            ...$primitive->toMethodCall(),
            'arguments' => $arguments,
        ];

        if ($method === 'resources/read' && $primitive instanceof HasUriTemplate) {
            $params['uri'] = $this->expandUriTemplate($primitive->uriTemplate(), $arguments);
        }

        $request = new JsonRpcRequest(uniqid(), $method, $params);
        $response = $this->executeRequest($server, $request);

        return new TestResponse($primitive, $response, $this->actingAs);
    }

    /**
     * Expand a URI template using provided variables.
     *
     * @param \Crustum\Mcp\Support\UriTemplate $template URI template
     * @param array<string, mixed> $variables Template variables
     * @return string
     */
    protected function expandUriTemplate(UriTemplate $template, array $variables): string
    {
        $expanded = (string)$template;

        foreach ($template->variableNames() as $name) {
            if (!array_key_exists($name, $variables)) {
                throw new InvalidArgumentException("Missing value for URI template variable [{$name}].");
            }

            $value = $variables[$name];

            if (!is_scalar($value) && !$value instanceof Stringable) {
                throw new InvalidArgumentException("URI template variable [{$name}] must be a scalar or Stringable value.");
            }

            $value = (string)$value;

            if (str_contains($value, '/')) {
                throw new InvalidArgumentException("URI template variable [{$name}] value must not contain '/'.");
            }

            $expanded = str_replace('{' . $name . '}', $value, $expanded);
        }

        return $expanded;
    }
}
