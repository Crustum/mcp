<?php
declare(strict_types=1);

use Cake\Http\Response;
use Crustum\Mcp\Client;
use Crustum\Mcp\Client\Exception\OAuthException;
use Crustum\Mcp\Client\OAuth\OAuthClient;
use Crustum\Mcp\Client\OAuth\TokenSet;

function fakeDiscoveryMap(): array
{
    return [
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'resource' => 'https://mcp.test/mcp',
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
            'registration_endpoint' => 'https://auth.test/register',
            'code_challenge_methods_supported' => ['S256'],
        ]),
    ];
}

function fakeDiscovery(): void
{
    Http::fake(fakeDiscoveryMap());
}

it('builds an authorization redirect with PKCE and stashes session state', function (): void {
    fakeDiscovery();

    $response = Client::web('https://mcp.test/mcp')
        ->withOAuth(
            clientId: 'client-123',
            scope: 'mcp:use',
            redirectUri: 'https://app.test/callback',
        )
        ->oAuthClient(session: mcpSession())
        ->redirect('/dashboard');

    $target = responseLocation($response);
    parse_str((string)parse_url($target, PHP_URL_QUERY), $query);

    expect($target)->toStartWith('https://auth.test/authorize?')
        ->and($query['response_type'])->toBe('code')
        ->and($query['client_id'])->toBe('client-123')
        ->and($query['redirect_uri'])->toBe('https://app.test/callback')
        ->and($query['code_challenge_method'])->toBe('S256')
        ->and($query['scope'])->toBe('mcp:use')
        ->and($query['resource'])->toBe('https://mcp.test/mcp')
        ->and($query)->toHaveKey('code_challenge')
        ->and($query)->toHaveKey('state');

    $stored = Session::get('mcp.oauth.' . sha1('https://mcp.test/mcp'));

    expect($stored['state'])->toBe($query['state'])
        ->and($stored['client_id'])->toBe('client-123')
        ->and($stored['return_to'])->toBe('/dashboard')
        ->and($stored['verifier'])->toBeString();
});

it('merges query params onto an authorization endpoint that already has a query string', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize?audience=api',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    $target = responseLocation(Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient(session: mcpSession())
        ->redirect());

    expect(substr_count($target, '?'))->toBe(1);

    parse_str((string)parse_url($target, PHP_URL_QUERY), $query);

    expect($query['audience'])->toBe('api')
        ->and($query['response_type'])->toBe('code')
        ->and($query['client_id'])->toBe('client-123');
});

it('dynamically registers a client when no client id is configured', function (): void {
    Http::fake(array_merge(fakeDiscoveryMap(), [
        'https://auth.test/register' => Http::response(['client_id' => 'dcr-999', 'client_secret' => 'shh']),
    ]));

    Client::web('https://mcp.test/mcp')
        ->withOAuth(redirectUri: 'https://app.test/callback')
        ->oAuthClient(session: mcpSession())
        ->redirect();

    $stored = Session::get('mcp.oauth.' . sha1('https://mcp.test/mcp'));

    expect($stored['client_id'])->toBe('dcr-999')
        ->and($stored['client_secret'])->toBe('shh');

    Http::assertSent(fn($request): bool => $request->url() === 'https://auth.test/register'
        && ($request['redirect_uris'] ?? null) === ['https://app.test/callback']);
});

it('exchanges an authorization code for a token set', function (): void {
    Http::fake(array_merge(fakeDiscoveryMap(), [
        'https://auth.test/token' => Http::response([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'mcp:use',
        ]),
    ]));

    $key = 'mcp.oauth.' . sha1('https://mcp.test/mcp');

    Session::put($key, [
        'state' => 'the-state',
        'verifier' => 'the-verifier',
        'client_id' => 'client-123',
        'client_secret' => null,
        'token_endpoint' => 'https://auth.test/token',
        'redirect_uri' => 'https://app.test/callback',
        'return_to' => null,
    ]);

    $session = mcpSession();
    $query = [
        'code' => 'auth-code',
        'state' => 'the-state',
    ];

    $token = Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123')
        ->oAuthClient(session: $session)
        ->exchangeCallback($query, $session);

    expect($token)->toBeInstanceOf(TokenSet::class)
        ->and($token->accessToken)->toBe('access-token')
        ->and($token->refreshToken)->toBe('refresh-token')
        ->and($token->expiresAt)->toBeGreaterThan(time());

    expect(Session::has($key))->toBeFalse();

    Http::assertSent(fn($request): bool => $request->url() === 'https://auth.test/token'
        && ($request['grant_type'] ?? null) === 'authorization_code'
        && ($request['code'] ?? null) === 'auth-code'
        && ($request['code_verifier'] ?? null) === 'the-verifier'
        && ($request['resource'] ?? null) === 'https://mcp.test/mcp');
});

it('rejects a mismatched state parameter', function (): void {
    fakeDiscovery();

    $key = 'mcp.oauth.' . sha1('https://mcp.test/mcp');

    Session::put($key, [
        'state' => 'expected-state',
        'verifier' => 'verifier',
        'client_id' => 'client-123',
        'client_secret' => null,
        'token_endpoint' => 'https://auth.test/token',
        'redirect_uri' => 'https://app.test/callback',
        'return_to' => null,
    ]);

    $session = mcpSession();
    $query = [
        'code' => 'auth-code',
        'state' => 'tampered-state',
    ];

    expect(fn(): TokenSet => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123')
        ->oAuthClient(session: $session)
        ->exchangeCallback($query, $session))
        ->toThrow(OAuthException::class, 'state parameter did not match');
});

it('runs the client credentials grant', function (): void {
    Http::fake(array_merge(fakeDiscoveryMap(), [
        'https://auth.test/token' => Http::response([
            'access_token' => 'machine-token',
            'expires_in' => 7200,
        ]),
    ]));

    $token = Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'svc', clientSecret: 'secret', scope: 'mcp:use')
        ->oAuthClient(session: mcpSession())
        ->clientCredentials();

    expect($token->accessToken)->toBe('machine-token');

    Http::assertSent(fn($request): bool => $request->url() === 'https://auth.test/token'
        && ($request['grant_type'] ?? null) === 'client_credentials'
        && ($request['client_id'] ?? null) === 'svc'
        && ($request['client_secret'] ?? null) === 'secret');
});

it('throws when the authorization server redirects back with an error', function (): void {
    $session = mcpSession();
    $query = [
        'error' => 'access_denied',
        'error_description' => 'The user denied the request',
    ];

    Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123')
        ->oAuthClient(session: $session)
        ->exchangeCallback($query, $session);
})->throws(OAuthException::class, 'The authorization server returned an error [access_denied]: The user denied the request');

it('requires an authorization code when exchanging a callback', function (): void {
    $session = mcpSession();

    expect(fn(): TokenSet => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'svc', clientSecret: 'secret', scope: 'mcp:use')
        ->oAuthClient(session: $session)
        ->exchangeCallback([], $session))
        ->toThrow(OAuthException::class, 'did not include an authorization code');
});

it('refreshes a token using the refresh grant', function (): void {
    Http::fake(array_merge(fakeDiscoveryMap(), [
        'https://auth.test/token' => Http::response([
            'access_token' => 'fresh-token',
            'refresh_token' => 'new-refresh',
            'expires_in' => 3600,
        ]),
    ]));

    $token = Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', scope: 'mcp:use')
        ->oAuthClient(session: mcpSession())
        ->refreshCredentials('old-refresh');

    expect($token->accessToken)->toBe('fresh-token')
        ->and($token->refreshToken)->toBe('new-refresh');

    Http::assertSent(fn($request): bool => ($request['grant_type'] ?? null) === 'refresh_token'
        && ($request['refresh_token'] ?? null) === 'old-refresh'
        && ($request['client_id'] ?? null) === 'client-123');
});

it('falls back to the resource origin when protected resource metadata is unavailable', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response('', 404),
        'https://mcp.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://mcp.test',
            'authorization_endpoint' => 'https://mcp.test/authorize',
            'token_endpoint' => 'https://mcp.test/token',
        ]),
    ]);

    $response = Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient(session: mcpSession())
        ->redirect();

    expect(responseLocation($response))->toStartWith('https://mcp.test/authorize?');
});

it('throws when the token request fails', function (): void {
    Http::fake(array_merge(fakeDiscoveryMap(), [
        'https://auth.test/token' => Http::response(['error' => 'invalid_grant'], 400),
    ]));

    expect(fn(): TokenSet => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', scope: 'mcp:use')
        ->oAuthClient(session: mcpSession())
        ->refreshCredentials('bad-token'))
        ->toThrow(OAuthException::class, 'failed with status [400]');
});

it('throws when the authorization server metadata cannot be discovered', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response('', 500),
        'https://auth.test/.well-known/openid-configuration' => Http::response('', 500),
    ]);

    expect(fn(): TokenSet => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', clientSecret: 'secret')
        ->oAuthClient(session: mcpSession())
        ->clientCredentials())
        ->toThrow(OAuthException::class, 'Unable to discover authorization server metadata');
});

it('throws when dynamic registration is needed but unsupported', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    expect(fn(): Response => Client::web('https://mcp.test/mcp')
        ->withOAuth(redirectUri: 'https://app.test/callback')
        ->oAuthClient(session: mcpSession())
        ->redirect())
        ->toThrow(OAuthException::class, 'does not support dynamic client registration');
});

it('throws when oauth is used without configuration', function (): void {
    expect(fn(): OAuthClient => Client::web('https://mcp.test/mcp')->oAuthClient(session: mcpSession()))
        ->toThrow(OAuthException::class, 'No OAuth configuration');
});

it('requires a redirect uri before redirecting', function (): void {
    fakeDiscovery();

    expect(fn(): Response => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123')
        ->oAuthClient(session: mcpSession())
        ->redirect())
        ->toThrow(OAuthException::class, 'redirect URI is required');
});

it('uses the server advertised resource metadata url when provided', function (): void {
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

    $target = responseLocation(Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient('https://mcp.test/.well-known/custom-resource', session: mcpSession())
        ->redirect());

    expect($target)->toStartWith('https://auth.test/authorize?');

    Http::assertSent(fn($request): bool => $request->url() === 'https://mcp.test/.well-known/custom-resource');
});

it('throws instead of falling back to the origin when an explicit resource metadata url fails', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/custom-resource' => Http::response('', 404),
        'https://mcp.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://mcp.test',
            'authorization_endpoint' => 'https://mcp.test/authorize',
            'token_endpoint' => 'https://mcp.test/token',
        ]),
    ]);

    expect(fn(): Response => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient('https://mcp.test/.well-known/custom-resource', session: mcpSession())
        ->redirect())
        ->toThrow(OAuthException::class, 'Protected resource metadata request to [https://mcp.test/.well-known/custom-resource] failed with status [404]');

    Http::assertNotSent(fn($request): bool => $request->url() === 'https://mcp.test/.well-known/oauth-authorization-server');
});

it('throws when an explicit resource metadata url returns invalid json', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/custom-resource' => Http::response('not json', 200, ['Content-Type' => 'text/plain']),
    ]);

    expect(fn(): Response => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient('https://mcp.test/.well-known/custom-resource', session: mcpSession())
        ->redirect())
        ->toThrow(OAuthException::class, 'did not return a valid JSON object');
});

it('rejects protected resource metadata urls on internal hosts', function (): void {
    expect(fn(): Response => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient('https://127.0.0.1/.well-known/oauth-protected-resource', session: mcpSession())
        ->redirect())
        ->toThrow(OAuthException::class, 'private or internal host');
});

it('rejects protected resource metadata urls served over plain http', function (): void {
    expect(fn(): Response => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient('http://mcp.test/.well-known/oauth-protected-resource', session: mcpSession())
        ->redirect())
        ->toThrow(OAuthException::class, 'must be served over HTTPS');
});

it('rejects an authorization server whose issuer does not match', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://evil.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    expect(fn(): Response => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient(session: mcpSession())
        ->redirect())
        ->toThrow(OAuthException::class, 'did not match the expected issuer');
});

it('rejects protected resource metadata whose resource does not match', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'resource' => 'https://other.test/mcp',
            'authorization_servers' => ['https://auth.test'],
        ]),
    ]);

    expect(fn(): Response => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient(session: mcpSession())
        ->redirect())
        ->toThrow(OAuthException::class, 'did not match the expected resource');
});

it('rejects authorization server metadata that omits the issuer', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    expect(fn(): Response => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient(session: mcpSession())
        ->redirect())
        ->toThrow(OAuthException::class, 'did not match the expected issuer');
});

it('rejects an authorization server that does not advertise the S256 PKCE method', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
            'code_challenge_methods_supported' => ['plain'],
        ]),
    ]);

    expect(fn(): Response => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient(session: mcpSession())
        ->redirect())
        ->toThrow(OAuthException::class, 'does not support the required S256');
});

it('rejects an authorization server served over plain http', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['http://auth.test'],
        ]),
    ]);

    expect(fn(): Response => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient(session: mcpSession())
        ->redirect())
        ->toThrow(OAuthException::class, 'must be served over HTTPS');
});

it('falls back to openid connect discovery when oauth metadata is absent', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response('', 404),
        'https://auth.test/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    $target = responseLocation(Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient(session: mcpSession())
        ->redirect());

    expect($target)->toStartWith('https://auth.test/authorize?');
});

it('validates a matching iss parameter on the authorization callback', function (): void {
    Http::fake(array_merge(fakeDiscoveryMap(), [
        'https://auth.test/token' => Http::response(['access_token' => 'access-token']),
    ]));

    $key = 'mcp.oauth.' . sha1('https://mcp.test/mcp');

    Session::put($key, [
        'state' => 'the-state',
        'verifier' => 'the-verifier',
        'client_id' => 'client-123',
        'client_secret' => null,
        'token_endpoint' => 'https://auth.test/token',
        'redirect_uri' => 'https://app.test/callback',
        'return_to' => null,
        'issuer' => 'https://auth.test',
        'iss_supported' => true,
    ]);

    $session = mcpSession();
    $query = [
        'code' => 'auth-code',
        'state' => 'the-state',
        'iss' => 'https://auth.test',
    ];

    $token = Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123')
        ->oAuthClient(session: $session)
        ->exchangeCallback($query, $session);

    expect($token->accessToken)->toBe('access-token');
});

it('rejects a mismatched iss parameter on the authorization callback', function (): void {
    fakeDiscovery();

    $key = 'mcp.oauth.' . sha1('https://mcp.test/mcp');

    Session::put($key, [
        'state' => 'the-state',
        'verifier' => 'the-verifier',
        'client_id' => 'client-123',
        'client_secret' => null,
        'token_endpoint' => 'https://auth.test/token',
        'redirect_uri' => 'https://app.test/callback',
        'return_to' => null,
        'issuer' => 'https://auth.test',
        'iss_supported' => true,
    ]);

    $session = mcpSession();
    $query = [
        'code' => 'auth-code',
        'state' => 'the-state',
        'iss' => 'https://evil.test',
    ];

    expect(fn(): TokenSet => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123')
        ->oAuthClient(session: $session)
        ->exchangeCallback($query, $session))
        ->toThrow(OAuthException::class, 'iss) parameter did not match');
});

it('rejects a missing iss parameter when the server advertises support', function (): void {
    fakeDiscovery();

    $key = 'mcp.oauth.' . sha1('https://mcp.test/mcp');

    Session::put($key, [
        'state' => 'the-state',
        'verifier' => 'the-verifier',
        'client_id' => 'client-123',
        'client_secret' => null,
        'token_endpoint' => 'https://auth.test/token',
        'redirect_uri' => 'https://app.test/callback',
        'return_to' => null,
        'issuer' => 'https://auth.test',
        'iss_supported' => true,
    ]);

    $session = mcpSession();
    $query = [
        'code' => 'auth-code',
        'state' => 'the-state',
    ];

    expect(fn(): TokenSet => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123')
        ->oAuthClient(session: $session)
        ->exchangeCallback($query, $session))
        ->toThrow(OAuthException::class, 'missing the required iss parameter');
});

it('records the issuer in the session for callback validation', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
            'authorization_response_iss_parameter_supported' => true,
        ]),
    ]);

    Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient(session: mcpSession())
        ->redirect();

    $stored = Session::get('mcp.oauth.' . sha1('https://mcp.test/mcp'));

    expect($stored['issuer'])->toBe('https://auth.test')
        ->and($stored['iss_supported'])->toBeTrue();
});

it('defaults the scope to mcp:use', function (): void {
    fakeDiscovery();

    $target = responseLocation(Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient(session: mcpSession())
        ->redirect());

    parse_str((string)parse_url($target, PHP_URL_QUERY), $query);

    expect($query['scope'])->toBe('mcp:use');
});

it('does not request every supported scope from protected resource metadata', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
            'scopes_supported' => ['mcp:read', 'mcp:write'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    $target = responseLocation(Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient(session: mcpSession())
        ->redirect());

    parse_str((string)parse_url($target, PHP_URL_QUERY), $query);

    expect($query['scope'])->toBe('mcp:use');
});

it('prefers the challenge scope over the configured scope', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
            'scopes_supported' => ['mcp:read'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    $target = responseLocation(Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', scope: 'mcp:use', redirectUri: 'https://app.test/callback')
        ->oAuthClient(challengeScope: 'files:read files:write', session: mcpSession())
        ->redirect());

    parse_str((string)parse_url($target, PHP_URL_QUERY), $query);

    expect($query['scope'])->toBe('files:read files:write');
});

it('sends a native application_type when registering a localhost client', function (): void {
    Http::fake(array_merge(fakeDiscoveryMap(), [
        'https://auth.test/register' => Http::response(['client_id' => 'dcr-1']),
    ]));

    Client::web('https://mcp.test/mcp')
        ->withOAuth(redirectUri: 'http://localhost:3000/callback')
        ->oAuthClient(session: mcpSession())
        ->redirect();

    Http::assertSent(fn($request): bool => $request->url() === 'https://auth.test/register'
        && ($request['application_type'] ?? null) === 'native');
});

it('sends a web application_type when registering a remote client', function (): void {
    Http::fake(array_merge(fakeDiscoveryMap(), [
        'https://auth.test/register' => Http::response(['client_id' => 'dcr-1']),
    ]));

    Client::web('https://mcp.test/mcp')
        ->withOAuth(redirectUri: 'https://app.test/callback')
        ->oAuthClient(session: mcpSession())
        ->redirect();

    Http::assertSent(fn($request): bool => $request->url() === 'https://auth.test/register'
        && ($request['application_type'] ?? null) === 'web');
});

it('strips fragments but preserves trailing slashes in the resource identifier', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp/' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    $target = responseLocation(Client::web('https://mcp.test/mcp/#fragment')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient(session: mcpSession())
        ->redirect());

    parse_str((string)parse_url($target, PHP_URL_QUERY), $query);

    expect($query['resource'])->toBe('https://mcp.test/mcp/');
});

it('strips the fragment but preserves the query string in the resource identifier', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    $target = responseLocation(Client::web('https://mcp.test/mcp?tenant=acme#section')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient(session: mcpSession())
        ->redirect());

    parse_str((string)parse_url($target, PHP_URL_QUERY), $query);

    expect($query['resource'])->toBe('https://mcp.test/mcp?tenant=acme');
});

it('leaves a resource identifier without a fragment unchanged', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    $target = responseLocation(Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient(session: mcpSession())
        ->redirect());

    parse_str((string)parse_url($target, PHP_URL_QUERY), $query);

    expect($query['resource'])->toBe('https://mcp.test/mcp');
});

it('rejects protected resource metadata when a trailing slash differs', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp/' => Http::response([
            'resource' => 'https://mcp.test/mcp',
            'authorization_servers' => ['https://auth.test'],
        ]),
    ]);

    expect(fn(): Response => Client::web('https://mcp.test/mcp/')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuthClient(session: mcpSession())
        ->redirect())
        ->toThrow(OAuthException::class, 'did not match the expected resource [https://mcp.test/mcp/]');
});

it('includes the resource parameter on a refresh token request', function (): void {
    Http::fake(array_merge(fakeDiscoveryMap(), [
        'https://auth.test/token' => Http::response(['access_token' => 'fresh-token']),
    ]));

    Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123')
        ->oAuthClient(session: mcpSession())
        ->refreshCredentials('old-refresh');

    Http::assertSent(fn($request): bool => $request->url() === 'https://auth.test/token'
        && ($request['grant_type'] ?? null) === 'refresh_token'
        && ($request['resource'] ?? null) === 'https://mcp.test/mcp');
});

it('defaults the redirect uri to the package callback route for a registered client', function (): void {
    fakeDiscovery();

    config(['Mcp.oauth.routes.github.callback' => 'https://app.test/mcp/oauth/github/callback']);

    $target = responseLocation(Client::web('https://mcp.test/mcp')
        ->setName('github')
        ->withOAuth(clientId: 'client-123', scope: 'mcp:use')
        ->oAuthClient(session: mcpSession())
        ->redirect());

    parse_str((string)parse_url($target, PHP_URL_QUERY), $query);

    expect($query['redirect_uri'])->toBe('https://app.test/mcp/oauth/github/callback');
});

it('lets an explicit redirect uri override the default callback route', function (): void {
    fakeDiscovery();

    config(['Mcp.oauth.routes.github.callback' => 'https://app.test/mcp/oauth/github/callback']);

    $target = responseLocation(Client::web('https://mcp.test/mcp')
        ->setName('github')
        ->withOAuth(clientId: 'client-123', scope: 'mcp:use', redirectUri: 'https://app.test/custom')
        ->oAuthClient(session: mcpSession())
        ->redirect());

    parse_str((string)parse_url($target, PHP_URL_QUERY), $query);

    expect($query['redirect_uri'])->toBe('https://app.test/custom');
});

it('surfaces the dynamically registered credentials on the token set', function (): void {
    Http::fake(array_merge(fakeDiscoveryMap(), [
        'https://auth.test/token' => Http::response(['access_token' => 'access-token', 'refresh_token' => 'refresh-token']),
    ]));

    $key = 'mcp.oauth.' . sha1('https://mcp.test/mcp');

    Session::put($key, [
        'state' => 'the-state',
        'verifier' => 'the-verifier',
        'client_id' => 'dcr-999',
        'client_secret' => 'dcr-secret',
        'token_endpoint' => 'https://auth.test/token',
        'token_auth_method' => 'client_secret_post',
        'redirect_uri' => 'https://app.test/callback',
        'return_to' => null,
    ]);

    $session = mcpSession();
    $query = [
        'code' => 'auth-code',
        'state' => 'the-state',
    ];

    $token = Client::web('https://mcp.test/mcp')
        ->withOAuth()
        ->oAuthClient(session: $session)
        ->exchangeCallback($query, $session);

    expect($token->clientId)->toBe('dcr-999')
        ->and($token->clientSecret)->toBe('dcr-secret');
});

it('refreshes using explicitly passed client credentials', function (): void {
    Http::fake(array_merge(fakeDiscoveryMap(), [
        'https://auth.test/token' => Http::response(['access_token' => 'fresh-token']),
    ]));

    $token = Client::web('https://mcp.test/mcp')
        ->withOAuth(scope: 'mcp:use')
        ->oAuthClient(session: mcpSession())
        ->refreshCredentials('old-refresh', clientId: 'dcr-999', clientSecret: 'dcr-secret');

    expect($token->clientId)->toBe('dcr-999');

    Http::assertSent(fn($request): bool => ($request['grant_type'] ?? null) === 'refresh_token'
        && ($request['client_id'] ?? null) === 'dcr-999'
        && ($request['client_secret'] ?? null) === 'dcr-secret');
});

it('uses client_secret_basic when the server only supports it', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
            'token_endpoint_auth_methods_supported' => ['client_secret_basic'],
        ]),
        'https://auth.test/token' => Http::response(['access_token' => 'machine-token']),
    ]);

    Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'svc', clientSecret: 'secret', scope: 'mcp:use')
        ->oAuthClient(session: mcpSession())
        ->clientCredentials();

    Http::assertSent(function ($request): bool {
        $authorization = $request->header('Authorization')[0] ?? '';

        return $request->url() === 'https://auth.test/token'
            && $authorization === 'Basic ' . base64_encode('svc:secret')
            && ! array_key_exists('client_secret', $request->data());
    });
});
