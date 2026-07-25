<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\OAuth;

use Cake\Core\Configure;
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Closure;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

/**
 * Registers OAuth connect and callback routes for MCP web clients.
 */
class OAuthRouteRegistrar
{
    /**
     * Registered OAuth callback handlers.
     *
     * @var array<string, \Closure|array{0: class-string, 1: string}>
     */
    protected static array $handlers = [];

    /**
     * Clear registered OAuth callback handlers.
     *
     * @return void
     */
    public static function clearHandlers(): void
    {
        static::$handlers = [];
    }

    /**
     * Register OAuth routes for a named MCP web client.
     *
     * @param \Cake\Routing\RouteBuilder $routes Route builder instance
     * @param string $client Registered MCP client name
     * @param \Closure|array{0: class-string, 1: string} $handler OAuth callback handler
     * @param array<int, string>|string $middleware Route middleware
     * @param string|null $connectUri Connect route path
     * @param string|null $callbackUri Callback route path
     * @return void
     */
    public function register(
        RouteBuilder $routes,
        string $client,
        Closure|array $handler,
        array|string $middleware = [],
        ?string $connectUri = null,
        ?string $callbackUri = null,
    ): void {
        static::$handlers[$client] = $handler;

        $connectPath = $connectUri ?? "mcp/{$client}/connect";
        $callbackPath = $callbackUri ?? "mcp/oauth/{$client}/callback";
        $middleware = is_array($middleware) ? $middleware : [$middleware];

        $registerRoutes = function (RouteBuilder $builder) use ($client, $connectPath, $callbackPath): void {
            $builder->connect(
                $connectPath,
                [
                    'plugin' => 'Mcp',
                    'controller' => 'OAuth',
                    'action' => 'connect',
                    'clientName' => $client,
                ],
                [
                    '_name' => "mcp.oauth.{$client}.connect",
                    '_method' => 'GET',
                ],
            );

            $builder->connect(
                $callbackPath,
                [
                    'plugin' => 'Mcp',
                    'controller' => 'OAuth',
                    'action' => 'callback',
                    'clientName' => $client,
                ],
                [
                    '_name' => "mcp.oauth.{$client}.callback",
                    '_method' => 'GET',
                ],
            );
        };

        if ($middleware === []) {
            $registerRoutes($routes);

            return;
        }

        $routes->scope('/', function (RouteBuilder $builder) use ($middleware, $registerRoutes): void {
            $builder->applyMiddleware(...$middleware);
            $registerRoutes($builder);
        });
    }

    /**
     * Invoke a registered OAuth callback handler.
     *
     * @param string $client Registered MCP client name
     * @param array{provider: string, client: string, token: \Crustum\Mcp\Client\OAuth\TokenSet, returnTo: string|null} $context Callback context
     * @return mixed
     */
    public static function invokeHandler(string $client, array $context): mixed
    {
        if (!isset(static::$handlers[$client])) {
            return null;
        }

        $handler = static::$handlers[$client];

        if (is_array($handler)) {
            [$className, $method] = $handler;
            $instance = new $className();
            $reflection = new ReflectionMethod($instance, $method);

            return $reflection->invokeArgs($instance, static::bindContextArguments($reflection->getParameters(), $context));
        }

        $reflection = new ReflectionFunction($handler);

        return $handler(...static::bindContextArguments($reflection->getParameters(), $context));
    }

    /**
     * Bind named callback parameters from the OAuth context.
     *
     * @param array<int, \ReflectionParameter> $parameters Handler parameters
     * @param array{provider: string, client: string, token: \Crustum\Mcp\Client\OAuth\TokenSet, returnTo: string|null} $context Callback context
     * @return array<int, mixed>
     */
    protected static function bindContextArguments(array $parameters, array $context): array
    {
        if ($parameters === []) {
            return [];
        }

        if (count($parameters) === 1 && static::acceptsContextArray($parameters[0])) {
            return [$context];
        }

        $arguments = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $context)) {
                $arguments[] = $context[$name];

                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();

                continue;
            }

            $arguments[] = null;
        }

        return $arguments;
    }

    /**
     * Determine whether a parameter expects the full OAuth context array.
     *
     * @param \ReflectionParameter $parameter Handler parameter
     * @return bool
     */
    protected static function acceptsContextArray(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        if ($type === null) {
            return $parameter->getName() === 'context';
        }

        if ($type instanceof ReflectionNamedType) {
            return $type->getName() === 'array';
        }

        return false;
    }

    /**
     * Resolve the OAuth callback URI for a named client.
     *
     * @param string $name Client name
     * @return string|null
     */
    public static function callbackUrl(string $name): ?string
    {
        $configured = Configure::read("Mcp.oauth.routes.{$name}.callback");

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        try {
            return Router::url(['_name' => "mcp.oauth.{$name}.callback"], true);
        } catch (Throwable) {
            return null;
        }
    }
}
