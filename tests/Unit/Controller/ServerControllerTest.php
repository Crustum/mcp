<?php
declare(strict_types=1);

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Mcp\Controller\ServerController;
use Crustum\Mcp\Server\Registrar;
use Crustum\Mcp\Test\Fixtures\ExampleServer;

/**
 * @param array<string, mixed> $options Request options
 * @return \Crustum\Mcp\Controller\ServerController
 */
function mcpServerController(array $options = []): ServerController
{
    $controller = new ServerController(new ServerRequest($options));
    $controller->setResponse(new Response());

    return $controller;
}

it('returns 405 for methodNotAllowed', function (): void {
    $controller = mcpServerController([
        'url' => '/mcp/test',
        'environment' => [
            'REQUEST_METHOD' => 'GET',
        ],
    ]);

    $response = $controller->methodNotAllowed();

    expect($response->getStatusCode())->toBe(405);
    expect($response->getHeaderLine('Allow'))->toBe('POST');
});

it('returns 404 when no MCP server is registered for the route', function (): void {
    $controller = mcpServerController([
        'url' => '/mcp/missing',
        'environment' => [
            'REQUEST_METHOD' => 'POST',
        ],
        'params' => [
            'serverUri' => 'mcp/missing',
        ],
    ]);

    $response = $controller->handle();

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->getStatusCode())->toBe(404);
});

it('handles registered MCP POST requests', function (): void {
    $registrar = Registrar::getInstance();
    $registrar->registerWeb('/mcp/test', ExampleServer::class);

    $controller = mcpServerController([
        'url' => '/mcp/test',
        'environment' => [
            'REQUEST_METHOD' => 'POST',
        ],
        'input' => json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => [],
                'clientInfo' => [
                    'name' => 'test',
                    'version' => '1.0.0',
                ],
            ],
        ], JSON_THROW_ON_ERROR),
        'params' => [
            'serverUri' => 'mcp/test',
        ],
    ]);

    $response = $controller->handle();

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->getStatusCode())->toBeGreaterThanOrEqual(200);
    expect((string)$response->getBody())->not->toBe('');
});
