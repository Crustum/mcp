<?php
declare(strict_types=1);

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Mcp\Client;
use Crustum\Mcp\Client\OAuth\TokenSet;
use Crustum\Mcp\Controller\OAuthController;
use Crustum\Mcp\Facades\Mcp;
use Crustum\Mcp\Test\Fixtures\Client\OAuthCallbackController;
use Crustum\Mcp\WebClient;

/**
 * @return array<string, array<string, mixed>>
 */
function fakeOAuthSession(): array
{
    return [
        'mcp.oauth.' . sha1('https://mcp.test/mcp') => [
            'state' => 'the-state',
            'verifier' => 'the-verifier',
            'client_id' => 'client-123',
            'client_secret' => null,
            'token_endpoint' => 'https://auth.test/token',
            'redirect_uri' => 'https://app.test/callback',
            'return_to' => null,
        ],
    ];
}

function registerGithubClient(): void
{
    Mcp::registerClient('github', fn(): WebClient => Client::web('https://mcp.test/mcp')->withOAuth(
        clientId: 'client-123',
        redirectUri: 'https://app.test/callback',
    ));
}

/**
 * @param array<string, array<string, mixed>> $sessionData
 * @param array<string, mixed> $query
 */
function oauthControllerRequest(array $sessionData = [], array $query = []): ServerRequest
{
    $session = mcpSession();

    foreach (array_keys($session->read() ?? []) as $key) {
        if (is_string($key)) {
            $session->delete($key);
        }
    }

    foreach ($sessionData as $key => $value) {
        $session->write($key, $value);
    }

    return new ServerRequest([
        'environment' => [
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'app.test',
        ],
        'url' => '/',
        'query' => $query,
        'session' => $session,
    ]);
}

/**
 * @param array<string, array<string, mixed>> $sessionData
 * @param array<string, mixed> $query
 */
function invokeOAuthConnect(string $clientName, array $sessionData = [], array $query = []): Response
{
    $controller = new OAuthController(oauthControllerRequest($sessionData, $query));
    $response = $controller->connect($clientName);

    expect($response)->toBeInstanceOf(Response::class);

    return $response;
}

/**
 * @param array<string, array<string, mixed>> $sessionData
 * @param array<string, mixed> $query
 */
function invokeOAuthCallback(string $clientName, array $sessionData = [], array $query = []): mixed
{
    $controller = new OAuthController(oauthControllerRequest($sessionData, $query));

    return $controller->callback($clientName);
}

it('exchanges the code and invokes the handler with the client name', function (): void {
    Http::fake([
        'https://auth.test/token' => Http::response([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
        ]),
    ]);

    registerGithubClient();

    $capturedProvider = null;
    $capturedToken = null;
    $routes = mcpRegistrarRouteBuilder([]);

    Mcp::oAuthRoutesFor($routes, 'github', function (string $provider, TokenSet $token) use (&$capturedProvider, &$capturedToken): Response {
        $capturedProvider = $provider;
        $capturedToken = $token;

        return (new Response())->withStatus(302)->withLocation('/dashboard');
    });

    $response = invokeOAuthCallback('github', fakeOAuthSession(), [
        'code' => 'auth-code',
        'state' => 'the-state',
    ]);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getHeaderLine('Location'))->toBe('/dashboard')
        ->and($capturedProvider)->toBe('github')
        ->and($capturedToken)->toBeInstanceOf(TokenSet::class)
        ->and($capturedToken->accessToken)->toBe('access-token')
        ->and($capturedToken->refreshToken)->toBe('refresh-token');

    Http::assertSent(fn($request): bool => $request->url() === 'https://auth.test/token'
        && ($request['grant_type'] ?? null) === 'authorization_code'
        && ($request['code'] ?? null) === 'auth-code');
});

it('registers a connect route that redirects to the authorization server', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'resource' => 'https://mcp.test/mcp',
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    registerGithubClient();
    Mcp::oAuthRoutesFor(
        mcpRegistrarRouteBuilder([]),
        'github',
        fn(string $provider, TokenSet $token): Response => (new Response())->withStatus(302)->withLocation('/dashboard'),
    );

    $response = invokeOAuthConnect('github');

    expect($response->getHeaderLine('Location'))->toContain('https://auth.test/authorize');
});

it('supports controller array syntax for the handler', function (): void {
    Http::fake([
        'https://auth.test/token' => Http::response(['access_token' => 'access-token']),
    ]);

    registerGithubClient();
    Mcp::oAuthRoutesFor(mcpRegistrarRouteBuilder([]), 'github', [OAuthCallbackController::class, 'callback']);

    $response = invokeOAuthCallback('github', fakeOAuthSession(), [
        'code' => 'auth-code',
        'state' => 'the-state',
    ]);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getHeaderLine('Location'))->toBe('/connected/github');
});

it('registers named connect and callback routes', function (): void {
    Mcp::oAuthRoutesFor(
        mcpRegistrarRouteBuilder([]),
        'github',
        fn(string $provider, TokenSet $token): null => null,
    );

    expect(mcpNamedRoute('mcp.oauth.github.connect'))->not->toBeNull()
        ->and(mcpNamedRoute('mcp.oauth.github.callback'))->not->toBeNull();
});

it('allows the middleware to be applied on both routes', function (): void {
    $routes = mcpRegistrarRouteBuilder([]);
    $routes->registerMiddleware('auth', static fn($request, $handler) => $handler->handle($request));

    Mcp::oAuthRoutesFor(
        $routes,
        'github',
        fn(string $provider, TokenSet $token): null => null,
        middleware: ['auth'],
    );

    expect(mcpNamedRoute('mcp.oauth.github.connect')?->getMiddleware())->toContain('auth')
        ->and(mcpNamedRoute('mcp.oauth.github.callback')?->getMiddleware())->toContain('auth');
});

it('falls back to redirecting home when the handler returns nothing', function (): void {
    Http::fake([
        'https://auth.test/token' => Http::response(['access_token' => 'access-token']),
    ]);

    registerGithubClient();
    Mcp::oAuthRoutesFor(mcpRegistrarRouteBuilder([]), 'github', function (string $provider, TokenSet $token): void {
    });

    $response = invokeOAuthCallback('github', fakeOAuthSession(), [
        'code' => 'auth-code',
        'state' => 'the-state',
    ]);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getHeaderLine('Location'))->toEndWith('/');
});

it('passes the stored return destination to the handler and fallback redirect', function (): void {
    Http::fake([
        'https://auth.test/token' => Http::response(['access_token' => 'access-token']),
    ]);

    registerGithubClient();

    $capturedReturnTo = null;

    Mcp::oAuthRoutesFor(
        mcpRegistrarRouteBuilder([]),
        'github',
        function (string $provider, TokenSet $token, ?string $returnTo) use (&$capturedReturnTo): void {
            $capturedReturnTo = $returnTo;
        },
    );

    $session = fakeOAuthSession();
    $session['mcp.oauth.' . sha1('https://mcp.test/mcp')]['return_to'] = '/connected';

    $response = invokeOAuthCallback('github', $session, [
        'code' => 'auth-code',
        'state' => 'the-state',
    ]);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getHeaderLine('Location'))->toEndWith('/connected')
        ->and($capturedReturnTo)->toBe('/connected');
});

it('forwards challenge metadata and scope from the connect route into discovery', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/custom-resource' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    Mcp::registerClient('github', fn(): WebClient => Client::web('https://mcp.test/mcp')->withOAuth(
        clientId: 'client-123',
        redirectUri: 'https://app.test/callback',
    ));

    Mcp::oAuthRoutesFor(
        mcpRegistrarRouteBuilder([]),
        'github',
        fn(string $provider, TokenSet $token): null => null,
    );

    $response = invokeOAuthConnect('github', [], [
        'resource_metadata' => 'https://mcp.test/.well-known/custom-resource',
        'scope' => 'files:read',
    ]);

    $location = $response->getHeaderLine('Location');

    expect($location)->toContain('https://auth.test/authorize')
        ->and($location)->toContain('scope=files%3Aread');

    Http::assertSent(fn($request): bool => $request->url() === 'https://mcp.test/.well-known/custom-resource');
});
