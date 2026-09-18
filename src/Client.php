<?php
declare(strict_types=1);

namespace Crustum\Mcp;

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Cake\Utility\Hash;
use Crustum\Mcp\Client\ClientManager;
use Crustum\Mcp\Client\Contracts\Transport;
use Crustum\Mcp\Client\Exception\AuthorizationRequiredException;
use Crustum\Mcp\Client\Methods\Ping;
use Crustum\Mcp\Client\Methods\Prompts\GetPrompt;
use Crustum\Mcp\Client\Methods\Prompts\ListPrompts;
use Crustum\Mcp\Client\Methods\Resources\ListResources;
use Crustum\Mcp\Client\Methods\Resources\ReadResource;
use Crustum\Mcp\Client\Methods\Tools\CallTool;
use Crustum\Mcp\Client\Methods\Tools\ListTools;
use Crustum\Mcp\Client\Primitives\Tool;
use Crustum\Mcp\Client\Protocol;
use Crustum\Mcp\Client\ResponseCache;
use Crustum\Mcp\Client\Schema\DiscoverResult;
use Crustum\Mcp\Client\Schema\InitializeResult;
use Crustum\Mcp\Client\Schema\PromptResult;
use Crustum\Mcp\Client\Schema\ResourceReadResult;
use Crustum\Mcp\Client\Schema\ToolResult;
use Crustum\Mcp\Client\Transport\HttpTransport;
use Crustum\Mcp\Client\Transport\StdioTransport;
use Crustum\Mcp\Client\Transport\TransportFactory;
use Crustum\Mcp\Enums\ErrorCode;
use Crustum\Mcp\Enums\ProtocolVersion;
use Crustum\Mcp\Exception\ClientException;
use Crustum\Mcp\Exception\JsonRpcException;
use Crustum\Mcp\Schema\Implementation;
use Crustum\Mcp\Support\MirroredParameters;

/**
 * MCP client for local and remote servers.
 */
class Client
{
    /**
     * JSON-RPC protocol handler.
     *
     * @var \Crustum\Mcp\Client\Protocol
     */
    protected Protocol $protocol;

    /**
     * Registered client name for manager-backed unserialization.
     *
     * @var string|null
     */
    protected ?string $name = null;

    /**
     * Create a new MCP client.
     *
     * @param \Crustum\Mcp\Client\Contracts\Transport $transport Client transport
     * @param \Crustum\Mcp\Schema\Implementation|null $clientInfo Client implementation metadata
     */
    public function __construct(
        protected Transport $transport,
        public ?Implementation $clientInfo = null,
    ) {
        $this->clientInfo = $clientInfo ?? $this->defaultClientInfo();

        $this->protocol = new Protocol($this->transport, $this->clientInfo);
    }

    /**
     * Build default client implementation metadata.
     *
     * @return \Crustum\Mcp\Schema\Implementation
     */
    protected function defaultClientInfo(): Implementation
    {
        return new Implementation(
            name: (string)Configure::read('App.name', 'CakePHP MCP Client'),
            version: '0.0.1',
        );
    }

    /**
     * Set the registered client name.
     *
     * @param string|null $name Client name
     * @return static
     */
    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the registered client name.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Create a client backed by a local stdio subprocess.
     *
     * @param string $command Subprocess command
     * @param array<int, string> $args Subprocess arguments
     * @return self
     */
    public static function local(string $command, array $args = []): self
    {
        return new self(new StdioTransport($command, $args));
    }

    /**
     * Create a client backed by a remote HTTP MCP server.
     *
     * @param string $url MCP server URL
     * @return \Crustum\Mcp\WebClient
     */
    public static function web(string $url): WebClient
    {
        return new WebClient(new HttpTransport($url));
    }

    /**
     * Configure the transport timeout.
     *
     * @param float $seconds Timeout in seconds
     * @return static
     */
    public function withTimeout(float $seconds): static
    {
        $this->transport->setTimeoutSeconds($seconds);

        return $this;
    }

    /**
     * Enable client-side response caching.
     *
     * @param string|null $store Cache engine name (null = default)
     * @param string|null $for Authorization context discriminator
     * @return static
     */
    public function withCache(?string $store = null, ?string $for = null): static
    {
        $this->protocol->useCache(new ResponseCache($store, $for));

        return $this;
    }

    /**
     * Disable client-side response caching.
     *
     * @return static
     */
    public function withoutCache(): static
    {
        $this->protocol->useCache(null);

        return $this;
    }

    /**
     * Connect and initialize the MCP session.
     *
     * @return static
     */
    public function connect(): static
    {
        $this->protocol->connect();

        return $this;
    }

    /**
     * Disconnect from the MCP server.
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->protocol->disconnect();
    }

    /**
     * Determine whether the client is connected.
     *
     * @return bool
     */
    public function connected(): bool
    {
        return $this->protocol->connected();
    }

    /**
     * Get the initialize handshake result.
     *
     * @return \Crustum\Mcp\Client\Schema\InitializeResult|null
     */
    public function initializeResult(): ?InitializeResult
    {
        return $this->protocol->initializeResult();
    }

    /**
     * Get the discover handshake result.
     *
     * @return \Crustum\Mcp\Client\Schema\DiscoverResult|null
     */
    public function discoverResult(): ?DiscoverResult
    {
        return $this->protocol->discoverResult();
    }

    /**
     * Get the negotiated server capabilities.
     *
     * @return array<string, mixed>
     */
    public function capabilities(): array
    {
        $this->protocol->connect();

        return $this->protocol->capabilities();
    }

    /**
     * Get the negotiated server implementation.
     *
     * @return \Crustum\Mcp\Schema\Implementation|null
     */
    public function serverInfo(): ?Implementation
    {
        $this->protocol->connect();

        return $this->protocol->serverInfo();
    }

    /**
     * Get the negotiated server instructions.
     *
     * @return string|null
     */
    public function instructions(): ?string
    {
        $this->protocol->connect();

        return $this->protocol->instructions();
    }

    /**
     * Pin the protocol version used by the client.
     *
     * @param \Crustum\Mcp\Enums\ProtocolVersion|null $version Protocol version to pin
     * @return static
     */
    public function withProtocolVersion(?ProtocolVersion $version): static
    {
        if ($version instanceof ProtocolVersion && !in_array($version->value, ProtocolVersion::clientSupported(), true)) {
            throw new ClientException(sprintf(
                'This client does not support protocol version [%s]. It supports [%s].',
                $version->value,
                implode(', ', ProtocolVersion::clientSupported()),
            ));
        }

        $this->protocol->pinProtocolVersion($version);

        return $this;
    }

    /**
     * Get the negotiated protocol version.
     *
     * @return \Crustum\Mcp\Enums\ProtocolVersion
     */
    public function protocolVersion(): ProtocolVersion
    {
        $this->protocol->connect();

        return $this->protocol->connectionProtocol();
    }

    /**
     * Ping the MCP server.
     *
     * @return void
     */
    public function ping(): void
    {
        (new Ping())->handle($this->protocol);
    }

    /**
     * List available tools from the MCP server.
     *
     * @param int|null $limit Maximum number of tools to fetch
     * @param iterable<string, \Crustum\Mcp\Client\Primitives\Tool>|null $default Fallback tools when authorization is required
     * @return \Cake\Collection\Collection<string, \Crustum\Mcp\Client\Primitives\Tool>
     */
    public function tools(?int $limit = null, ?iterable $default = null): Collection
    {
        try {
            return (new ListTools(client: $this, limit: $limit))->handle($this->protocol);
        } catch (AuthorizationRequiredException $authorizationRequiredException) {
            if ($default === null) {
                throw $authorizationRequiredException;
            }

            return new Collection($default);
        }
    }

    /**
     * Call a tool on the MCP server.
     *
     * @param \Crustum\Mcp\Client\Primitives\Tool|string $tool Tool primitive or name
     * @param array<string, mixed> $arguments Tool arguments
     * @return \Crustum\Mcp\Client\Schema\ToolResult
     */
    public function callTool(Tool|string $tool, array $arguments = []): ToolResult
    {
        $name = $tool instanceof Tool ? $tool->name : $tool;
        $mirroredParameters = $tool instanceof Tool ? $tool->mirroredParameters() : null;

        try {
            return (new CallTool($name, $arguments, $mirroredParameters))->handle($this->protocol);
        } catch (JsonRpcException $jsonRpcException) {
            if ($jsonRpcException->getCode() !== ErrorCode::HEADER_MISMATCH->value) {
                throw $jsonRpcException;
            }

            $refreshedTools = $this->tools()->toArray();
            $refreshed = ($refreshedTools[$name] ?? null)?->mirroredParameters();

            if (
                !$refreshed instanceof MirroredParameters
                || $refreshed->headers($arguments) === ($mirroredParameters?->headers($arguments) ?? [])
            ) {
                throw $jsonRpcException;
            }

            return (new CallTool($name, $arguments, $refreshed))->handle($this->protocol);
        }
    }

    /**
     * List available prompts from the MCP server.
     *
     * @param int|null $limit Maximum number of prompts to fetch
     * @param iterable<string, \Crustum\Mcp\Client\Primitives\Prompt>|null $default Fallback prompts when authorization is required
     * @return \Cake\Collection\Collection<string, \Crustum\Mcp\Client\Primitives\Prompt>
     */
    public function prompts(?int $limit = null, ?iterable $default = null): Collection
    {
        try {
            return (new ListPrompts(limit: $limit))->handle($this->protocol);
        } catch (AuthorizationRequiredException $authorizationRequiredException) {
            if ($default === null) {
                throw $authorizationRequiredException;
            }

            return new Collection($default);
        }
    }

    /**
     * Get a prompt from the MCP server.
     *
     * @param string $name Prompt name
     * @param array<string, mixed> $arguments Prompt arguments
     * @return \Crustum\Mcp\Client\Schema\PromptResult
     */
    public function getPrompt(string $name, array $arguments = []): PromptResult
    {
        return (new GetPrompt($name, $arguments))->handle($this->protocol);
    }

    /**
     * List available resources from the MCP server.
     *
     * @param int|null $limit Maximum number of resources to fetch
     * @param iterable<string, \Crustum\Mcp\Client\Primitives\Resource>|null $default Fallback resources when authorization is required
     * @return \Cake\Collection\Collection<string, \Crustum\Mcp\Client\Primitives\Resource>
     */
    public function resources(?int $limit = null, ?iterable $default = null): Collection
    {
        try {
            return (new ListResources(limit: $limit))->handle($this->protocol);
        } catch (AuthorizationRequiredException $authorizationRequiredException) {
            if ($default === null) {
                throw $authorizationRequiredException;
            }

            return new Collection($default);
        }
    }

    /**
     * Read a resource from the MCP server.
     *
     * @param string $uri Resource URI
     * @return \Crustum\Mcp\Client\Schema\ResourceReadResult
     */
    public function readResource(string $uri): ResourceReadResult
    {
        return (new ReadResource($uri))->handle($this->protocol);
    }

    /**
     * Serialize the client for storage.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        if ($this->name !== null) {
            return ['name' => $this->name];
        }

        return [
            'name' => null,
            'clientInfo' => $this->clientInfo,
            'transport' => $this->transport->recipe(),
            'protocolVersion' => $this->protocol->pinnedProtocolVersion()?->value,
            'cache' => $this->protocol->cache(),
        ];
    }

    /**
     * Restore a serialized client instance.
     *
     * @param array<string, mixed> $data Serialized client data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->name = Hash::get($data, 'name');
        $cache = null;

        if ($this->name !== null) {
            $resolved = ClientManager::getInstance()->build($this->name);

            $this->transport = $resolved->transport;
            $this->clientInfo = $resolved->clientInfo;
            $pinned = $resolved->protocol->pinnedProtocolVersion();
        } else {
            $this->clientInfo = Hash::get($data, 'clientInfo');
            $this->transport = TransportFactory::fromRecipe(Hash::get($data, 'transport'));
            $pinned = ProtocolVersion::tryFrom((string)Hash::get($data, 'protocolVersion'));
            $cache = Hash::get($data, 'cache');
        }

        $this->clientInfo ??= $this->defaultClientInfo();

        $this->protocol = new Protocol($this->transport, $this->clientInfo, $pinned);
        $this->protocol->useCache($cache instanceof ResponseCache ? $cache : null);
    }

    /**
     * Disconnect when the client instance is destroyed.
     */
    public function __destruct()
    {
        if ($this->connected()) {
            $this->disconnect();
        }
    }
}
