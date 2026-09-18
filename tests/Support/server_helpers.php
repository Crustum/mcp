<?php
declare(strict_types=1);

use Crustum\Mcp\Test\Fixtures\ArrayTransport;
use Crustum\Mcp\Test\Fixtures\ExampleServer;

/**
 * Build the protocol metadata carried by every request.
 *
 * @return array<string, mixed>
 */
function protocolMeta(): array
{
    return [
        'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
        'io.modelcontextprotocol/clientCapabilities' => [],
    ];
}

/**
 * Build the mirrored HTTP headers for a JSON-RPC message.
 *
 * @param array<string, mixed> $message JSON-RPC message
 * @return array<string, string>
 */
function mcpHeaders(array $message): array
{
    $params = $message['params'] ?? [];

    $name = match ($message['method']) {
        'tools/call', 'prompts/get' => $params['name'] ?? null,
        'resources/read' => $params['uri'] ?? null,
        default => null,
    };

    return array_filter([
        'MCP-Protocol-Version' => '2026-07-28',
        'Mcp-Method' => $message['method'],
        'Mcp-Name' => $name,
    ], fn(?string $value): bool => $value !== null);
}

/**
 * Build a JSON-RPC server/discover request payload.
 *
 * @return array<string, mixed>
 */
function discoverMessage(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 456,
        'method' => 'server/discover',
        'params' => [
            '_meta' => protocolMeta(),
        ],
    ];
}

/**
 * Build the expected server/discover JSON-RPC response for ExampleServer.
 *
 * @return array<string, mixed>
 */
function expectedDiscoverResponse(): array
{
    $server = new ExampleServer(new ArrayTransport());

    [
        $capabilities,
        $instructions,
    ] = (fn(): array => [
        $this->capabilities,
        $this->instructions,
    ])->call($server);

    return [
        'jsonrpc' => '2.0',
        'id' => 456,
        'result' => [
            'resultType' => 'complete',
            'ttlMs' => 0,
            'cacheScope' => 'private',
            'supportedVersions' => ['2026-07-28'],
            'capabilities' => $capabilities,
            'instructions' => $instructions,
            '_meta' => [
                'io.modelcontextprotocol/serverInfo' => [
                    'name' => 'CakePHP MCP Server',
                    'version' => '0.0.1',
                ],
            ],
        ],
    ];
}

/**
 * Build a JSON-RPC resources/read request payload.
 *
 * @param string $uri Resource URI
 * @return array<string, mixed>
 */
function readResourceMessage(string $uri = 'file://resources/last-log-line-resource'): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 123,
        'method' => 'resources/read',
        'params' => [
            'uri' => $uri,
            '_meta' => protocolMeta(),
        ],
    ];
}

/**
 * Build a JSON-RPC resources/list request payload.
 *
 * @return array<string, mixed>
 */
function listResourcesMessage(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/list',
        'params' => [
            '_meta' => protocolMeta(),
        ],
    ];
}

/**
 * Build the expected resources/list JSON-RPC response for ExampleServer.
 *
 * @return array<string, mixed>
 */
function expectedListResourcesResponse(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'resultType' => 'complete',
            'ttlMs' => 0,
            'cacheScope' => 'private',
            'resources' => [
                [
                    'name' => 'last-log-line-resource',
                    'title' => 'Last Log Line Resource',
                    'description' => 'The last line of the log file',
                    'uri' => 'file://resources/last-log-line-resource',
                    'mimeType' => 'text/plain',
                ],
                [
                    'name' => 'daily-plan-resource',
                    'title' => 'Daily Plan Resource',
                    'description' => 'The plan for the day',
                    'uri' => 'file://resources/daily-plan.md',
                    'mimeType' => 'text/markdown',
                ],
                [
                    'name' => 'recent-meeting-recording-resource',
                    'title' => 'Recent Meeting Recording Resource',
                    'description' => 'The most recent meeting recording',
                    'uri' => 'file://resources/recent-meeting-recording.mp4',
                    'mimeType' => 'video/mp4',
                ],
            ],
            '_meta' => [
                'io.modelcontextprotocol/serverInfo' => [
                    'name' => 'CakePHP MCP Server',
                    'version' => '0.0.1',
                ],
            ],
        ],
    ];
}

/**
 * Build the expected resources/read JSON-RPC response for ExampleServer.
 *
 * @return array<string, mixed>
 */
function expectedReadResourceResponse(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 123,
        'result' => [
            'resultType' => 'complete',
            'ttlMs' => 0,
            'cacheScope' => 'private',
            'contents' => [[
                'text' => '2025-07-02 12:00:00 Error: Something went wrong.',
                'uri' => 'file://resources/last-log-line-resource',
                'mimeType' => 'text/plain',
            ]],
            '_meta' => [
                'io.modelcontextprotocol/serverInfo' => [
                    'name' => 'CakePHP MCP Server',
                    'version' => '0.0.1',
                ],
            ],
        ],
    ];
}

/**
 * Parse JSON-RPC messages from a server-sent events stream body.
 *
 * @param string $content Raw SSE stream content
 * @return list<array<string, mixed>>
 */
function parseSseJsonRpcMessages(string $content): array
{
    $messages = [];

    foreach (explode("\n\n", trim($content)) as $event) {
        $event = trim($event);

        if ($event === '') {
            continue;
        }

        $messages[] = json_decode(trim(substr($event, strlen('data: '))), true);
    }

    return $messages;
}

/**
 * Parse JSON-RPC messages from stdio process output.
 *
 * @param string $output Raw stdout output
 * @return list<array<string, mixed>>
 */
function parseStdoutJsonRpcMessages(string $output): array
{
    $jsonMessages = array_filter(explode("\n", trim($output)));

    $messages = [];

    foreach ($jsonMessages as $jsonMessage) {
        if (empty($jsonMessage)) {
            continue;
        }

        $messages[] = json_decode($jsonMessage, true);
    }

    return $messages;
}

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
        'params' => [
            '_meta' => protocolMeta(),
        ],
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
            'resultType' => 'complete',
            'ttlMs' => 0,
            'cacheScope' => 'private',
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
            '_meta' => [
                'io.modelcontextprotocol/serverInfo' => [
                    'name' => 'CakePHP MCP Server',
                    'version' => '0.0.1',
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
            '_meta' => protocolMeta(),
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
            'resultType' => 'complete',
            'content' => [[
                'type' => 'text',
                'text' => 'Hello, John Doe!',
            ]],
            'isError' => false,
            '_meta' => [
                'io.modelcontextprotocol/serverInfo' => [
                    'name' => 'CakePHP MCP Server',
                    'version' => '0.0.1',
                ],
            ],
        ],
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
            '_meta' => protocolMeta(),
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
            'resultType' => 'complete',
            'content' => [['type' => 'text', 'text' => "Finished streaming {$count} messages."]],
            'isError' => false,
            '_meta' => [
                'io.modelcontextprotocol/serverInfo' => [
                    'name' => 'CakePHP MCP Server',
                    'version' => '0.0.1',
                ],
            ],
        ],
    ];

    return $messages;
}
