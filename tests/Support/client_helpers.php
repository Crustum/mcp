<?php
declare(strict_types=1);

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\Session;
use Crustum\Mcp\Client\ClientManager;

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
 * @return string
 */
function initializeResponse(): string
{
    return json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'protocolVersion' => '2025-11-25',
            'capabilities' => new stdClass(),
            'serverInfo' => ['name' => 'Test Server', 'version' => '1.0.0'],
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
