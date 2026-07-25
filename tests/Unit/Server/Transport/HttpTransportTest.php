<?php
declare(strict_types=1);

use Cake\Http\ServerRequest;
use Crustum\Mcp\Server\Transport\HttpTransport;

it('includes MCP-Session-Id from send() on the JSON response', function (): void {
    $transport = new HttpTransport(new ServerRequest(), 'transport-session');
    $transport->onReceive(static function () use ($transport): void {
        $transport->send('{"jsonrpc":"2.0","id":1,"result":{}}', 'init-session');
    });

    $response = $transport->run();

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getHeaderLine('MCP-Session-Id'))->toBe('init-session');
});

it('reads JSON-RPC from parsed body when the PSR stream was already consumed', function (): void {
    $payload = [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => ['protocolVersion' => '2025-03-26'],
    ];
    $request = (new ServerRequest(['input' => '']))
        ->withParsedBody($payload);

    $seen = null;
    $transport = new HttpTransport($request, 'transport-session');
    $transport->onReceive(static function (string $raw) use (&$seen, $transport): void {
        $seen = $raw;
        $transport->send('{"jsonrpc":"2.0","id":1,"result":{}}', 'init-session');
    });

    $transport->run();

    expect($seen)->toBe((string)json_encode($payload));
});
