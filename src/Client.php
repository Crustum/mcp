<?php
declare(strict_types=1);

namespace Crustum\Mcp;

use Cake\Collection\Collection;
use Cake\Core\Configure;
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
use Crustum\Mcp\Client\Protocol;
use Crustum\Mcp\Client\Schema\InitializeResult;
use Crustum\Mcp\Client\Schema\PromptResult;
use Crustum\Mcp\Client\Schema\ResourceReadResult;
use Crustum\Mcp\Client\Schema\ToolResult;
use Crustum\Mcp\Client\Transport\HttpTransport;
use Crustum\Mcp\Client\Transport\StdioTransport;
use Crustum\Mcp\Client\Transport\TransportFactory;
use Crustum\Mcp\Schema\Implementation;

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
     * @param string $name Tool name
     * @param array<string, mixed> $arguments Tool arguments
     * @return \Crustum\Mcp\Client\Schema\ToolResult
     */
    public function callTool(string $name, array $arguments = []): ToolResult
    {
        return (new CallTool($name, $arguments))->handle($this->protocol);
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
        $this->name = $data['name'] ?? null;

        if ($this->name !== null) {
            $resolved = ClientManager::getInstance()->build($this->name);

            $this->transport = $resolved->transport;
            $this->clientInfo = $resolved->clientInfo;
        } else {
            $this->clientInfo = $data['clientInfo'];
            $this->transport = TransportFactory::fromRecipe($data['transport']);
        }

        $this->clientInfo ??= $this->defaultClientInfo();

        $this->protocol = new Protocol($this->transport, $this->clientInfo);
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
