<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client;

use Cake\Core\Configure;
use Closure;
use Crustum\Mcp\Client as McpClient;
use Crustum\Mcp\Enum\ProtocolVersion;
use Crustum\Mcp\Exception\ClientException;
use Crustum\Mcp\Trait\MacroableTrait;
use Crustum\Mcp\WebClient;

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
        $client = match (true) {
            isset($this->factories[$name]) => ($this->factories[$name])(),
            is_array(Configure::read("Mcp.clients.{$name}")) => $this->fromConfig($name),
            default => throw new ClientException("MCP client [{$name}] has not been registered."),
        };

        return $client->setName($name);
    }

    /**
     * Build a client from application configuration.
     *
     * @param string $name Client name
     * @return \Crustum\Mcp\Client
     * @throws \Crustum\Mcp\Exception\ClientException When neither a URL nor a command is configured
     */
    protected function fromConfig(string $name): McpClient
    {
        $key = "Mcp.clients.{$name}";
        $url = Configure::read("{$key}.url");
        $command = Configure::read("{$key}.command");

        if (is_string($url) && $url !== '') {
            $client = $this->webClientFromConfig($key);
        } elseif (is_string($command) && $command !== '') {
            $args = Configure::read("{$key}.args", []);
            $env = Configure::read("{$key}.env", []);

            $client = McpClient::local(
                $command,
                is_array($args) ? array_values(array_filter($args, is_string(...))) : [],
                is_array($env) ? array_filter($env, fn(mixed $value): bool => is_string($value) && $value !== '') : [],
            );
        } else {
            throw new ClientException("MCP client [{$name}] must define a [url] or [command].");
        }

        $timeout = Configure::read("{$key}.timeout");

        if (is_numeric($timeout)) {
            $client->withTimeout((float)$timeout);
        }

        $cache = Configure::read("{$key}.cache");

        if ($cache) {
            $cached = is_array($cache) ? array_intersect_key($cache, ['store' => true, 'for' => true]) : [];

            $client->withCache(
                isset($cached['store']) && is_string($cached['store']) ? $cached['store'] : null,
                isset($cached['for']) && is_string($cached['for']) ? $cached['for'] : null,
            );
        }

        $version = Configure::read("{$key}.protocol_version");

        if (is_string($version) && $version !== '') {
            $client->withProtocolVersion(ProtocolVersion::from($version));
        }

        $only = Configure::read("{$key}.tools.only");

        if (is_array($only)) {
            $client->onlyTools(array_values(array_filter($only, is_string(...))));
        }

        $except = Configure::read("{$key}.tools.except", []);

        if (is_array($except)) {
            $client->exceptTools(array_values(array_filter($except, is_string(...))));
        }

        return $client;
    }

    /**
     * Build a web client from application configuration.
     *
     * @param string $key Configuration key prefix
     * @return \Crustum\Mcp\WebClient
     */
    protected function webClientFromConfig(string $key): WebClient
    {
        $url = Configure::read("{$key}.url");
        $headers = Configure::read("{$key}.headers", []);
        $token = Configure::read("{$key}.token");
        $oauth = Configure::read("{$key}.oauth");

        $client = McpClient::web(is_string($url) && $url !== '' ? $url : '');

        if (is_array($headers)) {
            /** @var array<string, string> $filtered */
            $filtered = array_filter($headers, fn(mixed $value): bool => is_string($value) && $value !== '');

            $client->withHeaders($filtered);
        }

        if (is_string($token) && $token !== '') {
            $client->withToken($token);
        }

        if (is_array($oauth)) {
            $stringOrNull = fn(mixed $value): ?string => is_string($value) ? $value : null;

            $client->withOAuth(
                clientId: $stringOrNull($oauth['client_id'] ?? null),
                clientSecret: $stringOrNull($oauth['client_secret'] ?? null),
                scope: $stringOrNull($oauth['scope'] ?? null),
                redirectUri: $stringOrNull($oauth['redirect_uri'] ?? null),
            );
        }

        return $client;
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
