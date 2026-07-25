<?php
declare(strict_types=1);

use Cake\Event\EventManager;
use Crustum\Mcp\Event\SessionInitializedEvent;
use Crustum\Mcp\Test\Fixtures\ArrayTransport;
use Crustum\Mcp\Test\Fixtures\ExampleServer;

it('dispatches SessionInitialized event on initialize', function (): void {
    $dispatched = [];

    EventManager::instance()->on(
        SessionInitializedEvent::NAME,
        function (SessionInitializedEvent $event) use (&$dispatched): void {
            $dispatched[] = $event;
        },
    );

    $transport = new ArrayTransport();
    $server = new ExampleServer($transport);
    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-03-26',
            'clientInfo' => [
                'name' => 'claude-desktop',
                'title' => 'Claude Desktop',
                'version' => '1.0.0',
            ],
            'capabilities' => [
                'sampling' => [],
            ],
        ],
    ]);

    ($transport->handler)($payload);

    expect($dispatched)->toHaveCount(1);

    $event = $dispatched[0];

    expect($event->getClientInfo()['name'])->toBe('claude-desktop')
        ->and($event->clientTitle())->toBe('Claude Desktop')
        ->and($event->clientVersion())->toBe('1.0.0')
        ->and($event->getProtocolVersion())->toBe('2025-03-26')
        ->and($event->getClientCapabilities())->toEqual(['sampling' => []])
        ->and($event->getSessionId())->not->toBeEmpty();
});

it('dispatches SessionInitialized event with null values when not provided', function (): void {
    $dispatched = [];

    EventManager::instance()->on(
        SessionInitializedEvent::NAME,
        function (SessionInitializedEvent $event) use (&$dispatched): void {
            $dispatched[] = $event;
        },
    );

    $transport = new ArrayTransport();
    $server = new ExampleServer($transport);
    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [],
    ]);

    ($transport->handler)($payload);

    expect($dispatched)->toHaveCount(1);

    $event = $dispatched[0];

    expect($event->getClientInfo())->toBeNull()
        ->and($event->getProtocolVersion())->toBeNull()
        ->and($event->getClientCapabilities())->toBeNull()
        ->and($event->getSessionId())->not->toBeEmpty();
});

it('provides helper methods on SessionInitialized event', function (): void {
    $event = new SessionInitializedEvent(
        new ExampleServer(new ArrayTransport()),
        'test-session-id',
        [
            'name' => 'cursor',
            'title' => 'Cursor',
            'version' => '2.0.0',
        ],
        '2025-03-26',
        ['sampling' => []],
    );

    expect($event->clientName())->toBe('cursor')
        ->and($event->clientTitle())->toBe('Cursor')
        ->and($event->clientVersion())->toBe('2.0.0')
        ->and($event->getSessionId())->toBe('test-session-id')
        ->and($event->getProtocolVersion())->toBe('2025-03-26');
});

it('returns null for helper methods when clientInfo is null', function (): void {
    $event = new SessionInitializedEvent(
        new ExampleServer(new ArrayTransport()),
        'test-session-id',
    );

    expect($event->clientName())->toBeNull()
        ->and($event->clientTitle())->toBeNull()
        ->and($event->clientVersion())->toBeNull();
});

it('returns null for helper methods when fields are missing', function (): void {
    $event = new SessionInitializedEvent(
        new ExampleServer(new ArrayTransport()),
        'test-session-id',
        ['other' => 'data'],
    );

    expect($event->clientName())->toBeNull()
        ->and($event->clientTitle())->toBeNull()
        ->and($event->clientVersion())->toBeNull();
});
