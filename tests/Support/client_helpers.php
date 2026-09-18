<?php
declare(strict_types=1);

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\Session;
use Crustum\Mcp\Client\ClientManager;
use Crustum\Mcp\Test\Support\HttpFakeSequence;

/**
 * Write configuration values using dot notation.
 *
 * @param array<string, mixed> $values Configuration values
 * @return void
 */
function config(array $values): void
{
    foreach ($values as $key => $value) {
        $normalized = match ($key) {
            'app.name' => 'App.name',
            default => $key,
        };

        Configure::write($normalized, $value);
    }
}

/**
 * Resolve a service from the test container.
 *
 * @template T
 * @param class-string<T> $class
 * @return T
 */
function app(string $class): mixed
{
    if ($class === ClientManager::class) {
        return ClientManager::getInstance();
    }

    throw new InvalidArgumentException("Unknown test container binding [{$class}].");
}

/**
 * Shared HTTP session for OAuth client tests.
 *
 * @return \Cake\Http\Session
 */
function mcpSession(): Session
{
    if (!isset($GLOBALS['mcp_test_session']) || !$GLOBALS['mcp_test_session'] instanceof Session) {
        $GLOBALS['mcp_test_session'] = new Session([
            'defaults' => 'php',
            'cookie' => 'mcp_test_session',
        ]);
    }

    return $GLOBALS['mcp_test_session'];
}

/**
 * Reset the shared OAuth test session.
 *
 * @return void
 */
function resetMcpSession(): void
{
    if (isset($GLOBALS['mcp_test_session']) && $GLOBALS['mcp_test_session'] instanceof Session) {
        $GLOBALS['mcp_test_session']->destroy();
    }

    unset($GLOBALS['mcp_test_session']);
}

/**
 * Extract collection keys for assertions.
 *
 * @param \Cake\Collection\Collection $collection Collection instance
 * @return array<int, string|int>
 */
function collectionKeys(Collection $collection): array
{
    return array_keys($collection->toArray());
}

/**
 * Read a keyed collection entry.
 *
 * @param \Cake\Collection\Collection $collection Collection instance
 * @param string|int $key Collection key
 * @return mixed
 */
function collectionGet(Collection $collection, string|int $key): mixed
{
    return $collection->toArray()[$key];
}

/**
 * Build a legacy initialize handshake response.
 *
 * @param int $id Response identifier
 * @param string $version Negotiated protocol version
 * @return string
 */
function initializeResponse(int $id = 1, string $version = '2025-11-25'): string
{
    return json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'result' => [
            'protocolVersion' => $version,
            'capabilities' => new stdClass(),
            'serverInfo' => ['name' => 'Test Server', 'version' => '1.0.0'],
        ],
    ]);
}

/**
 * Build a modern server/discover response.
 *
 * @param int $id Response identifier
 * @param array<int, string> $supportedVersions Supported protocol versions
 * @return string
 */
function discoverResponse(int $id = 1, array $supportedVersions = ['2026-07-28']): string
{
    return json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'result' => [
            'resultType' => 'complete',
            'supportedVersions' => $supportedVersions,
            'capabilities' => ['tools' => ['listChanged' => false]],
            'instructions' => 'Be nice.',
            '_meta' => [
                'io.modelcontextprotocol/serverInfo' => ['name' => 'Test Server', 'version' => '1.0.0'],
            ],
        ],
    ]);
}

/**
 * Begin a legacy HTTP endpoint that rejects the modern probe.
 *
 * @return \Crustum\Mcp\Test\Support\HttpFakeSequence
 */
function legacyEndpoint(): HttpFakeSequence
{
    return Http::fakeSequence()->push('Bad Request', 400);
}

/**
 * Build a JSON-RPC method-not-found error response.
 *
 * @param int $id Response identifier
 * @return string
 */
function methodNotFoundResponse(int $id = 1): string
{
    return json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'error' => ['code' => -32601, 'message' => 'Method not found.'],
    ]);
}

/**
 * Build a JSON-RPC tools/call response.
 *
 * @param int $id Response identifier
 * @param string $text Response text
 * @return string
 */
function toolCallResponse(int $id, string $text): string
{
    return (string)json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'result' => [
            'resultType' => 'complete',
            'content' => [['type' => 'text', 'text' => $text]],
            'isError' => false,
        ],
    ]);
}

/**
 * @param int $id
 * @return string
 */
function pingResponse(int $id): string
{
    return json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'result' => new stdClass(),
    ]);
}

/**
 * @param array<int, array<string, mixed>> $frames
 * @return string
 */
function sseStream(array $frames): string
{
    $chunks = [];

    foreach ($frames as $frame) {
        $chunks[] = 'data: ' . json_encode($frame) . "\n\n";
    }

    return implode('', $chunks);
}

/**
 * @param \Cake\Http\Response $response
 * @return string
 */
function responseLocation(Response $response): string
{
    return $response->getHeaderLine('Location');
}
