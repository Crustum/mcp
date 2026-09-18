<?php
declare(strict_types=1);

use Cake\Cache\Cache;
use Crustum\Mcp\Client;
use Crustum\Mcp\Client\ResponseCache;
use Crustum\Mcp\Test\Fixtures\Client\FakeTransport;

beforeEach(function (): void {
    $uniq = uniqid();
    Cache::drop('default');
    Cache::setConfig('default', [
        'className' => 'File',
        'path' => sys_get_temp_dir(),
        'prefix' => 'mcp_cache_default_' . $uniq,
        'serialize' => true,
    ]);
    Cache::drop('array');
    Cache::setConfig('array', [
        'className' => 'File',
        'path' => sys_get_temp_dir(),
        'prefix' => 'mcp_cache_array_' . $uniq,
        'serialize' => true,
    ]);
});

function resourceResponse(string $text, string $resultType = 'complete'): string
{
    return json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'resultType' => $resultType,
            'ttlMs' => 300000,
            'cacheScope' => 'private',
            'contents' => [['uri' => 'file://a', 'mimeType' => 'text/plain', 'text' => $text]],
        ],
    ]);
}

function toolsPage(string $tool, ?string $nextCursor = null): string
{
    return json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => array_filter([
            'resultType' => 'complete',
            'ttlMs' => 300000,
            'cacheScope' => 'private',
            'tools' => [['name' => $tool]],
            'nextCursor' => $nextCursor,
        ]),
    ]);
}

function cacheableTransport(int $ttlMs = 300000, string $scope = 'private'): FakeTransport
{
    $transport = new FakeTransport();
    $transport->negotiates = true;
    $transport->responses[] = discoverResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'resultType' => 'complete',
            'ttlMs' => $ttlMs,
            'cacheScope' => $scope,
            'tools' => [['name' => 'add']],
        ],
    ]);

    return $transport;
}

it('serves a fresh result without touching the transport again', function (): void {
    $transport = cacheableTransport();
    $client = (new Client($transport))->withCache();

    expect($client->tools()->keys()->toArray())->toBe(['add']);

    $sent = count($transport->sent);

    expect($client->tools()->keys()->toArray())->toBe(['add'])
        ->and($transport->sent)->toHaveCount($sent);
});

it('never caches a result the server marked immediately stale', function (): void {
    $transport = cacheableTransport(ttlMs: 0);
    $client = (new Client($transport))->withCache();

    $client->tools();

    $sent = count($transport->sent);

    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => ['tools' => [['name' => 'add']]],
    ]);

    $client->tools();

    expect(count($transport->sent))->toBeGreaterThan($sent);
});

it('leaves the transport uncached until asked', function (): void {
    $transport = cacheableTransport();
    $client = new Client($transport);

    $client->tools();

    $sent = count($transport->sent);

    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => ['tools' => [['name' => 'add']]],
    ]);

    $client->tools();

    expect(count($transport->sent))->toBeGreaterThan($sent);
});

it('never caches an interim result', function (): void {
    $transport = new FakeTransport();
    $transport->negotiates = true;
    $transport->responses[] = discoverResponse();
    $transport->responses[] = resourceResponse('alpha', resultType: 'input_required');
    $transport->responses[] = resourceResponse('bravo');

    $client = (new Client($transport))->withCache();

    expect($client->readResource('file://a')->content())->toBe('alpha')
        ->and($client->readResource('file://a')->content())->toBe('bravo');
});

it('never caches a page of a paginated list', function (): void {
    $transport = new FakeTransport();
    $transport->negotiates = true;
    $transport->responses[] = discoverResponse();
    $transport->responses[] = toolsPage('add', nextCursor: 'page-2');
    $transport->responses[] = toolsPage('subtract');
    $transport->responses[] = toolsPage('add', nextCursor: 'page-2');
    $transport->responses[] = toolsPage('subtract');

    $client = (new Client($transport))->withCache();

    expect($client->tools()->keys()->toArray())->toBe(['add', 'subtract']);

    $sent = count($transport->sent);

    expect($client->tools()->keys()->toArray())->toBe(['add', 'subtract'])
        ->and(count($transport->sent))->toBeGreaterThan($sent);
});

it('stops caching once the cache is turned back off', function (): void {
    $transport = cacheableTransport();
    $client = (new Client($transport))->withCache()->withoutCache();

    $client->tools();

    $sent = count($transport->sent);

    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => ['tools' => [['name' => 'add']]],
    ]);

    $client->tools();

    expect(count($transport->sent))->toBeGreaterThan($sent);
});

it('includes cache in serialized output', function (): void {
    $client = (new Client(new FakeTransport()))->withCache(store: 'array', for: 'tenant-a');

    $data = $client->__serialize();

    expect($data)
        ->toHaveKey('cache')
        ->and($data['cache'])
        ->toBeInstanceOf(ResponseCache::class);

    $cache = $data['cache'];

    expect($cache->store)->toBe('array')
        ->and($cache->for)->toBe('tenant-a');
});
