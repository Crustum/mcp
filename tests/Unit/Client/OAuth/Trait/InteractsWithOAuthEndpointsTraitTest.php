<?php
declare(strict_types=1);

use Crustum\Mcp\Client\OAuth\AuthServerDiscovery;
use Crustum\Mcp\Client\OAuth\DynamicClientRegistration;
use Crustum\Mcp\Test\Support\HttpFake;

it('limits discovery requests to http and https with a capped response size', function (): void {
    if (!defined('CURLOPT_PROTOCOLS')) {
        $this->markTestSkipped('curl extension is not loaded.');
    }

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

    new AuthServerDiscovery()->discover('https://mcp.test/mcp');

    expect(HttpFake::$recorded)->not->toBe([]);

    foreach (HttpFake::$recorded as $recorded) {
        expect($recorded->options['curl'] ?? null)->toMatchArray([
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_MAXFILESIZE => 1_048_576,
        ]);
    }
});

it('limits registration requests to http and https', function (): void {
    if (!defined('CURLOPT_PROTOCOLS')) {
        $this->markTestSkipped('curl extension is not loaded.');
    }

    Http::fake(['https://auth.test/register' => Http::response(['client_id' => 'client-1'])]);

    new DynamicClientRegistration()->register('https://auth.test/register', 'https://app.test/callback');

    $recorded = HttpFake::$recorded;

    expect($recorded)->toHaveCount(1)
        ->and($recorded[0]->options['curl'][CURLOPT_PROTOCOLS] ?? null)->toBe(CURLPROTO_HTTP | CURLPROTO_HTTPS);
});
