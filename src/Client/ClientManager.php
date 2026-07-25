<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client;

use Closure;
use Crustum\Mcp\Client as McpClient;
use Crustum\Mcp\Exception\ClientException;
use Crustum\Mcp\Trait\MacroableTrait;

/**
 * Registry for named MCP client instances.
 */
class ClientManager
{
    use MacroableTrait;

    /**
     * Shared client manager instance.
     *
     * @var self|null
     */
    protected static ?self $instance = null;

    /**
     * Registered client factories.
     *
     * @var array<string, \Closure(): \Crustum\Mcp\Client>
     */
    protected array $factories = [];

    /**
     * Resolved client instances.
     *
     * @var array<string, \Crustum\Mcp\Client>
     */
    protected array $clients = [];

    /**
     * Get the shared client manager instance.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        return static::$instance ??= new self();
    }

    /**
     * Replace the shared client manager instance.
     *
     * @param self|null $instance Client manager instance
     * @return void
     */
    public static function setInstance(?self $instance): void
    {
        static::$instance = $instance;
    }

    /**
     * Register a named MCP client factory.
     *
     * @param string $name Client name
     * @param \Closure(): \Crustum\Mcp\Client $factory Client factory
     * @return void
     */
    public function registerClient(string $name, Closure $factory): void
    {
        if (isset($this->clients[$name])) {
            $this->disconnect($this->clients[$name]);

            unset($this->clients[$name]);
        }

        $this->factories[$name] = $factory;
    }

    /**
     * Resolve a registered MCP client.
     *
     * @param string $name Client name
     * @return \Crustum\Mcp\Client
     */
    public function client(string $name): McpClient
    {
        return $this->clients[$name] ??= $this->build($name);
    }

    /**
     * Build a fresh MCP client instance.
     *
     * @param string $name Client name
     * @return \Crustum\Mcp\Client
     */
    public function build(string $name): McpClient
    {
        if (!array_key_exists($name, $this->factories)) {
            throw new ClientException("MCP client [{$name}] has not been registered.");
        }

        return ($this->factories[$name])()->setName($name);
    }

    /**
     * Disconnect all resolved MCP clients.
     *
     * @return void
     */
    public function disconnectAll(): void
    {
        foreach ($this->clients as $client) {
            $this->disconnect($client);
        }

        $this->clients = [];
    }

    /**
     * Disconnect a client while swallowing transport errors.
     *
     * @param \Crustum\Mcp\Client $client Client instance
     * @return void
     */
    protected function disconnect(McpClient $client): void
    {
        try {
            $client->disconnect();
        } catch (ClientException) {
        }
    }
}
