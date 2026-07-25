<?php
declare(strict_types=1);

namespace Crustum\Mcp\Facades;

use Cake\Routing\RouteBuilder;
use Closure;
use Crustum\Mcp\Client;
use Crustum\Mcp\Client\ClientManager;
use Crustum\Mcp\Server\Registrar;

/**
 * Test helper for named MCP client registration and OAuth route wiring.
 */
final class Mcp
{
    /**
     * Register a named MCP client factory.
     *
     * @param string $name Client name
     * @param Closure(): \Crustum\Mcp\Client $factory Client factory
     * @return void
     */
    public static function registerClient(string $name, Closure $factory): void
    {
        ClientManager::getInstance()->registerClient($name, $factory);
    }

    /**
     * Resolve a registered MCP client.
     *
     * @param string $name Client name
     * @return \Crustum\Mcp\Client
     */
    public static function client(string $name): Client
    {
        return ClientManager::getInstance()->client($name);
    }

    /**
     * Register OAuth connect and callback routes for a named MCP web client.
     *
     * @param \Cake\Routing\RouteBuilder $routes Route builder instance
     * @param string $client Registered MCP client name
     * @param \Closure|array{0: class-string, 1: string} $handler OAuth callback handler
     * @param array<int, string>|string $middleware Route middleware
     * @param string|null $connectUri Connect route path
     * @param string|null $callbackUri Callback route path
     * @return void
     */
    public static function oAuthRoutesFor(
        RouteBuilder $routes,
        string $client,
        Closure|array $handler,
        array|string $middleware = [],
        ?string $connectUri = null,
        ?string $callbackUri = null,
    ): void {
        Registrar::getInstance()->oAuthRoutesFor(
            $routes,
            $client,
            $handler,
            $middleware,
            $connectUri,
            $callbackUri,
        );
    }
}
