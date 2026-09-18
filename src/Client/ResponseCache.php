<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client;

use Cake\Cache\Cache;
use Closure;
use Crustum\Mcp\Client\Contracts\Method;
use Crustum\Mcp\Client\Contracts\Transport;
use Crustum\Mcp\Enums\CacheScope;
use Crustum\Mcp\Server;
use function array_diff_key;
use function array_flip;
use function array_intersect_key;
use function hash;
use function in_array;
use function is_array;
use function ksort;
use function min;
use function serialize;

/**
 * Client-side response cache honoring server caching hints.
 */
class ResponseCache
{
    public const MAX_TTL_MS = 86_400_000;

    /**
     * @param string|null $store Cache engine name (null = default)
     * @param string|null $for Authorization context discriminator
     */
    public function __construct(
        public readonly ?string $store = null,
        public readonly ?string $for = null,
    ) {
    }

    /**
     * Remember a method response using server-provided caching hints.
     *
     * @param \Crustum\Mcp\Client\Contracts\Method $method JSON-RPC method
     * @param \Crustum\Mcp\Client\Contracts\Transport $transport Transport instance
     * @param \Closure(): array<string, mixed> $fetch Callback to execute the request
     * @return array<string, mixed>
     */
    public function remember(Method $method, Transport $transport, Closure $fetch): array
    {
        $params = $method->params();

        ksort($params);

        if (!$this->cacheable($method->method(), $params)) {
            return $fetch();
        }

        $recipe = $transport->recipe();
        $storeName = $this->store ?? 'default';
        $shared = $this->key($recipe, $method->method(), $params, CacheScope::Public);
        $private = $this->key($recipe, $method->method(), $params, CacheScope::Private);

        $cached = Cache::read($private, $storeName) ?? Cache::read($shared, $storeName);

        if (is_array($cached)) {
            return $cached;
        }

        $result = $fetch();

        if (($result['resultType'] ?? 'complete') !== 'complete' || !empty($result['nextCursor'])) {
            return $result;
        }

        $ttlMs = (int)($result['ttlMs'] ?? 0);
        $ttlMs = min($ttlMs, self::MAX_TTL_MS);

        if ($ttlMs > 0) {
            $key = ($result['cacheScope'] ?? '') === CacheScope::Public->value ? $shared : $private;

            Cache::write($key, $result, $storeName);
        }

        return $result;
    }

    /**
     * Check whether a method+params combination is cacheable.
     *
     * @param string $method JSON-RPC method name
     * @param array<string, mixed> $params Request parameters
     * @return bool
     */
    protected function cacheable(string $method, array $params): bool
    {
        return in_array($method, Server::CACHEABLE_METHODS, true)
            && !isset($params['cursor'])
            && !isset($params['inputResponses'])
            && !isset($params['requestState']);
    }

    /**
     * Build a cache key for a method call.
     *
     * @param array<string, mixed> $recipe Transport recipe
     * @param string $method JSON-RPC method name
     * @param array<string, mixed> $params Request parameters
     * @param \Crustum\Mcp\Enums\CacheScope $scope Cache scope
     * @return string
     */
    protected function key(array $recipe, string $method, array $params, CacheScope $scope): string
    {
        return implode(':', [
            'mcp',
            $this->hash(array_intersect_key($recipe, array_flip(['driver', 'url', 'command', 'args', 'headers']))),
            $scope === CacheScope::Public
                ? 'public'
                : $this->hash([$this->for, array_diff_key($recipe, array_flip(['timeoutSeconds']))]),
            $method,
            $this->hash($params),
        ]);
    }

    /**
     * Hash a value for use in cache keys.
     *
     * @param mixed $value Value to hash
     * @return string
     */
    protected function hash(mixed $value): string
    {
        return hash('sha256', serialize($value));
    }
}
