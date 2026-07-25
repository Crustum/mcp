<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Transport;

use Cake\Utility\Hash;
use Crustum\Mcp\Client\Contracts\Transport;
use Crustum\Mcp\Exception\ClientException;

/**
 * Factory for rebuilding MCP client transports from recipes.
 */
class TransportFactory
{
    /**
     * Rebuild a transport from a serialized recipe.
     *
     * @param array<string, mixed> $recipe Transport recipe
     * @return \Crustum\Mcp\Client\Contracts\Transport
     */
    public static function fromRecipe(array $recipe): Transport
    {
        return match (Hash::get($recipe, 'driver')) {
            'stdio' => self::stdio($recipe),
            'http' => self::http($recipe),
            default => throw new ClientException('Unable to rebuild transport from an unknown recipe.'),
        };
    }

    /**
     * Rebuild a stdio transport from a recipe.
     *
     * @param array<string, mixed> $recipe Transport recipe
     * @return \Crustum\Mcp\Client\Transport\StdioTransport
     */
    protected static function stdio(array $recipe): StdioTransport
    {
        $command = Hash::get($recipe, 'command');
        $args = Hash::get($recipe, 'args', []);

        if (!is_string($command) || !is_array($args)) {
            throw new ClientException('Invalid stdio transport recipe.');
        }

        $transport = new StdioTransport($command, array_values($args));

        self::applyTimeout($transport, $recipe);

        return $transport;
    }

    /**
     * Rebuild an HTTP transport from a recipe.
     *
     * @param array<string, mixed> $recipe Transport recipe
     * @return \Crustum\Mcp\Client\Transport\HttpTransport
     */
    protected static function http(array $recipe): HttpTransport
    {
        $url = Hash::get($recipe, 'url');

        if (!is_string($url)) {
            throw new ClientException('Invalid http transport recipe.');
        }

        $transport = new HttpTransport($url);

        $token = Hash::get($recipe, 'token');

        if (is_string($token)) {
            $transport->withToken($token);
        }

        $headers = Hash::get($recipe, 'headers');

        if (is_array($headers)) {
            /** @var array<string, string> $headers */
            $transport->withHeaders($headers);
        }

        self::applyTimeout($transport, $recipe);

        return $transport;
    }

    /**
     * Apply a timeout value from a recipe when present.
     *
     * @param \Crustum\Mcp\Client\Contracts\Transport $transport Transport instance
     * @param array<string, mixed> $recipe Transport recipe
     * @return void
     */
    protected static function applyTimeout(Transport $transport, array $recipe): void
    {
        $timeout = Hash::get($recipe, 'timeoutSeconds');

        if (is_int($timeout) || is_float($timeout)) {
            $transport->setTimeoutSeconds((float)$timeout);
        }
    }
}
