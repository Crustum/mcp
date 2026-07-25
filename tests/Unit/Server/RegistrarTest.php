<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Routing\Router;
use Crustum\Mcp\Server\Registrar;
use Crustum\Mcp\Server\WebServerRegistration;
use Crustum\Mcp\Test\Fixtures\ExampleServer;
use Crustum\Tessera\Tessera;

beforeEach(function (): void {
    resetMcpRegistrarState();
});

it('uses a shared registrar instance', function (): void {
    $first = Registrar::getInstance();
    $second = Registrar::getInstance();

    expect($first)->toBe($second);
});

it('replaces the shared registrar instance', function (): void {
    $original = Registrar::getInstance();
    $replacement = new Registrar();

    Registrar::setInstance($replacement);

    expect(Registrar::getInstance())->toBe($replacement);
    expect(Registrar::getInstance())->not->toBe($original);
});

it('registers a local server and retrieves it', function (): void {
    $registrar = new Registrar();

    $registrar->local('test-server', ExampleServer::class);

    $server = $registrar->getLocalServer('test-server');

    expect($server)->toBeCallable();
});

it('returns null for a non-existent local server', function (): void {
    $registrar = new Registrar();

    expect($registrar->getLocalServer('non-existent'))->toBeNull();
});

it('registers a web server and retrieves it', function (): void {
    $registrar = new Registrar();

    $registration = $registrar->registerWeb('/api/mcp', ExampleServer::class);

    expect($registration)->toBeInstanceOf(WebServerRegistration::class);
    expect($registrar->getWebServer('api/mcp'))->toBe($registration);
    expect($registrar->getWebServer('/api/mcp'))->toBe($registration);
    expect($registration->uri)->toBe('api/mcp');
    expect($registration->serverClass)->toBe(ExampleServer::class);
});

it('stores per-server middleware on web registrations', function (): void {
    $registrar = new Registrar();

    $registration = $registrar->registerWeb('/api/mcp', ExampleServer::class, ['authentication']);

    expect($registration->middleware)->toBe(['authentication']);
});

it('allows web servers without middleware when require_web_auth_middleware is false', function (): void {
    Configure::write('Mcp.require_web_auth_middleware', false);

    $registrar = new Registrar();
    $registration = $registrar->registerWeb('/api/mcp', ExampleServer::class);

    expect($registration->middleware)->toBe([]);
});

it('rejects web servers without middleware when require_web_auth_middleware is true', function (): void {
    Configure::write('Mcp.require_web_auth_middleware', true);

    $registrar = new Registrar();

    $registrar->registerWeb('/api/mcp', ExampleServer::class);
})->throws(RuntimeException::class, 'MCP web server [api/mcp] has no auth middleware');

it('registers web servers with middleware when require_web_auth_middleware is true', function (): void {
    Configure::write('Mcp.require_web_auth_middleware', true);

    $registrar = new Registrar();
    $registration = $registrar->registerWeb('/api/mcp', ExampleServer::class, ['tesseraOAuth']);

    expect($registration->middleware)->toBe(['tesseraOAuth']);
});

it('returns null for a non-existent web server', function (): void {
    $registrar = new Registrar();

    expect($registrar->getWebServer('non-existent'))->toBeNull();
});

it('returns all registered servers', function (): void {
    $registrar = new Registrar();

    $registrar->local('local-server', ExampleServer::class);
    $registrar->registerWeb('/web/mcp', ExampleServer::class);

    $servers = $registrar->servers();

    expect($servers)->toHaveCount(2);
    expect($servers)->toHaveKey('local-server');
    expect($servers)->toHaveKey('web/mcp');
    expect($servers['local-server'])->toBeCallable();
    expect($servers['web/mcp'])->toBeInstanceOf(WebServerRegistration::class);
});

it('loads local servers from application configuration', function (): void {
    Configure::write('Mcp.local', [
        'demo' => ExampleServer::class,
        123 => ExampleServer::class,
        'ignored' => 456,
    ]);

    $registrar = new Registrar();
    $registrar->ensureConfigured();

    expect($registrar->getLocalServer('demo'))->toBeCallable();
    expect($registrar->getLocalServer('123'))->toBeNull();
    expect($registrar->getLocalServer('ignored'))->toBeNull();
});

it('loads web servers from application configuration', function (): void {
    Configure::write('Mcp.Servers', [
        'qa' => [
            'route' => '/mcp/qa',
            'server' => ExampleServer::class,
            'middleware' => ['authentication'],
        ],
        'broken' => [
            'route' => 123,
            'server' => ExampleServer::class,
        ],
    ]);

    $registrar = new Registrar();
    $registrar->ensureConfigured();

    $registration = $registrar->getWebServer('mcp/qa');

    expect($registration)->toBeInstanceOf(WebServerRegistration::class);
    expect($registration?->middleware)->toBe(['authentication']);
    expect($registrar->getWebServer('broken'))->toBeNull();
});

it('connects MCP HTTP routes for a registered web server', function (): void {
    $registrar = new Registrar();
    $routes = mcpRegistrarRouteBuilder();

    $registrar->web($routes, '/api/mcp', ExampleServer::class);

    $postRoute = mcpNamedRoute('mcp.server.api.mcp.post');
    $getRoute = mcpNamedRoute('mcp.server.api.mcp.get');
    $deleteRoute = mcpNamedRoute('mcp.server.api.mcp.delete');

    expect($postRoute)->not->toBeNull();
    expect($getRoute)->not->toBeNull();
    expect($deleteRoute)->not->toBeNull();

    expect($postRoute?->defaults)->toMatchArray([
        'plugin' => 'Crustum/Mcp',
        'controller' => 'Server',
        'action' => 'handle',
        'serverUri' => 'api/mcp',
        '_method' => 'POST',
    ]);
    expect($getRoute?->defaults)->toMatchArray([
        'plugin' => 'Crustum/Mcp',
        'controller' => 'Server',
        'action' => 'methodNotAllowed',
        'serverUri' => 'api/mcp',
        '_method' => 'GET',
    ]);
    expect($deleteRoute?->defaults)->toMatchArray([
        'plugin' => 'Crustum/Mcp',
        'controller' => 'Server',
        'action' => 'methodNotAllowed',
        'serverUri' => 'api/mcp',
        '_method' => 'DELETE',
    ]);
});

it('connects routes for every registered web server', function (): void {
    $registrar = new Registrar();
    $routes = mcpRegistrarRouteBuilder();

    $registrar->registerWeb('/mcp/one', ExampleServer::class);
    $registrar->registerWeb('/mcp/two', ExampleServer::class);
    $registrar->connectAllWebRoutes($routes);

    expect(mcpNamedRoute('mcp.server.mcp.one.post'))->not->toBeNull();
    expect(mcpNamedRoute('mcp.server.mcp.two.post'))->not->toBeNull();
});

it('does not connect duplicate routes for the same web server', function (): void {
    $registrar = new Registrar();
    $routes = mcpRegistrarRouteBuilder();

    $registrar->web($routes, '/api/mcp', ExampleServer::class);
    $registrar->web($routes, '/api/mcp', ExampleServer::class);

    $namedRoutes = Router::getRouteCollection()->named();
    $matchingRoutes = array_filter(
        $namedRoutes,
        static fn(string $name): bool => str_starts_with($name, 'mcp.server.api.mcp.'),
        ARRAY_FILTER_USE_KEY,
    );

    expect($matchingRoutes)->toHaveCount(3);
});

it('keeps route middleware unchanged when no per-server middleware is configured', function (): void {
    $registrar = new Registrar();
    $routes = mcpRegistrarRouteBuilder(['bodyParser', 'wwwAuthenticate', 'csrf']);
    $registration = new WebServerRegistration('api/mcp', ExampleServer::class);

    $middleware = invokeRegistrarMethod($registrar, 'routeMiddleware', [$routes, $registration]);

    expect($middleware)->toBe(['bodyParser', 'wwwAuthenticate', 'csrf']);
});

it('appends per-server middleware after the shared stack', function (): void {
    $registrar = new Registrar();
    $routes = mcpRegistrarRouteBuilder(['bodyParser', 'wwwAuthenticate', 'csrf']);
    $registration = new WebServerRegistration('api/mcp', ExampleServer::class, ['authentication']);

    $middleware = invokeRegistrarMethod($registrar, 'routeMiddleware', [$routes, $registration]);

    expect($middleware)->toBe(['bodyParser', 'wwwAuthenticate', 'csrf', 'authentication']);
});

it('applies per-server middleware on connected routes', function (): void {
    $registrar = new Registrar();
    $routes = mcpRegistrarRouteBuilder(['bodyParser', 'wwwAuthenticate']);

    $registrar->web($routes, '/api/mcp', ExampleServer::class, ['authentication']);

    $postRoute = mcpNamedRoute('mcp.server.api.mcp.post');

    expect($postRoute?->getMiddleware())->toBe(['bodyParser', 'wwwAuthenticate', 'authentication']);
});

it('registers the mcp scope with tessera', function (): void {
    Tessera::$scopes = [];

    $scopes = Registrar::ensureMcpScope();

    expect($scopes)->toHaveKey(Registrar::OAUTH_SCOPE);
    expect($scopes[Registrar::OAUTH_SCOPE])->toBe('Use MCP server');
    expect(Tessera::$scopes)->toHaveKey(Registrar::OAUTH_SCOPE);
});

it('does not overwrite an existing mcp scope description', function (): void {
    Tessera::$scopes = [Registrar::OAUTH_SCOPE => 'Existing MCP scope'];

    Registrar::ensureMcpScope();

    expect(Tessera::$scopes[Registrar::OAUTH_SCOPE])->toBe('Existing MCP scope');
});

it('merges mcp scope onto restricted client scopes', function (): void {
    expect(Registrar::scopesWithMcp([]))->toBe([Registrar::OAUTH_SCOPE]);
    expect(Registrar::scopesWithMcp(['custom:scope']))->toBe(['custom:scope', Registrar::OAUTH_SCOPE]);
    expect(Registrar::scopesWithMcp([Registrar::OAUTH_SCOPE]))->toBe([Registrar::OAUTH_SCOPE]);
    expect(Registrar::scopesWithMcp(null))->toBeNull();
});

it('registers oauth well-known and dcr routes', function (): void {
    $registrar = new Registrar();
    $routes = mcpRegistrarRouteBuilder([]);

    $registrar->oauthRoutes($routes);

    expect(mcpNamedRoute('mcp.oauth.protected-resource'))->not->toBeNull();
    expect(mcpNamedRoute('mcp.oauth.authorization-server'))->not->toBeNull();
    expect(mcpNamedRoute('mcp.oauth.protected-resource.nested'))->not->toBeNull();
    expect(mcpNamedRoute('mcp.oauth.authorization-server.nested'))->not->toBeNull();
    expect(mcpNamedRoute('mcp.oauth.register'))->not->toBeNull();
    expect(mcpNamedRoute('mcp.oauth.register')?->defaults)->toMatchArray([
        'plugin' => 'Crustum/Mcp',
        'controller' => 'OAuthRegister',
        'action' => 'register',
    ]);
});

it('registers dcr under a custom oauth prefix', function (): void {
    $registrar = new Registrar();
    $routes = mcpRegistrarRouteBuilder([]);

    $registrar->oauthRoutes($routes, 'custom-oauth');

    expect(mcpNamedRoute('mcp.oauth.register')?->template)->toBe('/custom-oauth/register');
});

it('builds authorization server metadata with mcp scope', function (): void {
    Configure::write('Mcp.authorization_server', 'https://auth.example.com');
    Configure::write('Mcp.oauth.authorization_endpoint', 'https://auth.example.com/oauth/authorize');
    Configure::write('Mcp.oauth.token_endpoint', 'https://auth.example.com/oauth/token');
    Configure::write('App.fullBaseUrl', 'https://mcp.example.com');

    $metadata = Registrar::authorizationServerMetadata('oauth');

    expect($metadata)->toMatchArray([
        'issuer' => 'https://auth.example.com',
        'authorization_endpoint' => 'https://auth.example.com/oauth/authorize',
        'token_endpoint' => 'https://auth.example.com/oauth/token',
        'registration_endpoint' => 'https://mcp.example.com/oauth/register',
        'response_types_supported' => ['code'],
        'code_challenge_methods_supported' => ['S256'],
        'scopes_supported' => [Registrar::OAUTH_SCOPE],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
    ]);
});

it('builds protected resource metadata for a nested path', function (): void {
    Configure::write('Mcp.authorization_server', 'https://auth.example.com');
    Configure::write('App.fullBaseUrl', 'https://mcp.example.com');

    $metadata = Registrar::protectedResourceMetadata('mcp/server');

    expect($metadata)->toMatchArray([
        'resource' => 'https://mcp.example.com/mcp/server',
        'authorization_servers' => ['https://auth.example.com'],
        'scopes_supported' => [Registrar::OAUTH_SCOPE],
    ]);
});

it('does not reconnect oauth routes twice', function (): void {
    $registrar = new Registrar();
    $routes = mcpRegistrarRouteBuilder([]);

    $registrar->oauthRoutes($routes);
    $registrar->oauthRoutes($routes);

    $namedRoutes = Router::getRouteCollection()->named();
    $oauthRoutes = array_filter(
        $namedRoutes,
        static fn(string $name): bool => str_starts_with($name, 'mcp.oauth.'),
        ARRAY_FILTER_USE_KEY,
    );

    expect($oauthRoutes)->toHaveCount(5);
});
