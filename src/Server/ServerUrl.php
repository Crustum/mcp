<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server;

use Cake\Core\Configure;
use Cake\Routing\Router;

/**
 * Resolves the current MCP server URL for UI metadata.
 */
final class ServerUrl
{
    /**
     * @var (callable(): string)|null
     */
    protected static $resolver;

    /**
     * Register a custom URL resolver.
     *
     * @param (callable(): string)|null $resolver URL resolver callback
     * @return void
     */
    public static function resolveUsing(?callable $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * Get the current application base URL.
     *
     * @return string
     */
    public static function current(): string
    {
        if (self::$resolver !== null) {
            return self::normalizeBaseUrl((self::$resolver)());
        }

        $candidates = [
            Configure::read('Mcp.base_url'),
            Configure::read('App.fullBaseUrl'),
            Configure::read('App.url'),
            env('APP_FULL_BASE_URL'),
            env('APP_URL'),
        ];

        foreach ($candidates as $candidate) {
            $normalized = self::normalizeBaseUrl($candidate);

            if ($normalized !== '') {
                return $normalized;
            }
        }

        $routerBase = Router::fullBaseUrl();

        if ($routerBase !== '') {
            return self::normalizeBaseUrl($routerBase);
        }

        return '';
    }

    /**
     * Build an absolute URL for a registered MCP server path.
     *
     * @param string $path Registered MCP route or URI
     * @return string
     */
    public static function forPath(string $path): string
    {
        $base = self::current();

        if ($base === '') {
            return '/' . ltrim($path, '/');
        }

        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Determine whether a URL is absolute.
     *
     * @param string $url URL candidate
     * @return bool
     */
    public static function isAbsolute(string $url): bool
    {
        return parse_url($url, PHP_URL_SCHEME) !== null
            && parse_url($url, PHP_URL_HOST) !== null;
    }

    /**
     * Normalize a configured base URL value.
     *
     * @param mixed $url URL candidate
     * @return string
     */
    protected static function normalizeBaseUrl(mixed $url): string
    {
        if (!is_string($url) || $url === '' || $url === 'false') {
            return '';
        }

        return rtrim($url, '/');
    }
}
