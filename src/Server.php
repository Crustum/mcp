<?php
declare(strict_types=1);

namespace Crustum\Mcp;

use Cake\Core\Configure;
use Crustum\Mcp\Enums\ErrorCode;
use Crustum\Mcp\Enums\Extension;
use Crustum\Mcp\Enums\MetaKey;
use Crustum\Mcp\Enums\ProtocolVersion;
use Crustum\Mcp\Exception\JsonRpcException;
use Crustum\Mcp\Schema\Implementation;
use Crustum\Mcp\Server\AppResource;
use Crustum\Mcp\Server\Attributes\Cacheable;
use Crustum\Mcp\Server\Attributes\Instructions;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Attributes\Version;
use Crustum\Mcp\Server\Contracts\Transport;
use Crustum\Mcp\Server\McpRequestBuilder;
use Crustum\Mcp\Server\Methods\CallTool;
use Crustum\Mcp\Server\Methods\CompletionComplete;
use Crustum\Mcp\Server\Methods\Discover;
use Crustum\Mcp\Server\Methods\GetPrompt;
use Crustum\Mcp\Server\Methods\Initialize;
use Crustum\Mcp\Server\Methods\Listen;
use Crustum\Mcp\Server\Methods\ListPrompts;
use Crustum\Mcp\Server\Methods\ListResources;
use Crustum\Mcp\Server\Methods\ListResourceTemplates;
use Crustum\Mcp\Server\Methods\ListTools;
use Crustum\Mcp\Server\Methods\Ping;
use Crustum\Mcp\Server\Methods\ReadResource;
use Crustum\Mcp\Server\Methods\Trait\ResolvesResourcesTrait;
use Crustum\Mcp\Server\ServerContext;
use Crustum\Mcp\Server\Testing\PendingTestResponse;
use Crustum\Mcp\Server\Testing\TestListResponse;
use Crustum\Mcp\Server\Testing\TestResponse;
use Crustum\Mcp\Server\Trait\HasIconsTrait;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Mcp\Support\McpContainerBindings;
use Crustum\Mcp\Transport\JsonRpcNotification;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;
use InvalidArgumentException;
use Throwable;

/**
 * Base MCP server implementation.
 *
 * @mixin \Crustum\Mcp\Server\Testing\PendingTestResponse
 */
abstract class Server
{
    use HasIconsTrait;
    use ResolvesResourcesTrait;

    public const CAPABILITY_TOOLS = 'tools';

    public const CAPABILITY_RESOURCES = 'resources';

    public const CAPABILITY_PROMPTS = 'prompts';

    public const CAPABILITY_COMPLETIONS = 'completions';

    /**
     * Methods whose results may carry caching hints.
     *
     * @var array<int, string>
     */
    public const CACHEABLE_METHODS = [
        'server/discover',
        'tools/list',
        'prompts/list',
        'resources/list',
        'resources/templates/list',
        'resources/read',
    ];

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
     * MCP extensions advertised through the extensions capability.
     *
     * @var array<int, \Crustum\Mcp\Enums\Extension>
     */
    protected array $extensions = [];

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
     * @var array<int|string, \Crustum\Mcp\Server\Tool|class-string<\Crustum\Mcp\Server\Tool>|array<int, \Crustum\Mcp\Server\Tool|class-string<\Crustum\Mcp\Server\Tool>>>
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
        'server/discover' => Discover::class,
        'initialize' => Initialize::class,
        'ping' => Ping::class,
        'subscriptions/listen' => Listen::class,
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
        $requestId = null;

        try {
            $jsonRequest = json_decode($rawMessage, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new JsonRpcException('Parse error: Invalid JSON was received by the server.', ErrorCode::PARSE_ERROR->value);
            }

            $request = isset($jsonRequest['id'])
                ? JsonRpcRequest::from($jsonRequest)
                : JsonRpcNotification::from($jsonRequest);

            if ($request instanceof JsonRpcNotification) {
                return;
            }

            $requestId = $request->id;

            if (!$request->isLegacy()) {
                $this->validateProtocolMeta($request, $context);
            }

            if (!isset($this->methods[$request->method])) {
                throw new JsonRpcException(
                    "The method [{$request->method}] was not found.",
                    ErrorCode::METHOD_NOT_FOUND->value,
                    $request->id,
                );
            }

            $this->handleMessage($request, $context);
        } catch (JsonRpcException $exception) {
            $this->send($exception->toJsonRpcResponse(), $context);
        } catch (Throwable $exception) {
            if (Configure::read('debug')) {
                throw $exception;
            }

            $this->send(JsonRpcResponse::error(
                $requestId,
                ErrorCode::INTERNAL_ERROR->value,
                'Something went wrong while processing the request.',
            ), $context);
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
            supportedProtocolVersions: $this->supportedProtocolVersion ?: ProtocolVersion::serverSupported(),
            serverCapabilities: $this->resolvedCapabilities(),
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
     * Validate that a request carries the required protocol metadata.
     *
     * @param \Crustum\Mcp\Transport\JsonRpcRequest $request JSON-RPC request
     * @param \Crustum\Mcp\Server\ServerContext $context Server context
     * @return void
     * @throws \Crustum\Mcp\Exception\JsonRpcException
     */
    protected function validateProtocolMeta(JsonRpcRequest $request, ServerContext $context): void
    {
        $meta = $request->meta() ?? [];

        $expected = [
            MetaKey::PROTOCOL_VERSION->value => 'is_string',
            MetaKey::CLIENT_CAPABILITIES->value => fn(mixed $value): bool => is_array($value) && ($value === [] || !array_is_list($value)),
        ];

        foreach ($expected as $metaKey => $isValid) {
            if (!array_key_exists($metaKey, $meta) || !$isValid($meta[$metaKey])) {
                throw new JsonRpcException(
                    "Invalid params: The request [_meta] is missing the required [{$metaKey}] member.",
                    ErrorCode::INVALID_PARAMS->value,
                    $request->id,
                );
            }
        }

        $requestedVersion = $meta[MetaKey::PROTOCOL_VERSION->value];

        if (!in_array($requestedVersion, $context->supportedProtocolVersions, true)) {
            throw new JsonRpcException(
                'Unsupported protocol version',
                ErrorCode::UNSUPPORTED_PROTOCOL_VERSION->value,
                $request->id,
                [
                    'supported' => $context->supportedProtocolVersions,
                    'requested' => $requestedVersion,
                ],
            );
        }
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
            $this->send($response, $context, $request);

            return;
        }

        $this->transport->stream(function () use ($request, $response, $context): iterable {
            foreach ($response as $message) {
                $this->send($message, $context, $request);
            }

            return [];
        });
    }

    /**
     * Send a JSON-RPC response, completing every result with server info metadata.
     *
     * @param \Crustum\Mcp\Transport\JsonRpcResponse $response JSON-RPC response
     * @param \Crustum\Mcp\Server\ServerContext $context Server context
     * @param \Crustum\Mcp\Transport\JsonRpcRequest|null $request JSON-RPC request
     * @return void
     */
    protected function send(JsonRpcResponse $response, ServerContext $context, ?JsonRpcRequest $request = null): void
    {
        if ($request instanceof JsonRpcRequest && array_key_exists('result', $response->content)) {
            $result = (array)$response->content['result'];
            $result['_meta'][MetaKey::SERVER_INFO->value] = $context->implementation->toArray();

            $response->content['result'] = [
                'resultType' => 'complete',
                ...$this->resolveCacheHints($request, $context),
                ...$result,
            ];
        }

        $this->transport->send($response->toJson());
    }

    /**
     * Declare per-method caching hints.
     *
     * @return array<string, \Crustum\Mcp\Server\Attributes\Cacheable>
     */
    protected function cacheHints(): array
    {
        return [];
    }

    /**
     * Resolve caching hints for a request result.
     *
     * @param \Crustum\Mcp\Transport\JsonRpcRequest $request JSON-RPC request
     * @param \Crustum\Mcp\Server\ServerContext $context Server context
     * @return array<string, int|string>
     */
    protected function resolveCacheHints(JsonRpcRequest $request, ServerContext $context): array
    {
        if (!in_array($request->method, self::CACHEABLE_METHODS, true)) {
            return [];
        }

        if (isset($request->params['inputResponses']) || isset($request->params['requestState'])) {
            return [];
        }

        $cacheable = $this->resourceCacheable($request, $context)
            ?? $this->cacheHints()[$request->method]
            ?? $this->resolveAttribute(Cacheable::class)
            ?? new Cacheable();

        return $cacheable->toArray();
    }

    /**
     * Resolve the caching hint declared on the requested resource.
     *
     * @param \Crustum\Mcp\Transport\JsonRpcRequest $request JSON-RPC request
     * @param \Crustum\Mcp\Server\ServerContext $context Server context
     * @return \Crustum\Mcp\Server\Attributes\Cacheable|null
     */
    protected function resourceCacheable(JsonRpcRequest $request, ServerContext $context): ?Cacheable
    {
        if ($request->method !== 'resources/read') {
            return null;
        }

        try {
            return $this->resolveResource($request->get('uri'), $context)->cacheable();
        } catch (InvalidArgumentException) {
            return null;
        }
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
     * Merge declared extensions into the advertised capabilities.
     *
     * @return array<string, mixed>
     */
    protected function resolvedCapabilities(): array
    {
        $extensions = [];

        foreach ($this->extensions as $extension) {
            $extensions[$extension->value] = (object)[];
        }

        return $extensions === []
            ? $this->capabilities
            : [...$this->capabilities, 'extensions' => $extensions];
    }

    /**
     * Detect and register the UI extension when app resources are present.
     *
     * @return void
     */
    protected function detectUiCapability(): void
    {
        foreach ($this->resources as $resource) {
            if (is_subclass_of($resource, AppResource::class)) {
                $this->extensions[] = Extension::Ui;

                return;
            }
        }
    }

    /**
     * Proxy static calls to the pending test response helper.
     *
     * @param string $name Method name
     * @param array<int, mixed> $arguments Method arguments
     * @return \Crustum\Mcp\Server\Testing\PendingTestResponse|\Crustum\Mcp\Server\Testing\TestResponse|\Crustum\Mcp\Server\Testing\TestListResponse
     */
    public static function __callStatic(string $name, array $arguments): PendingTestResponse|TestResponse|TestListResponse
    {
        $pendingTestResponse = new PendingTestResponse(static::class);

        return $pendingTestResponse->{$name}(...$arguments);
    }
}
