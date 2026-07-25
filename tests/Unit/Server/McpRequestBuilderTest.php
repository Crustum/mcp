<?php
declare(strict_types=1);

use Cake\Event\EventManager;
use Cake\Http\ServerRequest;
use Crustum\Mcp\Event\McpRequestBuildingEvent;
use Crustum\Mcp\Request;
use Crustum\Mcp\Server\McpRequestBuilder;
use Crustum\Mcp\Transport\JsonRpcRequest;

it('dispatches McpRequestBuildingEvent with server request context', function (): void {
    $principal = new stdClass();
    $principal->id = 7;

    $listener = static function (McpRequestBuildingEvent $event) use ($principal): void {
        if ($event->getServerRequest()?->getAttribute('identity') === $principal) {
            $event->getMcpRequest()->setIdentity($principal);
        }
    };

    EventManager::instance()->on(McpRequestBuildingEvent::NAME, $listener);

    $httpRequest = (new ServerRequest())->withAttribute('identity', $principal);
    $jsonRpcRequest = new JsonRpcRequest('1', 'tools/call', [
        'name' => 'example',
        'arguments' => ['city' => 'Paris'],
    ]);

    McpRequestBuilder::usingHttpRequest($httpRequest);
    $mcpRequest = McpRequestBuilder::build($jsonRpcRequest);

    expect($mcpRequest->getIdentity())->toBe($principal)
        ->and($mcpRequest->get('city'))->toBe('Paris');

    EventManager::instance()->off(McpRequestBuildingEvent::NAME, $listener);
    McpRequestBuilder::reset();
});

it('builds requests without http context for stdio transports', function (): void {
    $jsonRpcRequest = new JsonRpcRequest('1', 'tools/call', [
        'name' => 'example',
        'arguments' => [],
    ]);

    $mcpRequest = McpRequestBuilder::build($jsonRpcRequest);

    expect($mcpRequest->getIdentity())->toBeNull();
});

it('allows listeners to enrich a pre-built request instance', function (): void {
    $request = new Request(['token' => 'abc']);

    $listener = static function (McpRequestBuildingEvent $event): void {
        $event->getMcpRequest()->setIdentity('service-account');
    };

    EventManager::instance()->on(McpRequestBuildingEvent::NAME, $listener);

    $built = McpRequestBuilder::build(
        new JsonRpcRequest('1', 'resources/read', ['uri' => 'file://test']),
        $request,
    );

    expect($built)->toBe($request)
        ->and($built->getIdentity())->toBe('service-account')
        ->and($built->get('token'))->toBe('abc');

    EventManager::instance()->off(McpRequestBuildingEvent::NAME, $listener);
});
