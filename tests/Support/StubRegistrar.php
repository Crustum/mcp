<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Support;

use Crustum\Mcp\Server\Registrar;
use Crustum\Mcp\Server\WebServerRegistration;

/**
 * Configurable registrar double for command unit tests.
 */
final class StubRegistrar extends Registrar
{
    /**
     * @var array<string, callable(): mixed>
     */
    protected array $stubLocalServers = [];

    /**
     * @var array<string, \Crustum\Mcp\Server\WebServerRegistration>
     */
    protected array $stubWebServers = [];

    /**
     * @var array<string, callable(): mixed|\Crustum\Mcp\Server\WebServerRegistration>
     */
    protected array $stubServers = [];

    /**
     * @param array<string, callable(): mixed> $localServers Local server starters
     * @param array<string, \Crustum\Mcp\Server\WebServerRegistration> $webServers Web server registrations
     * @param array<string, callable(): mixed|\Crustum\Mcp\Server\WebServerRegistration> $servers Combined server map
     */
    public function __construct(
        array $localServers = [],
        array $webServers = [],
        array $servers = [],
    ) {
        $this->stubLocalServers = $localServers;
        $this->stubWebServers = $webServers;
        $this->stubServers = $servers;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function ensureConfigured(): void
    {
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getLocalServer(string $handle): ?callable
    {
        return $this->stubLocalServers[$handle] ?? null;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getWebServer(string $route): ?WebServerRegistration
    {
        $uri = ltrim($route, '/');

        return $this->stubWebServers[$uri] ?? null;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function servers(): array
    {
        if ($this->stubServers !== []) {
            return $this->stubServers;
        }

        return array_merge($this->stubLocalServers, $this->stubWebServers);
    }
}
