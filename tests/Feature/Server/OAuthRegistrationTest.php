<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventManager;
use Cake\Http\ServerRequest;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Cake\TestSuite\IntegrationTestTrait;
use Crustum\Mcp\Controller\OAuthRegisterController;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Tessera;

uses(IntegrationTestTrait::class);

beforeEach(function (): void {
    $this->configApplication(\TestApp\Application::class, [CONFIG]);
    Configure::write('ORM.mapJsonTypeForSqlite', true);
    Configure::write('App.fullBaseUrl', 'https://mcp.example.com');
    httpOAuthConfig();
    EventManager::instance()->off('Server.buildMiddleware');
    EventManager::instance()->on('Server.buildMiddleware', static function (): void {
        // The plugin bootstrap reloads config/mcp.php on every request,
        // wiping test Configure writes. Re-apply them after bootstrap.
        foreach (httpOAuthServerConfig() as $key => $value) {
            Configure::write($key, $value);
        }
    });
    // Routes load per request via TestApp pluginRoutes (config/routes.php);
    // named guards in Registrar::oauthRoutes() keep that idempotent.
    httpOAuthTable();
});

afterEach(function (): void {
    EventManager::instance()->off('Server.buildMiddleware');
    Configure::write('ORM.mapJsonTypeForSqlite', false);
    Router::resetRoutes();
    TableRegistry::getTableLocator()->clear();
});

/**
 * Store request-scoped plugin config applied after bootstrap reload.
 *
 * @param array<string, mixed>|null $config Config to store, or null to read
 * @return array<string, mixed>
 */
function httpOAuthServerConfig(?array $config = null): array
{
    static $stored = [];

    if ($config !== null) {
        $stored = $config;
    }

    return $stored;
}

/**
 * Set plugin config for the next dispatched requests.
 *
 * @param array<string, mixed> $overrides Config overrides
 * @return void
 */
function httpOAuthConfig(array $overrides = []): void
{
    httpOAuthServerConfig(array_merge([
        'Mcp.redirect_domains' => ['*'],
        'Mcp.custom_schemes' => [],
        'Mcp.authorization_server' => null,
        'Mcp.oauth.authorization_endpoint' => null,
        'Mcp.oauth.token_endpoint' => null,
    ], $overrides));
}

/**
 * Recreate the in-memory OAuth clients table for registration tests.
 *
 * @param list<string> $metadataColumns Metadata columns to include
 * @param bool $withScopes Whether to include the scopes column
 * @return void
 */
function httpOAuthTable(array $metadataColumns = ['logo_uri', 'client_uri'], bool $withScopes = true): void
{
    $connection = ConnectionManager::get('test');
    $connection->execute('DROP TABLE IF EXISTS oauth_clients');
    TableRegistry::getTableLocator()->clear();

    $extra = '';

    foreach ($metadataColumns as $column) {
        $extra .= ", {$column} VARCHAR(2048) NULL";
    }

    if ($withScopes) {
        $extra .= ', scopes JSON NULL';
    }

    $connection->execute(
        "CREATE TABLE oauth_clients (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            owner_id VARCHAR(36) NULL,
            owner_type VARCHAR(255) NULL,
            name VARCHAR(255) NOT NULL,
            secret VARCHAR(255) NULL,
            provider VARCHAR(255) NULL,
            redirect_uris TEXT NOT NULL,
            grant_types TEXT NOT NULL,
            revoked BOOLEAN NOT NULL DEFAULT 0,
            created DATETIME NULL,
            modified DATETIME NULL{$extra}
        )",
    );
}

/**
 * Count rows in the OAuth clients table.
 *
 * @return int
 */
function httpOAuthCount(): int
{
    return Tessera::clientsTable()->find()->count();
}

/**
 * Read the single OAuth client row created by a registration request.
 *
 * @return \Crustum\Tessera\Model\Entity\Client
 */
function httpOAuthRow(): Client
{
    return Tessera::clientsTable()->find()->firstOrFail();
}

/**
 * Decode the last integration response body.
 *
 * @param array<string, mixed> $body Response body
 * @return array<string, mixed>
 */
function httpOAuthBody(array $body): array
{
    return $body;
}

/**
 * Invoke a protected OAuthRegisterController method for unit testing.
 *
 * @param string $method Method name
 * @param array<int, mixed> $arguments Arguments
 * @return mixed
 */
function callOAuthRegister(string $method, array $arguments = []): mixed
{
    $controller = new OAuthRegisterController(new ServerRequest([
        'environment' => ['REQUEST_METHOD' => 'POST'],
    ]));
    $reflection = new ReflectionClass($controller);

    return $reflection->getMethod($method)->invoke($controller, ...$arguments);
}

it('handles oauth registration endpoint', function (): void {
    $this->post('/oauth/register', [
        'client_name' => 'Test Client',
        'redirect_uris' => ['http://localhost:3000/callback'],
    ]);

    $this->assertResponseCode(201);
    $body = json_decode((string)$this->_response->getBody(), true);

    expect($body['client_id'])->toBeString()->not->toBe('')
        ->and(httpOAuthBody($body))->toMatchArray([
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'redirect_uris' => ['http://localhost:3000/callback'],
            'scope' => 'mcp:use',
            'token_endpoint_auth_method' => 'none',
        ])
        ->and($body)->not->toHaveKey('logo_uri')
        ->and($body)->not->toHaveKey('client_uri');
});

it('persists and returns supported registration metadata', function (array $columns, array $expected): void {
    httpOAuthTable($columns);

    $this->post('/oauth/register', [
        'redirect_uris' => ['https://example.com/callback'],
        'logo_uri' => 'https://example.com/logo.png',
        'client_uri' => 'http://example.com',
    ]);

    $this->assertResponseCode(201);
    $body = json_decode((string)$this->_response->getBody(), true);

    expect(array_intersect_key($body, ['logo_uri' => true, 'client_uri' => true]))->toBe($expected)
        ->and(array_intersect_key(httpOAuthRow()->toArray(), ['logo_uri' => true, 'client_uri' => true]))
        ->toBe($expected);
})->with([
    'both columns' => [
        ['logo_uri', 'client_uri'],
        ['logo_uri' => 'https://example.com/logo.png', 'client_uri' => 'http://example.com'],
    ],
    'logo only' => [
        ['logo_uri'],
        ['logo_uri' => 'https://example.com/logo.png'],
    ],
    'website only' => [
        ['client_uri'],
        ['client_uri' => 'http://example.com'],
    ],
    'neither column' => [[], []],
]);

it('omits missing or null registration metadata from the response', function (?array $metadata): void {
    $this->post('/oauth/register', array_merge(
        ['redirect_uris' => ['https://example.com/callback']],
        $metadata ?? [],
    ));

    $this->assertResponseCode(201);
    $body = json_decode((string)$this->_response->getBody(), true);

    expect($body)->not->toHaveKey('logo_uri')
        ->and($body)->not->toHaveKey('client_uri');

    $row = httpOAuthRow();

    expect($row->get('logo_uri'))->toBeNull()
        ->and($row->get('client_uri'))->toBeNull();
})->with([
    'omitted' => [null],
    'null' => [['logo_uri' => null, 'client_uri' => null]],
]);

it('rejects invalid registration metadata before creating a client', function (string $attribute, mixed $value): void {
    $this->post('/oauth/register', [
        'redirect_uris' => ['https://example.com/callback'],
        $attribute => $value,
    ]);

    $this->assertResponseCode(400);
    $body = json_decode((string)$this->_response->getBody(), true);

    expect($body['error'])->toBe('invalid_client_metadata')
        ->and(httpOAuthCount())->toBe(0);
})->with(['logo_uri', 'client_uri'])->with([
    'invalid URL' => ['not-a-url'],
    'unsupported scheme' => ['ftp://example.com/logo.png'],
    'non-string' => [['https://example.com']],
    'too long' => ['https://example.com/' . str_repeat('a', 2030)],
]);

it('rejects non-string redirect URIs before creating a client', function (): void {
    $this->post('/oauth/register', [
        'redirect_uris' => [42],
    ]);

    $this->assertResponseCode(400);
    $body = json_decode((string)$this->_response->getBody(), true);

    expect($body['error'])->toBe('invalid_redirect_uri')
        ->and(httpOAuthCount())->toBe(0);
});

it('keeps redirect errors ahead of metadata errors', function (): void {
    $this->post('/oauth/register', [
        'redirect_uris' => ['not-a-url'],
        'logo_uri' => 'not-a-url',
    ]);

    $this->assertResponseCode(400);
    $body = json_decode((string)$this->_response->getBody(), true);

    expect($body)->toBe([
        'error' => 'invalid_redirect_uri',
        'error_description' => 'redirect_uris.0 is not a valid URL.',
    ]);
});

it('describes the redirect error when an earlier rule also fails', function (): void {
    $this->post('/oauth/register', [
        'client_name' => ['not-a-name'],
        'redirect_uris' => ['not-a-url'],
    ]);

    $this->assertResponseCode(400);
    $body = json_decode((string)$this->_response->getBody(), true);

    expect($body)->toBe([
        'error' => 'invalid_redirect_uri',
        'error_description' => 'redirect_uris.0 is not a valid URL.',
    ]);
});

it('falls back to the redirect host when no client name is provided', function (): void {
    $this->post('/oauth/register', [
        'redirect_uris' => ['https://example.com/callback'],
    ]);

    $this->assertResponseCode(201);

    expect(httpOAuthRow()->get('name'))->toBe('example.com');
});

it('returns invalid_client_metadata when client metadata is invalid', function (): void {
    $this->post('/oauth/register', [
        'client_name' => str_repeat('a', 256),
        'redirect_uris' => ['https://example.com/callback'],
    ]);

    $this->assertResponseCode(400);
    $body = json_decode((string)$this->_response->getBody(), true);

    expect($body['error'])->toBe('invalid_client_metadata');
});

it('preserves a falsy client name for oauth registration', function (): void {
    $this->post('/oauth/register', [
        'client_name' => '0',
        'redirect_uris' => ['https://example.com/callback'],
    ]);

    $this->assertResponseCode(201);

    expect(httpOAuthRow()->get('name'))->toBe('0');
});

it('falls back to the legacy name field for oauth registration', function (): void {
    $this->post('/oauth/register', [
        'name' => 'Legacy Client',
        'redirect_uris' => ['http://localhost:3000/callback'],
    ]);

    $this->assertResponseCode(201);

    expect(httpOAuthRow()->get('name'))->toBe('Legacy Client');
});

it('prefers client_name over name for oauth registration', function (): void {
    $this->post('/oauth/register', [
        'client_name' => 'Preferred Client',
        'name' => 'Legacy Client',
        'redirect_uris' => ['http://localhost:3000/callback'],
    ]);

    $this->assertResponseCode(201);

    expect(httpOAuthRow()->get('name'))->toBe('Preferred Client');
});

it('handles oauth registration with allowed domains', function (): void {
    httpOAuthConfig(['Mcp.redirect_domains' => ['http://localhost:3000/']]);

    $this->post('/oauth/register', [
        'client_name' => 'Test Client',
        'redirect_uris' => ['http://localhost:3000/callback'],
    ]);

    $this->assertResponseCode(201);
    $body = json_decode((string)$this->_response->getBody(), true);

    expect(httpOAuthBody($body))->toMatchArray([
        'redirect_uris' => ['http://localhost:3000/callback'],
        'scope' => 'mcp:use',
        'token_endpoint_auth_method' => 'none',
    ]);
});

it('allows localhost with dynamic port when localhost is in redirect_domains', function (string $uri): void {
    httpOAuthConfig(['Mcp.redirect_domains' => ['https://example.com', 'http://localhost']]);

    $this->post('/oauth/register', [
        'client_name' => 'Test Client',
        'redirect_uris' => [$uri],
    ]);

    $this->assertResponseCode(201);
})->with([
    'localhost' => ['http://localhost:18293/callback'],
    '127.0.0.1' => ['http://127.0.0.1:29100/callback'],
    'IPv6 loopback' => ['http://[::1]:39201/callback'],
]);

it('rejects localhost with dynamic port when localhost is not in redirect_domains', function (string $uri): void {
    httpOAuthConfig(['Mcp.redirect_domains' => ['https://example.com']]);

    $this->post('/oauth/register', [
        'client_name' => 'Test Client',
        'redirect_uris' => [$uri],
    ]);

    $this->assertResponseCode(400);
})->with([
    'localhost' => ['http://localhost:18293/callback'],
    '127.0.0.1' => ['http://127.0.0.1:29100/callback'],
    'IPv6 loopback' => ['http://[::1]:39201/callback'],
]);

it('does not allow non-localhost URLs when localhost is in redirect_domains', function (): void {
    httpOAuthConfig(['Mcp.redirect_domains' => ['https://example.com', 'http://localhost']]);

    $this->post('/oauth/register', [
        'client_name' => 'Test Client',
        'redirect_uris' => ['http://evil.com:18293/callback'],
    ]);

    $this->assertResponseCode(400);
});

it('does not allow https localhost URLs via localhost redirect domain', function (): void {
    httpOAuthConfig(['Mcp.redirect_domains' => ['https://example.com', 'http://localhost']]);

    $this->post('/oauth/register', [
        'client_name' => 'Test Client',
        'redirect_uris' => ['https://localhost:18293/callback'],
    ]);

    $this->assertResponseCode(400);
});

it('allows all localhost hosts when any localhost variant is in redirect_domains', function (string $configDomain): void {
    httpOAuthConfig(['Mcp.redirect_domains' => [$configDomain]]);

    $this->post('/oauth/register', [
        'client_name' => 'Test Client',
        'redirect_uris' => ['http://localhost:18293/callback'],
    ]);

    $this->assertResponseCode(201);
})->with([
    'http://127.0.0.1' => ['http://127.0.0.1'],
    'http://[::1]' => ['http://[::1]'],
    'localhost without scheme' => ['localhost'],
]);

it('handles oauth registration with incorrect redirect domain', function (): void {
    httpOAuthConfig(['Mcp.redirect_domains' => ['http://allowed-domain.com/']]);

    $this->post('/oauth/register', [
        'client_name' => 'Test Client',
        'redirect_uris' => ['http://not-allowed.com/callback'],
    ]);

    $this->assertResponseCode(400);
});

it('accepts custom scheme redirect URIs when the scheme is configured', function (string $uri): void {
    httpOAuthConfig(['Mcp.custom_schemes' => ['cursor', 'vscode', 'claude']]);

    $this->post('/oauth/register', [
        'client_name' => 'Desktop Client',
        'redirect_uris' => [$uri],
    ]);

    $this->assertResponseCode(201);
    $body = json_decode((string)$this->_response->getBody(), true);

    expect($body['redirect_uris'])->toBe([$uri]);
})->with([
    'cursor scheme' => ['cursor://anysphere.cursor-mcp/oauth/callback'],
    'vscode scheme' => ['vscode://extension.mcp/callback'],
    'claude scheme' => ['claude://desktop.app/oauth/callback'],
]);

it('rejects custom scheme redirect URIs when the scheme is not configured', function (): void {
    httpOAuthConfig(['Mcp.custom_schemes' => []]);

    $this->post('/oauth/register', [
        'client_name' => 'Desktop Client',
        'redirect_uris' => ['cursor://anysphere.cursor-mcp/oauth/callback'],
    ]);

    $this->assertResponseCode(400);
});

it('rejects custom scheme redirect URIs when a different scheme is configured', function (): void {
    httpOAuthConfig(['Mcp.custom_schemes' => ['vscode']]);

    $this->post('/oauth/register', [
        'client_name' => 'Desktop Client',
        'redirect_uris' => ['cursor://anysphere.cursor-mcp/oauth/callback'],
    ]);

    $this->assertResponseCode(400);
});

it('rejects custom scheme redirect URIs with missing host', function (): void {
    httpOAuthConfig(['Mcp.custom_schemes' => ['cursor']]);

    $this->post('/oauth/register', [
        'client_name' => 'Desktop Client',
        'redirect_uris' => ['cursor:///callback'],
    ]);

    $this->assertResponseCode(400);
});

it('still allows standard http URLs when custom schemes are configured', function (): void {
    httpOAuthConfig(['Mcp.custom_schemes' => ['cursor']]);

    $this->post('/oauth/register', [
        'client_name' => 'Web Client',
        'redirect_uris' => ['http://localhost:3000/callback'],
    ]);

    $this->assertResponseCode(201);
});

it('returns json validation errors even without Accept application/json header', function (): void {
    $this->post('/oauth/register', [
        'redirect_uris' => ['not-a-valid-url'],
    ]);

    $this->assertResponseCode(400);

    expect($this->_response->getHeaderLine('Content-Type'))->toContain('application/json');

    $body = json_decode((string)$this->_response->getBody(), true);

    expect($body)->toHaveKey('error')
        ->and($body)->toHaveKey('error_description');
});

it('persists the advertised mcp scope when clients are scope-restricted by default', function (): void {
    $client = (new ClientRepository())->createAuthorizationCodeGrantClient(
        'Scoped',
        ['http://localhost:3000/callback'],
        false,
    );
    $client->set('scopes', []);
    Tessera::clientsTable()->saveOrFail($client);

    callOAuthRegister('grantMcpScope', [$client]);

    expect(Tessera::clientsTable()->get($client->id)->get('scopes'))->toBe(['mcp:use']);
});

it('preserves scopes granted at creation when persisting the mcp scope', function (): void {
    $client = (new ClientRepository())->createAuthorizationCodeGrantClient(
        'Scoped',
        ['http://localhost:3000/callback'],
        false,
    );
    $client->set('scopes', ['custom:scope']);
    Tessera::clientsTable()->saveOrFail($client);

    callOAuthRegister('grantMcpScope', [$client]);

    expect(Tessera::clientsTable()->get($client->id)->get('scopes'))->toBe(['custom:scope', 'mcp:use']);
});

it('does not duplicate the mcp scope when it was already granted at creation', function (): void {
    $client = (new ClientRepository())->createAuthorizationCodeGrantClient(
        'Scoped',
        ['http://localhost:3000/callback'],
        false,
    );
    $client->set('scopes', ['mcp:use']);
    Tessera::clientsTable()->saveOrFail($client);

    callOAuthRegister('grantMcpScope', [$client]);

    expect(Tessera::clientsTable()->get($client->id)->get('scopes'))->toBe(['mcp:use']);
});

it('leaves clients unrestricted when the scopes value is null', function (): void {
    $client = (new ClientRepository())->createAuthorizationCodeGrantClient(
        'Scoped',
        ['http://localhost:3000/callback'],
        false,
    );

    callOAuthRegister('grantMcpScope', [$client]);

    expect(Tessera::clientsTable()->get($client->id)->get('scopes'))->toBeNull();
});

it('leaves clients unrestricted when client scopes are not enabled', function (): void {
    httpOAuthTable(['logo_uri', 'client_uri'], false);

    $client = (new ClientRepository())->createAuthorizationCodeGrantClient(
        'Scoped',
        ['http://localhost:3000/callback'],
        false,
    );

    callOAuthRegister('grantMcpScope', [$client]);

    expect(Tessera::clientsTable()->get($client->id)->has('scopes'))->toBeFalse();
});

it('handles oauth discovery with multi-segment paths', function (): void {
    httpOAuthConfig([
        'Mcp.authorization_server' => 'https://mcp.example.com',
        'Mcp.oauth.authorization_endpoint' => 'https://mcp.example.com/oauth/authorize',
        'Mcp.oauth.token_endpoint' => 'https://mcp.example.com/oauth/token',
    ]);

    $this->get('/.well-known/oauth-protected-resource/mcp/weather');

    $this->assertResponseCode(200);
    $body = json_decode((string)$this->_response->getBody(), true);

    expect(httpOAuthBody($body))->toMatchArray([
        'resource' => 'https://mcp.example.com/mcp/weather',
        'authorization_servers' => ['https://mcp.example.com'],
        'scopes_supported' => ['mcp:use'],
    ]);

    $this->get('/.well-known/oauth-authorization-server/mcp/weather');

    $this->assertResponseCode(200);
    $body = json_decode((string)$this->_response->getBody(), true);

    expect($body['issuer'])->toBe('https://mcp.example.com')
        ->and($body['scopes_supported'])->toBe(['mcp:use'])
        ->and($body['response_types_supported'])->toBe(['code'])
        ->and($body['code_challenge_methods_supported'])->toBe(['S256'])
        ->and($body['grant_types_supported'])->toBe(['authorization_code', 'refresh_token']);
});

it('handles oauth discovery with single segment paths', function (): void {
    $this->get('/.well-known/oauth-protected-resource/mcp');

    $this->assertResponseCode(200);
    $body = json_decode((string)$this->_response->getBody(), true);

    expect($body['resource'])->toBe('https://mcp.example.com/mcp')
        ->and($body['scopes_supported'])->toBe(['mcp:use']);

    $this->get('/.well-known/oauth-authorization-server/mcp');

    $this->assertResponseCode(200);
    $body = json_decode((string)$this->_response->getBody(), true);

    expect($body['issuer'])->toBe('https://mcp.example.com');
});

it('handles oauth discovery with no path', function (): void {
    $this->get('/.well-known/oauth-protected-resource');

    $this->assertResponseCode(200);
    $body = json_decode((string)$this->_response->getBody(), true);

    expect($body['resource'])->toBe('https://mcp.example.com/')
        ->and($body['scopes_supported'])->toBe(['mcp:use']);

    $this->get('/.well-known/oauth-authorization-server');

    $this->assertResponseCode(200);
    $body = json_decode((string)$this->_response->getBody(), true);

    expect($body['issuer'])->toBe('https://mcp.example.com');
});

it('uses configured authorization_server for authorization_servers field', function (): void {
    httpOAuthConfig(['Mcp.authorization_server' => 'https://auth.example.com']);

    $this->get('/.well-known/oauth-protected-resource/mcp');

    $this->assertResponseCode(200);
    $body = json_decode((string)$this->_response->getBody(), true);

    expect($body['authorization_servers'])->toBe(['https://auth.example.com']);
});

it('uses configured authorization_server as issuer in authorization server metadata', function (): void {
    httpOAuthConfig(['Mcp.authorization_server' => 'https://auth.example.com']);

    $this->get('/.well-known/oauth-authorization-server');

    $this->assertResponseCode(200);
    $body = json_decode((string)$this->_response->getBody(), true);

    expect($body['issuer'])->toBe('https://auth.example.com');
});
