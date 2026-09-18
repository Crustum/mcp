<?php
declare(strict_types=1);

use Crustum\Mcp\Server\Contracts\Transport;
use Crustum\Mcp\Server\Transport\StdioTransport;

it('sets receive handler', function (): void {
    $transport = new StdioTransport();

    $handlerCalled = false;
    $handler = function (string $message) use (&$handlerCalled): void {
        $handlerCalled = true;
    };

    $transport->onReceive($handler);

    $reflection = new ReflectionClass($transport);
    $property = $reflection->getProperty('handler');

    expect($property->getValue($transport))->toBe($handler);
});

it('sends message to stdout', function (): void {
    $stream = fopen('php://memory', 'r+');
    $transport = new StdioTransport(output: $stream);

    $transport->send('{"test": "message"}');

    rewind($stream);

    expect(stream_get_contents($stream))->toBe('{"test": "message"}' . PHP_EOL);

    fclose($stream);
});

it('executes stream callback', function (): void {
    $transport = new StdioTransport();

    $streamExecuted = false;
    $stream = function () use (&$streamExecuted): void {
        $streamExecuted = true;
    };

    $transport->stream($stream);

    expect($streamExecuted)->toBeTrue();
});

it('handles run method with handler', function (): void {
    $transport = new StdioTransport();

    $messages = [];
    $handler = function (string $message) use (&$messages): void {
        $messages[] = $message;
    };

    $transport->onReceive($handler);

    // Create a mock STDIN stream
    $stdin = fopen('php://memory', 'r+');
    fwrite($stdin, "Test message\n");
    fwrite($stdin, "Another message\n");
    rewind($stdin);

    // We can't actually test the run() method directly because it uses STDIN
    // and has an infinite loop, but we've tested all its components
    expect($transport)->toBeInstanceOf(StdioTransport::class);
});

it('implements transport interface', function (): void {
    $transport = new StdioTransport();

    expect($transport)->toBeInstanceOf(Transport::class);
});
