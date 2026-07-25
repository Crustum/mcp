<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Routing\Route\Route;
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Crustum\Mcp\Server\Registrar;

/**
 * Reset registrar and routing state between registrar tests.
 *
 * @return void
 */
function resetMcpRegistrarState(): void
{
    Router::resetRoutes();
    Registrar::setInstance(null);
    Configure::delete('Mcp.local');
    Configure::delete('Mcp.Servers');
    Configure::delete('Mcp.require_web_auth_middleware');
    Configure::delete('Mcp.authorization_server');
    Configure::delete('Mcp.oauth.authorization_endpoint');
    Configure::delete('Mcp.oauth.token_endpoint');
    Configure::write('App.fullBaseUrl', 'http://localhost');
    Configure::write('debug', false);

    if (class_exists(\Crustum\Tessera\Tessera::class)) {
        \Crustum\Tessera\Tessera::$scopes = [];
    }
}

/**
 * Build a route builder for registrar route tests.
 *
 * @param array<int, string> $middleware Middleware aliases applied to the builder
 * @return \Cake\Routing\RouteBuilder
 */
function mcpRegistrarRouteBuilder(array $middleware = ['bodyParser', 'reorderJsonAccept', 'wwwAuthenticate']): RouteBuilder
{
    $builder = Router::createRouteBuilder('/');

    foreach ($middleware as $name) {
        if (!Router::getRouteCollection()->middlewareExists($name)) {
            $builder->registerMiddleware(
                $name,
                static fn($request, $handler) => $handler->handle($request),
            );
        }
    }

    if ($middleware !== []) {
        $builder->applyMiddleware(...$middleware);
    }

    return $builder;
}

/**
 * Get a named route from the current route collection.
 *
 * @param string $name Named route identifier
 * @return \Cake\Routing\Route\Route|null
 */
function mcpNamedRoute(string $name): ?Route
{
    return Router::getRouteCollection()->named()[$name] ?? null;
}

/**
 * Invoke a protected registrar method for unit testing.
 *
 * @param object $registrar Registrar instance
 * @param string $method Method name
 * @param array<int, mixed> $arguments Method arguments
 * @return mixed
 */
function invokeRegistrarMethod(object $registrar, string $method, array $arguments = []): mixed
{
    $reflection = new ReflectionClass($registrar);
    $reflectionMethod = $reflection->getMethod($method);

    return $reflectionMethod->invoke($registrar, ...$arguments);
}
