<?php
declare(strict_types=1);

use Cake\Log\Log;
use Crustum\Mcp\Client;
use Crustum\Mcp\Client\Primitives\Tool;
use Crustum\Mcp\Client\Schema\ToolResult;
use Crustum\Mcp\Enums\ProtocolVersion;
use Crustum\Mcp\Exception\JsonRpcException;
use Crustum\Mcp\Test\Fixtures\Client\FakeTransport;

/**
 * Build a tools/list response.
 *
 * @param int $id Response identifier
 * @param array<int, array<string, mixed>> $tools Tool payloads
 * @return string
 */
function toolsListResponse(int $id, array $tools): string
{
    return (string)json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'result' => ['resultType' => 'complete', 'tools' => $tools],
    ]);
}

/**
 * Build a tool payload with an annotated header parameter.
 *
 * @param string $header Header name token
 * @return array<string, mixed>
 */
function annotatedTool(string $header = 'Region'): array
{
    return [
        'name' => 'execute_sql',
        'description' => 'Runs SQL',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'region' => ['type' => 'string', 'x-mcp-header' => $header],
                'query' => ['type' => 'string'],
            ],
        ],
    ];
}

/**
 * List the request methods sent, excluding the negotiation frames.
 *
 * @param \Crustum\Mcp\Test\Fixtures\Client\FakeTransport $transport Transport under test
 * @return array<int, mixed>
 */
function sentMethods(FakeTransport $transport): array
{
    return array_values(array_filter(
        array_map(
            static fn(string $frame): mixed => json_decode($frame, true)['method'] ?? null,
            $transport->sent,
        ),
        static fn(mixed $method): bool => !in_array($method, [null, 'server/discover', 'initialize'], true),
    ));
}

/**
 * Build a header-mismatch JSON-RPC error response.
 *
 * @param int $id Response identifier
 * @return string
 */
function headerMismatchResponse(int $id): string
{
    return (string)json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'error' => ['code' => -32020, 'message' => 'Header mismatch: the [Mcp-Param-Region] header is required.'],
    ]);
}

it('mirrors annotated parameters into headers on a tool call', function (): void {
    $transport = new FakeTransport();
    $transport->negotiates = true;
    $transport->responses[] = discoverResponse();
    $transport->responses[] = toolsListResponse(2, [annotatedTool()]);
    $transport->responses[] = toolCallResponse(3, 'done');

    $client = (new Client($transport))->withProtocolVersion(ProtocolVersion::LATEST);
    $tool = collectionGet($client->tools(), 'execute_sql');

    $result = $client->callTool($tool, ['region' => 'us-west1', 'query' => 'select 1']);

    expect($tool)->toBeInstanceOf(Tool::class);
    expect($tool->mirroredParameters())->toHaveCount(1);
    expect($result->text())->toBe('done');
    expect($transport->sentHeaders[2])->toBe([
        'Mcp-Method' => 'tools/call',
        'Mcp-Name' => 'execute_sql',
        'Mcp-Param-Region' => 'us-west1',
    ]);
});

it('mirrors parameters when calling through the tool primitive', function (): void {
    $transport = new FakeTransport();
    $transport->negotiates = true;
    $transport->responses[] = discoverResponse();
    $transport->responses[] = toolsListResponse(2, [annotatedTool()]);
    $transport->responses[] = toolCallResponse(3, 'done');

    $client = (new Client($transport))->withProtocolVersion(ProtocolVersion::LATEST);

    collectionGet($client->tools(), 'execute_sql')->call(['region' => 'us-west1', 'query' => 'select 1']);

    expect($transport->sentHeaders[2])->toBe([
        'Mcp-Method' => 'tools/call',
        'Mcp-Name' => 'execute_sql',
        'Mcp-Param-Region' => 'us-west1',
    ]);
});

it('sends only the standard headers when calling a tool by name', function (): void {
    $transport = new FakeTransport();
    $transport->negotiates = true;
    $transport->responses[] = discoverResponse();
    $transport->responses[] = toolCallResponse(2, 'done');

    (new Client($transport))->withProtocolVersion(ProtocolVersion::LATEST)->callTool('execute_sql', ['region' => 'us-west1']);

    expect($transport->sentHeaders[1])
        ->toBe([
            'Mcp-Method' => 'tools/call',
            'Mcp-Name' => 'execute_sql',
        ])
        ->not->toHaveKey('Mcp-Param-Region');
});

it('sends no mirrored headers on the legacy protocol era', function (): void {
    $transport = new FakeTransport();
    $transport->responses[] = initializeResponse(1, ProtocolVersion::V2025_11_25->value);
    $transport->responses[] = toolsListResponse(2, [annotatedTool()]);
    $transport->responses[] = toolCallResponse(3, 'done');

    $client = (new Client($transport))->withProtocolVersion(ProtocolVersion::V2025_11_25);

    collectionGet($client->tools(), 'execute_sql')->call(['region' => 'us-west1']);

    expect($transport->sentHeaders[2])->toBeEmpty();
});

it('excludes a tool whose annotation is invalid from the tool list', function (): void {
    Log::reset();
    Log::setConfig('mcp-test', [
        'className' => 'Array',
        'levels' => ['warning'],
    ]);

    try {
        $transport = new FakeTransport();
        $transport->negotiates = true;
        $transport->responses[] = discoverResponse();
        $transport->responses[] = toolsListResponse(2, [
            annotatedTool('Bad Header'),
            ['name' => 'plain', 'description' => 'Fine', 'inputSchema' => ['type' => 'object']],
        ]);

        $tools = (new Client($transport))->withProtocolVersion(ProtocolVersion::LATEST)->tools();

        expect($tools)
            ->toHaveCount(1)
            ->toHaveKey('plain')
            ->not->toHaveKey('execute_sql');

        $logged = implode(' ', Log::engine('mcp-test')->read());

        expect($logged)
            ->toContain('execute_sql')
            ->toContain('not a valid header name token');
    } finally {
        Log::reset();
    }
});

it('re-lists and retries once when the server reports a header mismatch', function (): void {
    $transport = new FakeTransport();
    $transport->negotiates = true;
    $transport->responses[] = discoverResponse();
    $transport->responses[] = headerMismatchResponse(2);
    $transport->responses[] = toolsListResponse(3, [annotatedTool()]);
    $transport->responses[] = toolCallResponse(4, 'done');

    $result = (new Client($transport))
        ->withProtocolVersion(ProtocolVersion::LATEST)
        ->callTool('execute_sql', ['region' => 'us-west1', 'query' => 'select 1']);

    expect($result->text())->toBe('done')
        ->and($transport->sent)->toHaveCount(4)
        ->and($transport->sentHeaders[1])->not->toHaveKey('Mcp-Param-Region')
        ->and($transport->sentHeaders[3])->toHaveKey('Mcp-Param-Region', 'us-west1')
        ->and(sentMethods($transport))->toBe(['tools/call', 'tools/list', 'tools/call']);
});

it('does not retry when the refreshed definition mirrors nothing new', function (): void {
    $transport = new FakeTransport();
    $transport->negotiates = true;
    $transport->responses[] = discoverResponse();
    $transport->responses[] = headerMismatchResponse(2);
    $transport->responses[] = toolsListResponse(3, [
        ['name' => 'execute_sql', 'description' => 'Runs SQL', 'inputSchema' => ['type' => 'object']],
    ]);

    $client = (new Client($transport))->withProtocolVersion(ProtocolVersion::LATEST);

    expect(fn(): ToolResult => $client->callTool('execute_sql', ['region' => 'us-west1']))
        ->toThrow(
            fn(JsonRpcException $jsonRpcException): mixed => expect($jsonRpcException)
                ->getCode()->toBe(-32020)
                ->getMessage()->toContain('Header mismatch'),
        )
        ->and($transport->sent)->toHaveCount(3);
});

it('does not retry a tool call that fails for any other reason', function (): void {
    $transport = new FakeTransport();
    $transport->negotiates = true;
    $transport->responses[] = discoverResponse();
    $transport->responses[] = methodNotFoundResponse(2);

    $client = (new Client($transport))->withProtocolVersion(ProtocolVersion::LATEST);

    expect(fn(): ToolResult => $client->callTool('execute_sql', ['region' => 'us-west1']))
        ->toThrow(JsonRpcException::class, 'Method not found')
        ->and($transport->sent)->toHaveCount(2)
        ->and(sentMethods($transport))->toBe(['tools/call']);
});
