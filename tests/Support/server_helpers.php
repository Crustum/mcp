<?php
declare(strict_types=1);

use Crustum\Mcp\Test\Fixtures\ArrayTransport;
use Crustum\Mcp\Test\Fixtures\ExampleServer;

/**
 * Build a JSON-RPC initialize request payload.
 *
 * @return array<string, mixed>
 */
function initializeMessage(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 456,
        'method' => 'initialize',
        'params' => [],
    ];
}

/**
 * Build the expected initialize JSON-RPC response for ExampleServer.
 *
 * @return array<string, mixed>
 */
function expectedInitializeResponse(): array
{
    $server = new ExampleServer(new ArrayTransport());

    [
        $capabilities,
        $name,
        $version,
        $instructions,
    ] = (fn(): array => [
        $this->capabilities,
        $this->name,
        $this->version,
        $this->instructions,
    ])->call($server);

    return [
        'jsonrpc' => '2.0',
        'id' => 456,
        'result' => [
            'protocolVersion' => '2025-11-25',
            'capabilities' => $capabilities,
            'serverInfo' => [
                'name' => $name,
                'version' => $version,
            ],
            'instructions' => $instructions,
        ],
    ];
}

/**
 * Build a JSON-RPC tools/list request payload.
 *
 * @return array<string, mixed>
 */
function listToolsMessage(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ];
}

/**
 * Build the expected tools/list JSON-RPC response for ExampleServer.
 *
 * @return array<string, mixed>
 */
function expectedListToolsResponse(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'tools' => [
                [
                    'name' => 'say-hi-tool',
                    'description' => 'This tool says hello to a person',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'The name of the person to greet',
                            ],
                        ],
                        'required' => ['name'],
                    ],
                    'annotations' => [],
                    'title' => 'Say Hi Tool',
                ],
                [
                    'name' => 'streaming-tool',
                    'description' => 'A tool that streams multiple responses.',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'count' => [
                                'type' => 'integer',
                                'description' => 'Number of messages to stream.',
                            ],
                        ],
                        'required' => ['count'],
                    ],
                    'annotations' => [],
                    'title' => 'Streaming Tool',
                ],
            ],
        ],
    ];
}

/**
 * Build a JSON-RPC tools/call request payload.
 *
 * @return array<string, mixed>
 */
function callToolMessage(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'say-hi-tool',
            'arguments' => [
                'name' => 'John Doe',
            ],
        ],
    ];
}

/**
 * Build the expected tools/call JSON-RPC response for ExampleServer.
 *
 * @return array<string, mixed>
 */
function expectedCallToolResponse(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'content' => [[
                'type' => 'text',
                'text' => 'Hello, John Doe!',
            ]],
            'isError' => false,
        ],
    ];
}

/**
 * Build a JSON-RPC ping request payload.
 *
 * @return array<string, mixed>
 */
function pingMessage(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 789,
        'method' => 'ping',
    ];
}

/**
 * Build the expected ping JSON-RPC response.
 *
 * @return array<string, mixed>
 */
function expectedPingResponse(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 789,
        'result' => [],
    ];
}

/**
 * Build a JSON-RPC streaming tools/call request payload.
 *
 * @param int $count Number of streamed messages
 * @return array<string, mixed>
 */
function callStreamingToolMessage(int $count = 2): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => [
            'name' => 'streaming-tool',
            'arguments' => [
                'count' => $count,
            ],
        ],
    ];
}

/**
 * Build the expected streaming tool JSON-RPC responses.
 *
 * @param int $count Number of streamed messages
 * @return list<array<string, mixed>>
 */
function expectedStreamingToolResponse(int $count = 2): array
{
    $messages = [];

    for ($i = 1; $i <= $count; $i++) {
        $messages[] = [
            'jsonrpc' => '2.0',
            'method' => 'stream/progress',
            'params' => ['progress' => $i / $count * 100, 'message' => "Processing item {$i} of {$count}"],
        ];
    }

    $messages[] = [
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'content' => [['type' => 'text', 'text' => "Finished streaming {$count} messages."]],
            'isError' => false,
        ],
    ];

    return $messages;
}
