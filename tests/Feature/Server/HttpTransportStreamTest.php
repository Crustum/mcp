<?php
declare(strict_types=1);

use Cake\Http\ServerRequest;
use Crustum\Mcp\Server\Transport\HttpTransport;

/**
 * Capture CallbackStream body output.
 *
 * @param \Cake\Http\Response $response Streaming response
 * @return string
 */
function streamedTransportContent($response): string
{
    return $response->getBody()->getContents();
}

it('streams iterable responses returned from the stream callback', function (): void {
    $transport = new HttpTransport(new ServerRequest(), 'test-session');

    $transport->stream(fn(): iterable => [
        '{"jsonrpc":"2.0","id":1,"result":[]}',
        '{"jsonrpc":"2.0","id":2,"result":[]}',
    ]);

    $content = streamedTransportContent($transport->run());

    expect($content)->toContain('data: {"jsonrpc":"2.0","id":1,"result":[]}')
        ->and($content)->toContain('data: {"jsonrpc":"2.0","id":2,"result":[]}');
});

it('streams generator responses returned from the stream callback', function (): void {
    $transport = new HttpTransport(new ServerRequest(), 'test-session');

    $transport->stream(function (): Generator {
        yield '{"jsonrpc":"2.0","id":3,"result":[]}';
        yield '{"jsonrpc":"2.0","id":4,"result":[]}';
    });

    $content = streamedTransportContent($transport->run());

    expect($content)->toContain('data: {"jsonrpc":"2.0","id":3,"result":[]}')
        ->and($content)->toContain('data: {"jsonrpc":"2.0","id":4,"result":[]}');
});

it('does not double emit when stream callback echoes directly', function (): void {
    $transport = new HttpTransport(new ServerRequest(), 'test-session');

    $transport->stream(function (): void {
        echo 'data: {"jsonrpc":"2.0","id":99,"result":[]}';
        echo "\n\n";
    });

    $content = streamedTransportContent($transport->run());

    expect($content)->toBe("data: {\"jsonrpc\":\"2.0\",\"id\":99,\"result\":[]}\n\n");
});
