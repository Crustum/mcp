<?php
declare(strict_types=1);

use Cake\Http\ServerRequest;
use Crustum\Mcp\Server\Transport\HttpTransport;

it('returns 202 when there is no reply', function (): void {
    $transport = new HttpTransport(new ServerRequest());

    $response = $transport->run();

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(202);
});

it('returns 200 for a successful reply', function (): void {
    $transport = new HttpTransport(new ServerRequest());
    $transport->onReceive(static function () use ($transport): void {
        $transport->send('{"jsonrpc":"2.0","id":1,"result":{}}');
    });

    $response = $transport->run();

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(200);
});

it('maps a method not found error to 404', function (): void {
    $transport = new HttpTransport(new ServerRequest());
    $transport->onReceive(static function () use ($transport): void {
        $transport->send('{"jsonrpc":"2.0","id":1,"error":{"code":-32601,"message":"not found"}}');
    });

    $response = $transport->run();

    expect($response->getStatusCode())->toBe(404);
});

it('maps an internal error to 500', function (): void {
    $transport = new HttpTransport(new ServerRequest());
    $transport->onReceive(static function () use ($transport): void {
        $transport->send('{"jsonrpc":"2.0","id":1,"error":{"code":-32603,"message":"internal"}}');
    });

    $response = $transport->run();

    expect($response->getStatusCode())->toBe(500);
});

it('maps other protocol errors to 400', function (): void {
    $transport = new HttpTransport(new ServerRequest());
    $transport->onReceive(static function () use ($transport): void {
        $transport->send('{"jsonrpc":"2.0","id":1,"error":{"code":-32602,"message":"bad params"}}');
    });

    $response = $transport->run();

    expect($response->getStatusCode())->toBe(400);
});

it('reads JSON-RPC from parsed body when the PSR stream was already consumed', function (): void {
    $payload = [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => ['_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28']],
    ];
    $request = (new ServerRequest(['input' => '']))
        ->withParsedBody($payload);

    $seen = null;
    $transport = new HttpTransport($request);
    $transport->onReceive(static function (string $raw) use (&$seen, $transport): void {
        $seen = $raw;
        $transport->send('{"jsonrpc":"2.0","id":1,"result":{}}');
    });

    $transport->run();

    expect($seen)->toBe((string)json_encode($payload));
});
