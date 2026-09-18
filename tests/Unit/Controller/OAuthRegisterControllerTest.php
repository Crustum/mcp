<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Http\ServerRequest;
use Cake\ORM\TableRegistry;
use Crustum\Mcp\Controller\OAuthRegisterController;
use Crustum\Mcp\Server\Registrar;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Tessera;

function oauthRegisterController(): OAuthRegisterController
{
    return new OAuthRegisterController(new ServerRequest([
        'environment' => ['REQUEST_METHOD' => 'POST'],
    ]));
}

function oauthRegisterRequest(array $data): OAuthRegisterController
{
    return new OAuthRegisterController(new ServerRequest([
        'environment' => ['REQUEST_METHOD' => 'POST'],
        'post' => $data,
    ]));
}

function invokeOAuthRegisterMethod(OAuthRegisterController $controller, string $method, array $arguments = []): mixed
{
    $reflection = new ReflectionClass($controller);
    $reflectionMethod = $reflection->getMethod($method);

    return $reflectionMethod->invoke($controller, ...$arguments);
}

function createOauthClientsTable(string $table = 'oauth_clients', bool $withMetadata = true): void
{
    $metadata = $withMetadata
        ? ",
            logo_uri VARCHAR(2048) NULL,
            client_uri VARCHAR(2048) NULL"
        : '';

    ConnectionManager::get('test')->execute(
        "CREATE TABLE IF NOT EXISTS {$table} (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            owner_id VARCHAR(36) NULL,
            owner_type VARCHAR(255) NULL,
            name VARCHAR(255) NOT NULL,
            secret VARCHAR(255) NULL,
            provider VARCHAR(255) NULL,
            redirect_uris TEXT NOT NULL,
            grant_types TEXT NOT NULL,
            scopes TEXT NULL,
            revoked BOOLEAN NOT NULL DEFAULT 0,
            created DATETIME NULL,
            modified DATETIME NULL{$metadata}
        )",
    );
}

/**
 * Custom client entity with metadata support (mutator equivalent).
 */
class CustomMetadataClient extends Client
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'logo_uri' => true,
        'client_uri' => true,
    ];

    /**
     * Normalize the logo URI on set.
     *
     * @param mixed $value Logo URI
     * @return mixed
     */
    protected function _setLogoUri(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return strtolower($value);
    }
}

beforeEach(function (): void {
    Configure::write('Mcp.redirect_domains', ['*']);
    Configure::write('Mcp.custom_schemes', []);
});

afterEach(function (): void {
    TableRegistry::getTableLocator()->clear();
});

it('resolves the client name from client_name', function (): void {
    $controller = oauthRegisterController();

    expect(invokeOAuthRegisterMethod($controller, 'resolveClientName', [[
        'client_name' => 'Cursor',
        'redirect_uris' => ['https://example.com/callback'],
    ]]))->toBe('Cursor');
});

it('falls back to the redirect host when no client name is provided', function (): void {
    $controller = oauthRegisterController();

    expect(invokeOAuthRegisterMethod($controller, 'resolveClientName', [[
        'redirect_uris' => ['https://example.com/callback'],
    ]]))->toBe('example.com');
});

it('accepts localhost redirects when localhost is allowed', function (): void {
    Configure::write('Mcp.redirect_domains', ['http://localhost']);

    $controller = oauthRegisterController();

    expect(invokeOAuthRegisterMethod($controller, 'validateRedirectUri', [
        'http://localhost:3000/callback',
    ]))->toBeTrue();
});

it('rejects redirects outside the allow-list', function (): void {
    Configure::write('Mcp.redirect_domains', ['https://allowed.example/']);

    $controller = oauthRegisterController();

    expect(invokeOAuthRegisterMethod($controller, 'validateRedirectUri', [
        'https://evil.example/callback',
    ]))->toBe('redirect_uris is not a permitted redirect domain.');
});

it('rejects missing redirect uris during validation', function (): void {
    $controller = oauthRegisterController();
    $validator = invokeOAuthRegisterMethod($controller, 'createValidator');

    $errors = $validator->validate([]);

    expect($errors)->toHaveKey('redirect_uris');
});

it('documents the advertised oauth scope constant', function (): void {
    expect(Registrar::OAUTH_SCOPE)->toBe('mcp:use');
});

it('rejects invalid client metadata urls', function (): void {
    $controller = oauthRegisterController();
    $validator = invokeOAuthRegisterMethod($controller, 'createValidator');

    $tooLong = 'https://app.example/' . str_repeat('a', 2048);

    foreach (['logo_uri', 'client_uri'] as $field) {
        foreach (['not-a-url', 'ftp://app.example/logo.png', 42, $tooLong] as $value) {
            $errors = $validator->validate([
                'redirect_uris' => ['https://app.example/callback'],
                $field => $value,
            ]);

            expect($errors)->toHaveKey($field);
        }
    }
});

it('accepts valid logo and client uris', function (): void {
    $controller = oauthRegisterController();
    $validator = invokeOAuthRegisterMethod($controller, 'createValidator');

    $errors = $validator->validate([
        'redirect_uris' => ['https://app.example/callback'],
        'logo_uri' => 'https://app.example/logo.png',
        'client_uri' => 'http://app.example/',
    ]);

    expect($errors)->toBe([]);
});

it('allows missing logo and client uris', function (): void {
    $controller = oauthRegisterController();
    $validator = invokeOAuthRegisterMethod($controller, 'createValidator');

    $errors = $validator->validate([
        'redirect_uris' => ['https://app.example/callback'],
    ]);

    expect($errors)->toBe([]);
});

it('reports non-string redirect items with per-item messages', function (): void {
    $controller = oauthRegisterController();

    $itemErrors = invokeOAuthRegisterMethod($controller, 'redirectUriItemErrors', [[
        'redirect_uris' => [42],
    ]]);

    expect($itemErrors)->toBe(['redirect_uris.0' => 'redirect_uris.0 is not a valid URL.']);
});

it('prefers redirect errors over metadata errors', function (): void {
    $controller = oauthRegisterController();
    $validator = invokeOAuthRegisterMethod($controller, 'createValidator');

    $data = [
        'redirect_uris' => ['not-a-url'],
        'logo_uri' => 'ftp://app.example/logo.png',
    ];
    $errors = $validator->validate($data);

    expect($errors)->not->toBe([])
        ->and(invokeOAuthRegisterMethod($controller, 'firstRedirectUriError', [$data, $errors]))
        ->toBe('redirect_uris.0 is not a valid URL.');
});

it('describes the redirect error when an earlier rule also fails', function (): void {
    $controller = oauthRegisterController();
    $validator = invokeOAuthRegisterMethod($controller, 'createValidator');

    $data = ['logo_uri' => 'not-a-url'];
    $errors = $validator->validate($data);

    $message = invokeOAuthRegisterMethod($controller, 'firstRedirectUriError', [$data, $errors]);

    expect($message)->not->toBe('')
        ->and($message)->toBe(invokeOAuthRegisterMethod($controller, 'firstRedirectUriMessage', [$errors]));
});

it('returns invalid_redirect_uri for non-string redirect items', function (): void {
    $controller = oauthRegisterRequest(['redirect_uris' => [42]]);

    $response = $controller->register();

    expect($response->getStatusCode())->toBe(400)
        ->and(json_decode((string)$response->getBody(), true))->toBe([
            'error' => 'invalid_redirect_uri',
            'error_description' => 'redirect_uris.0 is not a valid URL.',
        ]);
});

it('returns invalid_client_metadata for bad logo uris', function (): void {
    $controller = oauthRegisterRequest([
        'redirect_uris' => ['https://app.example/callback'],
        'logo_uri' => 'ftp://app.example/logo.png',
    ]);

    $response = $controller->register();

    expect($response->getStatusCode())->toBe(400)
        ->and(json_decode((string)$response->getBody(), true))->toBe([
            'error' => 'invalid_client_metadata',
            'error_description' => 'The provided value must be an http(s) URL.',
        ]);
});

it('ignores metadata for non-entity clients', function (): void {
    $controller = oauthRegisterController();

    $metadata = invokeOAuthRegisterMethod($controller, 'persistClientMetadata', [
        new stdClass(),
        ['logo_uri' => 'https://app.example/logo.png'],
    ]);

    expect($metadata)->toBe([]);
});

it('returns empty metadata when the table lacks the columns', function (): void {
    $connection = ConnectionManager::get('test');
    $connection->execute('DROP TABLE IF EXISTS oauth_clients');
    TableRegistry::getTableLocator()->clear();
    createOauthClientsTable('oauth_clients', false);

    $controller = oauthRegisterController();

    $metadata = invokeOAuthRegisterMethod($controller, 'persistClientMetadata', [
        new Client(['name' => 'Plain']),
        ['logo_uri' => 'https://app.example/logo.png'],
    ]);

    expect($metadata)->toBe([]);

    $connection->execute('DROP TABLE oauth_clients');
    TableRegistry::getTableLocator()->clear();
});

it('includes metadata in the 201 response when the table supports the columns', function (): void {
    createOauthClientsTable();
    TableRegistry::getTableLocator()->remove('Tessera.Clients');

    $controller = oauthRegisterRequest([
        'redirect_uris' => ['https://example.com/callback'],
        'logo_uri' => 'https://example.com/logo.png',
        'client_uri' => 'https://example.com/',
    ]);

    $response = $controller->register();

    expect($response->getStatusCode())->toBe(201);

    $body = json_decode((string)$response->getBody(), true);

    expect($body['logo_uri'])->toBe('https://example.com/logo.png')
        ->and($body['client_uri'])->toBe('https://example.com/');
});

it('persists metadata for supporting custom entities', function (): void {
    createOauthClientsTable();
    TableRegistry::getTableLocator()->remove('Tessera.Clients');

    $client = (new ClientRepository())->createAuthorizationCodeGrantClient(
        'Custom',
        ['https://app.example/callback'],
        false,
    );

    Tessera::clientsTable()->setEntityClass('\\' . CustomMetadataClient::class);

    $controller = oauthRegisterController();

    $metadata = invokeOAuthRegisterMethod($controller, 'persistClientMetadata', [$client, [
        'logo_uri' => 'https://APP.EXAMPLE/Logo.PNG',
        'client_uri' => 'https://app.example/',
    ]]);

    expect($metadata)->toMatchArray([
        'logo_uri' => 'https://app.example/logo.png',
        'client_uri' => 'https://app.example/',
    ]);
});
