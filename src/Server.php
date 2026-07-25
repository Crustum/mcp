<?php
declare(strict_types=1);

namespace Crustum\Mcp;

use Cake\Core\Configure;
use Cake\Event\EventManager;
use Crustum\Mcp\Event\SessionInitializedEvent;
use Crustum\Mcp\Exception\JsonRpcException;
use Crustum\Mcp\Schema\Implementation;
use Crustum\Mcp\Server\AppResource;
use Crustum\Mcp\Server\Attributes\Instructions;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Attributes\Version;
use Crustum\Mcp\Server\Contracts\Transport;
use Crustum\Mcp\Server\Enums\ProtocolVersion;
use Crustum\Mcp\Server\McpRequestBuilder;
use Crustum\Mcp\Server\Methods\CallTool;
use Crustum\Mcp\Server\Methods\CompletionComplete;
use Crustum\Mcp\Server\Methods\GetPrompt;
use Crustum\Mcp\Server\Methods\Initialize;
use Crustum\Mcp\Server\Methods\ListPrompts;
use Crustum\Mcp\Server\Methods\ListResources;
use Crustum\Mcp\Server\Methods\ListResourceTemplates;
use Crustum\Mcp\Server\Methods\ListTools;
use Crustum\Mcp\Server\Methods\Ping;
use Crustum\Mcp\Server\Methods\ReadResource;
use Crustum\Mcp\Server\ServerContext;
use Crustum\Mcp\Server\Testing\PendingTestResponse;
use Crustum\Mcp\Server\Testing\TestResponse;
use Crustum\Mcp\Server\Trait\HasIconsTrait;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Mcp\Support\McpContainerBindings;
use Crustum\Mcp\Transport\JsonRpcNotification;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;
use Throwable;

/**
 * Base MCP server implementation.
 *
 * @mixin \Crustum\Mcp\Server\Testing\PendingTestResponse
 */
abstract class Server
{
    use HasIconsTrait;

    public const CAPABILITY_TOOLS = 'tools';

    public const CAPABILITY_RESOURCES = 'resources';

    public const CAPABILITY_PROMPTS = 'prompts';

    public const CAPABILITY_COMPLETIONS = 'completions';

    public const CAPABILITY_UI = 'io.modelcontextprotocol/ui';

    /**
     * @var string
     */
    protected string $name = 'CakePHP MCP Server';

    /**
     * @var string
     */
    protected string $version = '0.0.1';

    /**
     * @var string
     */
    protected string $instructions = <<<'MARKDOWN'
        This MCP server lets AI agents interact with this CakePHP application.
    MARKDOWN;

    /**
     * @var array<int, string>
     */
    protected array $supportedProtocolVersion = [];

    /**
     * @var array<string, array<string, bool>|\stdClass|string>
     */
    protected array $capabilities = [
        self::CAPABILITY_TOOLS => [
            'listChanged' => false,
        ],
        self::CAPABILITY_RESOURCES => [
            'listChanged' => false,
        ],
        self::CAPABILITY_PROMPTS => [
            'listChanged' => false,
        ],
    ];

    /**
     * @var array<int, \Crustum\Mcp\Server\Tool|class-string<\Crustum\Mcp\Server\Tool>>
     */
    protected array $tools = [];

    /**
     * @var array<int, \Crustum\Mcp\Server\Resource|class-string<\Crustum\Mcp\Server\Resource>>
     */
    protected array $resources = [];

    /**
     * @var array<int, \Crustum\Mcp\Server\Prompt|class-string<\Crustum\Mcp\Server\Prompt>>
     */
    protected array $prompts = [];

    /**
     * @var int
     */
    public int $maxPaginationLength = 50;

    /**
     * @var int
     */
    public int $defaultPaginationLength = 15;

    /**
     * @var array<string, class-string<\Crustum\Mcp\Server\Contracts\Method>>
     */
    protected array $methods = [
        'tools/list' => ListTools::class,
        'tools/call' => CallTool::class,
        'resources/list' => ListResources::class,
        'resources/read' => ReadResource::class,
        'resources/templates/list' => ListResourceTemplates::class,
        'prompts/list' => ListPrompts::class,
        'prompts/get' => GetPrompt::class,
        'completion/complete' => CompletionComplete::class,
        'ping' => Ping::class,
    ];

    /**
     * @param \Crustum\Mcp\Server\Contracts\Transport $transport Server transport
     */
    public function __construct(
        protected Transport $transport,
    ) {
    }

    /**
     * Add or modify a server capability.
     *
     * @param string $key Capability key
     * @param bool $value Capability value
     * @return void
     */
    public function addCapability(string $key, bool $value = true): void
    {
        if (str_contains($key, '.')) {
            [$root, $child] = explode('.', $key, 2);
            $existing = $this->capabilities[$root] ?? [];

            if (!is_array($existing)) {
                $existing = [];
            }

            $existing[$child] = $value;
            $this->capabilities[$root] = $existing;

            return;
        }

        $this->capabilities[$key] = (object)[];
    }

    /**
     * Register a custom JSON-RPC method handler.
     *
     * @param string $method JSON-RPC method name
     * @param class-string<\Crustum\Mcp\Server\Contracts\Method> $handler Handler class name
     * @return void
     */
    public function addMethod(string $method, string $handler): void
    {
        $this->methods[$method] = $handler;
    }

    /**
     * Boot the server transport loop.
     *
     * @return void
     */
    public function start(): void
    {
        $this->boot();
        $this->detectUiCapability();

        $this->transport->onReceive($this->handle(...));
    }

    /**
     * Boot hook for subclasses.
     *
     * @return void
     */
    protected function boot(): void
    {
    }

    /**
     * Handle an incoming raw JSON-RPC message.
     *
     * @param string $rawMessage Raw JSON payload
     * @return void
     */
    public function handle(string $rawMessage): void
    {
        $context = $this->createContext();
        $request = null;

        try {
            $jsonRequest = json_decode($rawMessage, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new JsonRpcException('Parse error: Invalid JSON was received by the server.', -32700);
            }

            $request = isset($jsonRequest['id'])
                ? JsonRpcRequest::from($jsonRequest, $this->transport->sessionId())
                : JsonRpcNotification::from($jsonRequest);

            if ($request instanceof JsonRpcNotification) {
                return;
            }

            if ($request->method === 'initialize') {
                $this->handleInitializeMessage($request, $context);

                return;
            }

            if (!isset($this->methods[$request->method])) {
                throw new JsonRpcException(
                    "The method [{$request->method}] was not found.",
                    -32601,
                    $request->id,
                );
            }

            $this->handleMessage($request, $context);
        } catch (JsonRpcException $exception) {
            $this->transport->send($exception->toJsonRpcResponse()->toJson());
        } catch (Throwable $exception) {
            if (Configure::read('debug')) {
                throw $exception;
            }

            $jsonRpcResponse = JsonRpcResponse::error(
                $request?->id,
                -32603,
                'Something went wrong while processing the request.',
            );

            $this->transport->send($jsonRpcResponse->toJson());
        }
    }

    /**
     * Create the server runtime context.
     *
     * @return \Crustum\Mcp\Server\ServerContext
     */
    public function createContext(): ServerContext
    {
        $name = $this->resolveAttribute(Name::class);
        $version = $this->resolveAttribute(Version::class);
        $instructions = $this->resolveAttribute(Instructions::class);

        return new ServerContext(
            supportedProtocolVersions: $this->supportedProtocolVersion ?: ProtocolVersion::supported(),
            serverCapabilities: $this->capabilities,
            implementation: new Implementation(
                name: $name !== null ? $name->value : $this->name,
                version: $version !== null ? $version->value : $this->version,
                icons: $this->resolvedIcons(),
            ),
            instructions: $instructions !== null ? $instructions->value : $this->instructions,
            maxPaginationLength: $this->maxPaginationLength,
            defaultPaginationLength: $this->defaultPaginationLength,
            tools: $this->tools,
            resources: $this->resources,
            prompts: $this->prompts,
        );
    }

    /**
     * Get icon definitions declared on the server.
     *
     * @return list<\Crustum\Mcp\Schema\Icon>
     */
    protected function icons(): array
    {
        return [];
    }

    /**
     * Handle a JSON-RPC request message.
     *
     * @param \Crustum\Mcp\Transport\JsonRpcRequest $request JSON-RPC request
     * @param \Crustum\Mcp\Server\ServerContext $context Server context
     * @return void
     */
    protected function handleMessage(JsonRpcRequest $request, ServerContext $context): void
    {
        $response = $this->runMethodHandle($request, $context);

        if (!is_iterable($response)) {
            $this->transport->send($response->toJson());

            return;
        }

        $this->transport->stream(function () use ($response): iterable {
            foreach ($response as $message) {
                $this->transport->send($message->toJson());
            }

            return [];
        });
    }

    /**
     * Execute a JSON-RPC method handler.
     *
     * @param \Crustum\Mcp\Transport\JsonRpcRequest $request JSON-RPC request
     * @param \Crustum\Mcp\Server\ServerContext $context Server context
     * @return \Crustum\Mcp\Transport\JsonRpcResponse|iterable<\Crustum\Mcp\Transport\JsonRpcResponse>
     */
    protected function runMethodHandle(JsonRpcRequest $request, ServerContext $context): iterable|JsonRpcResponse
    {
        $container = ContainerRegistry::getInstance();

        /** @var class-string<\Crustum\Mcp\Server\Contracts\Method> $methodClassName */
        $methodClassName = $this->methods[$request->method];
        /** @var \Crustum\Mcp\Server\Contracts\Method $methodClass */
        $methodClass = $container->has($methodClassName)
            ? $container->get($methodClassName)
            : new $methodClassName();

        McpRequestBuilder::usingHttpRequest($this->transport->httpRequest());
        $mcpRequest = McpRequestBuilder::build($request);
        McpContainerBindings::bindRequest($container, $mcpRequest);

        try {
            return $methodClass->handle($request, $context);
        } finally {
            McpContainerBindings::releaseRequest($container);
            McpRequestBuilder::reset();
        }
    }

    /**
     * Handle the MCP initialize handshake.
     *
     * @param \Crustum\Mcp\Transport\JsonRpcRequest $request JSON-RPC request
     * @param \Crustum\Mcp\Server\ServerContext $context Server context
     * @return void
     */
    protected function handleInitializeMessage(JsonRpcRequest $request, ServerContext $context): void
    {
        $response = (new Initialize())->handle($request, $context);
        $sessionId = $this->generateSessionId();

        EventManager::instance()->dispatch(new SessionInitializedEvent(
            $this,
            $sessionId,
            $request->params['clientInfo'] ?? null,
            $request->params['protocolVersion'] ?? null,
            $request->params['capabilities'] ?? null,
        ));

        $this->transport->send($response->toJson(), $sessionId);
    }

    /**
     * Generate a new MCP session identifier.
     *
     * @return string
     */
    protected function generateSessionId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Detect and register UI capability when app resources are present.
     *
     * @return void
     */
    protected function detectUiCapability(): void
    {
        if (array_key_exists(self::CAPABILITY_UI, $this->capabilities)) {
            return;
        }

        foreach ($this->resources as $resource) {
            if (is_subclass_of($resource, AppResource::class)) {
                $this->addCapability(self::CAPABILITY_UI);

                return;
            }
        }
    }

    /**
     * Proxy static calls to the pending test response helper.
     *
     * @param string $name Method name
     * @param array<int, mixed> $arguments Method arguments
     * @return \Crustum\Mcp\Server\Testing\PendingTestResponse|\Crustum\Mcp\Server\Testing\TestResponse
     */
    public static function __callStatic(string $name, array $arguments): PendingTestResponse|TestResponse
    {
        $pendingTestResponse = new PendingTestResponse(static::class);

        return $pendingTestResponse->{$name}(...$arguments);
    }
}
