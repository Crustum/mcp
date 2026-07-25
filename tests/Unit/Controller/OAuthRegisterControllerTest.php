<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Crustum\Mcp\Controller\OAuthRegisterController;
use Crustum\Mcp\Server\Registrar;

function oauthRegisterController(): OAuthRegisterController
{
    return new OAuthRegisterController(new ServerRequest([
        'environment' => ['REQUEST_METHOD' => 'POST'],
    ]));
}

function invokeOAuthRegisterMethod(OAuthRegisterController $controller, string $method, array $arguments = []): mixed
{
    $reflection = new ReflectionClass($controller);
    $reflectionMethod = $reflection->getMethod($method);

    return $reflectionMethod->invoke($controller, ...$arguments);
}

beforeEach(function (): void {
    Configure::write('Mcp.redirect_domains', ['*']);
    Configure::write('Mcp.custom_schemes', []);
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
