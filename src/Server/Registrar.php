<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server;

use Cake\Core\Configure;
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Cake\Utility\Text;
use Closure;
use Crustum\Mcp\Client\OAuth\OAuthRouteRegistrar;
use Crustum\Mcp\Server\Transport\StdioTransport;
use Crustum\Mcp\Support\OAuthDebugLog;
use Crustum\Tessera\Tessera;
use RuntimeException;
use Throwable;

/**
 * Registers MCP servers for local STDIO and HTTP transports.
 */
class Registrar
{
    /**
     * OAuth scope advertised for MCP client registration and metadata.
     */
    public const OAUTH_SCOPE = 'mcp:use';

    /**
     * Shared registrar instance.
     *
     * @var self|null
     */
    protected static ?self $instance = null;

    /**
     * @var array<string, callable(): mixed>
     */
    protected array $localServers = [];

    /**
     * @var array<string, \Crustum\Mcp\Server\WebServerRegistration>
     */
    protected array $httpServers = [];

    /**
     * URIs that already have HTTP routes connected.
     *
     * @var array<string, true>
     */
    protected array $connectedUris = [];

    /**
     * Whether OAuth well-known and DCR routes were connected.
     *
     * @var bool
     */
    protected bool $oauthRoutesConnected = false;

    /**
     * Get the shared registrar instance.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        return static::$instance ??= new self();
    }

    /**
     * Replace the shared registrar instance.
     *
     * @param self|null $instance Registrar instance
     * @return void
     */
    public static function setInstance(?self $instance): void
    {
        static::$instance = $instance;
    }

    /**
     * Register MCP servers declared in application configuration.
     *
     * @return void
     */
    public function ensureConfigured(): void
    {
        $this->configureFromConfigure();
    }

    /**
     * Load MCP servers from application configuration.
     *
     * @return void
     */
    public function configureFromConfigure(): void
    {
        $localServers = Configure::read('Mcp.local', []);

        if (is_array($localServers)) {
            foreach ($localServers as $handle => $serverClass) {
                if (!is_string($handle)) {
                    continue;
                }

                if (!is_string($serverClass)) {
                    continue;
                }

                $this->local($handle, $serverClass);
            }
        }

        $webServers = Configure::read('Mcp.Servers', []);

        if (!is_array($webServers)) {
            return;
        }

        foreach ($webServers as $config) {
            if (!is_array($config)) {
                continue;
            }

            $route = $config['route'] ?? null;
            $serverClass = $config['server'] ?? null;
            if (!is_string($route)) {
                continue;
            }

            if (!is_string($serverClass)) {
                continue;
            }

            $middleware = $config['middleware'] ?? [];

            if (!is_array($middleware)) {
                $middleware = [];
            }

            $this->registerWeb($route, $serverClass, $middleware);
        }
    }

    /**
     * Register a local STDIO MCP server.
     *
     * @param string $handle Server handle
     * @param class-string<\Crustum\Mcp\Server> $serverClass Server class name
     * @return void
     */
    public function local(string $handle, string $serverClass): void
    {
        $this->localServers[$handle] = fn(): mixed => static::startServer(
            $serverClass,
            fn(): StdioTransport => new StdioTransport(Text::uuid()),
        );
    }

    /**
     * Register an HTTP MCP server without connecting routes.
     *
     * @param string $route Route path
     * @param class-string<\Crustum\Mcp\Server> $serverClass Server class name
     * @param array<int, string> $middleware Additional route middleware aliases
     * @return \Crustum\Mcp\Server\WebServerRegistration
     * @throws \RuntimeException When `Mcp.require_web_auth_middleware` is true and `$middleware` is empty
     */
    public function registerWeb(string $route, string $serverClass, array $middleware = []): WebServerRegistration
    {
        $uri = ltrim($route, '/');
        $this->assertWebAuthMiddleware($uri, $middleware);
        $registration = new WebServerRegistration($uri, $serverClass, $middleware);
        $this->httpServers[$uri] = $registration;

        return $registration;
    }

    /**
     * Enforce or warn when a web MCP server is registered without auth middleware.
     *
     * @param string $uri Normalized route URI
     * @param array<int, string> $middleware Per-server middleware aliases
     * @return void
     * @throws \RuntimeException When `Mcp.require_web_auth_middleware` is true and `$middleware` is empty
     */
    protected function assertWebAuthMiddleware(string $uri, array $middleware): void
    {
        if ($middleware !== []) {
            return;
        }

        $message = sprintf(
            'MCP web server [%s] has no auth middleware. Set Mcp.Servers.*.middleware ' .
            '(for example tesseraOAuth) or disable Mcp.require_web_auth_middleware.',
            $uri,
        );

        if (Configure::read('Mcp.require_web_auth_middleware')) {
            throw new RuntimeException($message);
        }

        if (Configure::read('debug')) {
            OAuthDebugLog::warning($message);
        }
    }

    /**
     * Register an HTTP MCP server and connect its routes.
     *
     * @param \Cake\Routing\RouteBuilder $routes Route builder instance
     * @param string $route Route path
     * @param class-string<\Crustum\Mcp\Server> $serverClass Server class name
     * @param array<int, string> $middleware Additional route middleware aliases
     * @return \Crustum\Mcp\Server\WebServerRegistration
     */
    public function web(RouteBuilder $routes, string $route, string $serverClass, array $middleware = []): WebServerRegistration
    {
        $registration = $this->registerWeb($route, $serverClass, $middleware);
        $this->connectWebRoutes($routes, $registration);

        return $registration;
    }

    /**
     * Connect routes for all registered HTTP MCP servers.
     *
     * @param \Cake\Routing\RouteBuilder $routes Route builder instance
     * @return void
     */
    public function connectAllWebRoutes(RouteBuilder $routes): void
    {
        foreach ($this->httpServers as $registration) {
            $this->connectWebRoutes($routes, $registration);
        }
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
    public function oAuthRoutesFor(
        RouteBuilder $routes,
        string $client,
        Closure|array $handler,
        array|string $middleware = [],
        ?string $connectUri = null,
        ?string $callbackUri = null,
    ): void {
        (new OAuthRouteRegistrar())->register(
            $routes,
            $client,
            $handler,
            $middleware,
            $connectUri,
            $callbackUri,
        );
    }

    /**
     * Register OAuth well-known metadata and dynamic client registration routes.
     *
     * @param \Cake\Routing\RouteBuilder $routes Route builder instance
     * @param string $oauthPrefix Prefix for the DCR endpoint (default oauth)
     * @return void
     */
    public function oauthRoutes(RouteBuilder $routes, string $oauthPrefix = 'oauth'): void
    {
        if ($this->oauthRoutesConnected) {
            return;
        }

        static::ensureMcpScope();

        $plugin = ['plugin' => 'Crustum/Mcp'];
        $named = Router::getRouteCollection()->named();

        if (!array_key_exists('mcp.oauth.protected-resource', $named)) {
            $routes->connect(
                '/.well-known/oauth-protected-resource',
                $plugin + [
                    'controller' => 'OAuthMetadata',
                    'action' => 'protectedResource',
                ],
                ['_name' => 'mcp.oauth.protected-resource', '_method' => 'GET'],
            );
        }

        if (!array_key_exists('mcp.oauth.authorization-server', $named)) {
            $routes->connect(
                '/.well-known/oauth-authorization-server',
                $plugin + [
                    'controller' => 'OAuthMetadata',
                    'action' => 'authorizationServer',
                    'oauthPrefix' => $oauthPrefix,
                ],
                ['_name' => 'mcp.oauth.authorization-server', '_method' => 'GET'],
            );
        }

        $routes->connect(
            '/.well-known/oauth-protected-resource/{path}',
            $plugin + [
                'controller' => 'OAuthMetadata',
                'action' => 'protectedResource',
            ],
            [
                '_name' => 'mcp.oauth.protected-resource.nested',
                '_method' => 'GET',
                'pass' => ['path'],
                'path' => '.*',
            ],
        );

        $routes->connect(
            '/.well-known/oauth-authorization-server/{path}',
            $plugin + [
                'controller' => 'OAuthMetadata',
                'action' => 'authorizationServer',
                'oauthPrefix' => $oauthPrefix,
            ],
            [
                '_name' => 'mcp.oauth.authorization-server.nested',
                '_method' => 'GET',
                'pass' => ['path'],
                'path' => '.*',
            ],
        );

        $routes->connect(
            '/' . trim($oauthPrefix, '/') . '/register',
            $plugin + [
                'controller' => 'OAuthRegister',
                'action' => 'register',
            ],
            ['_name' => 'mcp.oauth.register', '_method' => 'POST'],
        );

        $this->oauthRoutesConnected = true;
    }

    /**
     * Build OAuth authorization server metadata.
     *
     * @param string $oauthPrefix DCR route prefix
     * @return array<string, array<int, string>|string>
     */
    public static function authorizationServerMetadata(string $oauthPrefix): array
    {
        $issuer = Configure::read('Mcp.authorization_server');
        if (!is_string($issuer) || $issuer === '') {
            $issuer = ServerUrl::current() !== '' ? ServerUrl::current() : Router::url('/', true);
        }

        return [
            'issuer' => $issuer,
            'authorization_endpoint' => static::authorizationEndpoint(),
            'token_endpoint' => static::tokenEndpoint(),
            'registration_endpoint' => ServerUrl::forPath(trim($oauthPrefix, '/') . '/register'),
            'response_types_supported' => ['code'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => [self::OAUTH_SCOPE],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
        ];
    }

    /**
     * Build OAuth protected resource metadata.
     *
     * @param string $path MCP resource path
     * @return array<string, array<int, string>|string>
     */
    public static function protectedResourceMetadata(string $path): array
    {
        $authorizationServer = Configure::read('Mcp.authorization_server');
        if (!is_string($authorizationServer) || $authorizationServer === '') {
            $authorizationServer = ServerUrl::current() !== '' ? ServerUrl::current() : Router::url('/', true);
        }

        return [
            'resource' => ServerUrl::forPath($path),
            'authorization_servers' => [$authorizationServer],
            'scopes_supported' => [self::OAUTH_SCOPE],
        ];
    }

    /**
     * Ensure the MCP OAuth scope is registered with Tessera.
     *
     * @return array<string, string>
     */
    public static function ensureMcpScope(): array
    {
        if (!class_exists(Tessera::class)) {
            return [];
        }

        $current = Tessera::$scopes;

        if (!array_key_exists(self::OAUTH_SCOPE, $current)) {
            $current[self::OAUTH_SCOPE] = 'Use MCP server';
            Tessera::tokensCan($current);
        }

        return $current;
    }

    /**
     * Merge mcp:use onto a client scopes list, or null when unrestricted.
     *
     * @param mixed $scopes Stored client scopes
     * @return list<string>|null
     */
    public static function scopesWithMcp(mixed $scopes): ?array
    {
        if (!is_array($scopes)) {
            return null;
        }

        if (in_array(self::OAUTH_SCOPE, $scopes, true)) {
            /** @var list<string> $scopes */
            return $scopes;
        }

        return [...$scopes, self::OAUTH_SCOPE];
    }

    /**
     * Connect HTTP routes for a registered web MCP server.
     *
     * @param \Cake\Routing\RouteBuilder $routes Route builder instance
     * @param \Crustum\Mcp\Server\WebServerRegistration $registration Registered web server
     * @return void
     */
    protected function connectWebRoutes(RouteBuilder $routes, WebServerRegistration $registration): void
    {
        if (isset($this->connectedUris[$registration->uri])) {
            return;
        }

        $uri = $registration->uri;
        $path = '/' . $uri;
        $routeName = 'mcp.server.' . str_replace('/', '.', $uri);
        $target = [
            'plugin' => 'Crustum/Mcp',
            'controller' => 'Server',
            'serverUri' => $uri,
        ];
        $routeOptions = [
            '_middleware' => $this->routeMiddleware($routes, $registration),
        ];

        $routes->connect(
            $path,
            $target + ['action' => 'methodNotAllowed', '_method' => 'GET'],
            $routeOptions + ['_name' => $routeName . '.get'],
        );
        $routes->connect(
            $path,
            $target + ['action' => 'methodNotAllowed', '_method' => 'DELETE'],
            $routeOptions + ['_name' => $routeName . '.delete'],
        );
        $routes->connect(
            $path,
            $target + ['action' => 'handle', '_method' => 'POST'],
            $routeOptions + ['_name' => $routeName . '.post'],
        );

        $this->connectedUris[$uri] = true;
    }

    /**
     * Build the middleware chain for a registered web server route.
     *
     * Per-server aliases (for example `tesseraOAuth`) are appended after the
     * shared MCP protocol middleware so they run before `ServerController`.
     *
     * @param \Cake\Routing\RouteBuilder $routes Route builder instance
     * @param \Crustum\Mcp\Server\WebServerRegistration $registration Registered web server
     * @return array<int, string>
     */
    protected function routeMiddleware(RouteBuilder $routes, WebServerRegistration $registration): array
    {
        $middleware = $routes->getMiddleware();

        if ($registration->middleware === []) {
            return $middleware;
        }

        return array_merge($middleware, $registration->middleware);
    }

    /**
     * Get a registered local server starter.
     *
     * @param string $handle Server handle
     * @return (callable(): mixed)|null
     */
    public function getLocalServer(string $handle): ?callable
    {
        return $this->localServers[$handle] ?? null;
    }

    /**
     * Get a registered web server registration.
     *
     * @param string $route Route path or URI
     * @return \Crustum\Mcp\Server\WebServerRegistration|null
     */
    public function getWebServer(string $route): ?WebServerRegistration
    {
        $uri = ltrim($route, '/');

        return $this->httpServers[$uri] ?? null;
    }

    /**
     * Get all registered servers keyed by handle or route.
     *
     * @return array<string, callable(): mixed|\Crustum\Mcp\Server\WebServerRegistration>
     */
    public function servers(): array
    {
        return array_merge(
            $this->localServers,
            $this->httpServers,
        );
    }

    /**
     * Resolve the authorization endpoint URL.
     *
     * @return string
     */
    protected static function authorizationEndpoint(): string
    {
        $override = Configure::read('Mcp.oauth.authorization_endpoint');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        try {
            return Router::url(['_name' => 'tessera:authorizations.authorize'], true);
        } catch (Throwable) {
            return ServerUrl::forPath('oauth/authorize');
        }
    }

    /**
     * Resolve the token endpoint URL.
     *
     * @return string
     */
    protected static function tokenEndpoint(): string
    {
        $override = Configure::read('Mcp.oauth.token_endpoint');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        try {
            return Router::url(['_name' => 'tessera:token'], true);
        } catch (Throwable) {
            return ServerUrl::forPath('oauth/token');
        }
    }

    /**
     * Start an MCP server with the given transport factory.
     *
     * @param class-string<\Crustum\Mcp\Server> $serverClass Server class name
     * @param callable(): \Crustum\Mcp\Server\Contracts\Transport $transportFactory Transport factory
     * @return mixed
     */
    protected static function startServer(string $serverClass, callable $transportFactory): mixed
    {
        $transport = $transportFactory();
        $server = new $serverClass($transport);
        $server->start();

        return $transport->run();
    }
}
