<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\OAuth;

use Cake\Http\Client\Exception\MissingResponseException;
use Cake\Http\Client\Exception\NetworkException;
use Cake\Http\Client\Response;
use Cake\Utility\Hash;
use Closure;
use Crustum\Mcp\Client\Exception\OAuthException;
use Crustum\Mcp\Client\Trait\InteractsWithOAuthEndpointsTrait;

/**
 * Discovers OAuth authorization server metadata for MCP protected resources.
 */
class AuthServerDiscovery
{
    use InteractsWithOAuthEndpointsTrait;

    /**
     * IPv6 address prefixes that are never public.
     *
     * @var array<int, string>
     */
    protected const NON_PUBLIC_IPV6_PREFIXES = [
        'ff',
        '2002',
        '20010000',
        '0064ff9b0001',
        'fec',
        'fed',
        'fee',
        'fef',
        '000000000000000000000000',
        '0000000000000000ffff0000',
    ];

    /**
     * Resolved addresses keyed by normalized host.
     *
     * @var array<string, array<int, string>>
     */
    protected array $resolved = [];

    /**
     * Host resolver override for tests.
     *
     * @var \Closure|null
     */
    protected static ?Closure $hostResolver = null;

    /**
     * Override host resolution, e.g. in tests.
     *
     * @param \Closure|null $callback Resolver receiving a host and returning addresses
     * @return void
     */
    public static function resolveHostsUsing(?Closure $callback): void
    {
        static::$hostResolver = $callback;
    }

    /**
     * Discover OAuth metadata for a protected MCP resource.
     *
     * @param string $resourceUrl Protected resource URL
     * @param string|null $resourceMetadataUrl Explicit protected resource metadata URL
     * @return \Crustum\Mcp\Client\OAuth\DiscoveryResult
     */
    public function discover(string $resourceUrl, ?string $resourceMetadataUrl = null): DiscoveryResult
    {
        $metadataUrl = $resourceMetadataUrl ?? $this->wellKnown($resourceUrl, 'oauth-protected-resource');

        $this->requireFetchable($metadataUrl, $resourceUrl);

        $resourceMetadata = $this->fetchResourceMetadata($metadataUrl, explicit: $resourceMetadataUrl !== null);

        $this->requireResourceMatches($resourceMetadata, $resourceUrl);

        $issuer = $this->issuerFrom($resourceMetadata) ?? $this->origin($resourceUrl);

        $this->requireSecure($issuer);
        $this->requireNotInternal($issuer, $resourceUrl);

        $serverMetadata = $this->fetchMetadata($issuer);

        if (!hash_equals($issuer, $serverMetadata->issuer)) {
            throw new OAuthException("Authorization server issuer [{$serverMetadata->issuer}] did not match the expected issuer [{$issuer}].");
        }

        $this->requireSecure($serverMetadata->authorizationEndpoint);
        $this->requireSecure($serverMetadata->tokenEndpoint);
        $this->requireNotInternal($serverMetadata->authorizationEndpoint, $resourceUrl, resolve: false);
        $this->requireNotInternal($serverMetadata->tokenEndpoint, $resourceUrl);

        if ($serverMetadata->registrationEndpoint !== null) {
            $this->requireSecure($serverMetadata->registrationEndpoint);
            $this->requireNotInternal($serverMetadata->registrationEndpoint, $resourceUrl);
        }

        $scopesSupported = array_values(array_map(strval(...), (array)($resourceMetadata['scopes_supported'] ?? [])));

        return new DiscoveryResult($serverMetadata, $scopesSupported);
    }

    /**
     * Require a resource to advertise a caller supplied metadata URL.
     *
     * @param string $resourceUrl Protected resource URL
     * @param string $resourceMetadataUrl Caller supplied metadata URL
     * @param array<string, string> $headers Client headers forwarded to the probe
     * @return void
     */
    public function requireAdvertised(string $resourceUrl, string $resourceMetadataUrl, array $headers = []): void
    {
        $this->requireFetchable($resourceMetadataUrl, $resourceUrl);

        if ($resourceMetadataUrl === $this->wellKnown($resourceUrl, 'oauth-protected-resource')) {
            return;
        }

        $this->requireFetchable($resourceUrl, $resourceUrl);

        $response = $this->probe($resourceUrl, array_filter(
            $headers,
            static fn(string $name): bool => strcasecmp($name, 'Authorization') !== 0 && !str_starts_with(strtolower($name), 'mcp-'),
            ARRAY_FILTER_USE_KEY,
        ));

        $advertised = WwwAuthenticateChallenge::parse($response->getHeaderLine('WWW-Authenticate'))->resourceMetadataUrl;

        if (!in_array($response->getStatusCode(), [401, 403], true) || $advertised === null || !hash_equals($advertised, $resourceMetadataUrl)) {
            throw new OAuthException("The protected resource metadata URL [{$resourceMetadataUrl}] was not advertised by the resource [{$resourceUrl}].");
        }
    }

    /**
     * Build curl options pinning a URL to its checked addresses.
     *
     * @param string $url Request URL
     * @return array<string, mixed>
     */
    public function pinOptions(string $url): array
    {
        if (!defined('CURLOPT_RESOLVE')) {
            return [];
        }

        $parts = $this->parse($url);
        $addresses = $this->resolved[$this->normalizedHost($parts['host'])] ?? [];

        if ($addresses === []) {
            return [];
        }

        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
        $addresses = array_map(static fn(string $address): string => str_contains($address, ':') ? "[{$address}]" : $address, $addresses);

        return ['curl' => [CURLOPT_RESOLVE => ["{$parts['host']}:{$port}:" . implode(',', $addresses)]]];
    }

    /**
     * Probe a resource with a ping request, following redirects manually.
     *
     * @param string $resourceUrl Protected resource URL
     * @param array<string, string> $headers Headers forwarded to the probe
     * @return \Cake\Http\Client\Response
     */
    protected function probe(string $resourceUrl, array $headers): Response
    {
        $body = (string)json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'ping',
        ]);

        $url = $resourceUrl;
        $redirects = 0;

        while (true) {
            try {
                /** @var array<int, mixed> $curl */
                $curl = ($this->pinOptions($url)['curl'] ?? []) + $this->oAuthCurlOptions();

                $response = $this->oAuthHttpClient()->post($url, $body, [
                    'headers' => array_merge([
                        'Accept' => 'application/json, text/event-stream',
                        'Content-Type' => 'application/json',
                    ], $headers),
                    'redirect' => 0,
                    'curl' => $curl,
                ]);
            } catch (NetworkException | MissingResponseException $exception) {
                throw new OAuthException("Unable to reach the resource [{$resourceUrl}] to verify its protected resource metadata.", 0, $exception);
            }

            if (!$response->isRedirect()) {
                return $response;
            }

            if ($redirects === 5) {
                throw new OAuthException("Unable to reach the resource [{$resourceUrl}] to verify its protected resource metadata.");
            }

            $redirects++;

            $url = $this->resolveRedirect($response->getHeaderLine('Location'), $url, $resourceUrl);
        }
    }

    /**
     * Resolve a redirect location against the probed URL.
     *
     * @param string $location Redirect location header value
     * @param string $base Probed URL the redirect was received from
     * @param string $resourceUrl Protected resource URL
     * @return string
     */
    protected function resolveRedirect(string $location, string $base, string $resourceUrl): string
    {
        if ($location === '') {
            throw new OAuthException("Unable to reach the resource [{$resourceUrl}] to verify its protected resource metadata.");
        }

        if (str_starts_with($location, '//')) {
            $next = parse_url($base, PHP_URL_SCHEME) . ':' . $location;
        } elseif (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $location) === 1) {
            $next = $location;
        } else {
            $next = rtrim($this->origin($base), '/') . '/' . ltrim($location, '/');
        }

        $parts = parse_url($next);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new OAuthException("Unable to reach the resource [{$resourceUrl}] to verify its protected resource metadata.");
        }

        $next = strtolower((string)$parts['scheme']) . substr($next, strlen((string)$parts['scheme']));

        $this->requireFetchable($next, $resourceUrl);

        return $next;
    }

    /**
     * Fetch protected resource metadata.
     *
     * @param string $metadataUrl Metadata document URL
     * @param bool $explicit Whether the metadata URL was explicitly provided
     * @return array<string, mixed>
     */
    protected function fetchResourceMetadata(string $metadataUrl, bool $explicit = false): array
    {
        $response = $this->oAuthGet($metadataUrl, $this->pinOptions($metadataUrl));

        if (!$response->isSuccess()) {
            if ($explicit) {
                throw new OAuthException("Protected resource metadata request to [{$metadataUrl}] failed with status [{$response->getStatusCode()}].");
            }

            return [];
        }

        $data = $response->getJson();

        if (is_array($data)) {
            return $data;
        }

        if ($explicit) {
            throw new OAuthException("Protected resource metadata at [{$metadataUrl}] did not return a valid JSON object.");
        }

        return [];
    }

    /**
     * Ensure protected resource metadata matches the requested resource URL.
     *
     * @param array<string, mixed> $resourceMetadata Protected resource metadata
     * @param string $resourceUrl Expected resource URL
     * @return void
     */
    protected function requireResourceMatches(array $resourceMetadata, string $resourceUrl): void
    {
        $resource = Hash::get($resourceMetadata, 'resource');

        if (is_string($resource) && !hash_equals($resourceUrl, $resource)) {
            throw new OAuthException("Protected resource metadata resource [{$resource}] did not match the expected resource [{$resourceUrl}].");
        }
    }

    /**
     * Resolve the authorization server issuer from protected resource metadata.
     *
     * @param array<string, mixed> $resourceMetadata Protected resource metadata
     * @return string|null
     */
    protected function issuerFrom(array $resourceMetadata): ?string
    {
        $servers = Hash::get($resourceMetadata, 'authorization_servers');

        if (is_array($servers) && $servers !== []) {
            return (string)$servers[0];
        }

        return null;
    }

    /**
     * Fetch authorization server metadata for an issuer.
     *
     * @param string $issuer Authorization server issuer
     * @return \Crustum\Mcp\Client\OAuth\AuthServerMetadata
     */
    protected function fetchMetadata(string $issuer): AuthServerMetadata
    {
        foreach ($this->metadataUrls($issuer) as $metadataUrl) {
            $response = $this->oAuthGet($metadataUrl, $this->pinOptions($metadataUrl));

            if (!$response->isSuccess()) {
                continue;
            }

            $metadata = $response->getJson();

            if (is_array($metadata)) {
                return AuthServerMetadata::fromArray($metadata);
            }
        }

        throw new OAuthException("Unable to discover authorization server metadata from [{$issuer}].");
    }

    /**
     * Build candidate authorization server metadata URLs.
     *
     * @param string $issuer Authorization server issuer
     * @return array<int, string>
     */
    protected function metadataUrls(string $issuer): array
    {
        $parts = $this->parse($issuer);

        $origin = $this->originFromParts($parts);

        $path = rtrim($parts['path'] ?? '', '/');

        if ($path === '') {
            return [
                $origin . '/.well-known/oauth-authorization-server',
                $origin . '/.well-known/openid-configuration',
            ];
        }

        return [
            $origin . '/.well-known/oauth-authorization-server' . $path,
            $origin . '/.well-known/openid-configuration' . $path,
            $origin . $path . '/.well-known/openid-configuration',
        ];
    }

    /**
     * Build a well-known metadata URL for a resource.
     *
     * @param string $url Resource URL
     * @param string $type Well-known document type
     * @return string
     */
    protected function wellKnown(string $url, string $type): string
    {
        $parts = $this->parse($url);

        $path = $parts['path'] ?? '';

        return $this->originFromParts($parts) . '/.well-known/' . $type . $path;
    }

    /**
     * Resolve the origin for a URL.
     *
     * @param string $url URL to parse
     * @return string
     */
    protected function origin(string $url): string
    {
        return $this->originFromParts($this->parse($url));
    }

    /**
     * Require an OAuth endpoint to use HTTPS unless it is localhost.
     *
     * @param string $url Endpoint URL
     * @return void
     */
    protected function requireSecure(string $url): void
    {
        $parts = $this->parse($url);

        if ($parts['scheme'] === 'https') {
            return;
        }

        if ($parts['scheme'] === 'http' && $this->isLocalhost($this->normalizedHost($parts['host']))) {
            return;
        }

        throw new OAuthException("OAuth endpoint [{$url}] must be served over HTTPS.");
    }

    /**
     * Require an OAuth metadata URL to be fetchable.
     *
     * @param string $url Metadata URL
     * @param string $resourceUrl Protected resource URL
     * @return void
     */
    public function requireFetchable(string $url, string $resourceUrl): void
    {
        $this->requireSecure($url);
        $this->requireNotInternal($url, $resourceUrl);
    }

    /**
     * Require OAuth endpoints to avoid private or internal hosts.
     *
     * @param string $url Endpoint URL
     * @param string $resourceUrl Protected resource URL
     * @param bool $resolve Whether to resolve host names to addresses
     * @return void
     */
    protected function requireNotInternal(string $url, string $resourceUrl, bool $resolve = true): void
    {
        $host = $this->normalizedHost($this->parse($url)['host']);

        if (!$this->isLocalhost($host) && !$this->isIpAddress($host)) {
            if (!$resolve || ($this->usesProxy() && !$this->isLocalName($host))) {
                return;
            }

            if ($this->resolve($host) === []) {
                throw new OAuthException("OAuth endpoint [{$url}] cannot use a private, internal, or unresolvable host.");
            }
        }

        if (!$this->isInternalHost($host) || $this->isPrivateHost($this->normalizedHost($this->parse($resourceUrl)['host']))) {
            return;
        }

        throw new OAuthException("OAuth endpoint [{$url}] cannot use a private, internal, or unresolvable host.");
    }

    /**
     * Determine whether a host is a local development name.
     *
     * @param string $host Host name
     * @return bool
     */
    protected function isLocalName(string $host): bool
    {
        $name = '.' . rtrim($host, '.');

        return array_any(['.localhost', '.local', '.internal', '.test'], fn(string $suffix): bool => str_ends_with($name, $suffix));
    }

    /**
     * Determine whether requests go through an HTTP proxy.
     *
     * @return bool
     */
    protected function usesProxy(): bool
    {
        return array_any(['HTTPS_PROXY', 'https_proxy', 'ALL_PROXY', 'all_proxy'], fn(string $variable): string|false => getenv($variable));
    }

    /**
     * Determine whether a host is private or internal.
     *
     * @param string $host Host name or IP address
     * @return bool
     */
    protected function isInternalHost(string $host): bool
    {
        if ($this->isLocalhost($host)) {
            return true;
        }

        $addresses = $this->isIpAddress($host) ? [$host] : $this->resolve($host);

        return array_filter($addresses, fn(string $address): bool => !$this->isPublicAddress($address)) !== [];
    }

    /**
     * Determine whether a host only resolves to private addresses.
     *
     * @param string $host Host name or IP address
     * @return bool
     */
    protected function isPrivateHost(string $host): bool
    {
        if ($this->isLocalhost($host)) {
            return true;
        }

        $addresses = $this->isIpAddress($host) ? [$host] : $this->resolve($host);

        return $addresses !== [] && array_filter($addresses, $this->isPublicAddress(...)) === [];
    }

    /**
     * Determine whether a host value is an IP address.
     *
     * @param string $host Host name or IP address
     * @return bool
     */
    protected function isIpAddress(string $host): bool
    {
        return filter_var($host, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Determine whether an IP address is publicly routable.
     *
     * @param string $address IP address
     * @return bool
     */
    protected function isPublicAddress(string $address): bool
    {
        if (!$this->isIpAddress($address)) {
            return false;
        }

        $packed = (string)inet_pton($address);

        if (str_starts_with($packed, str_repeat("\0", 10) . "\xFF\xFF") || str_starts_with($packed, "\x00\x64\xFF\x9B" . str_repeat("\0", 8))) {
            return $this->isPublicAddress((string)inet_ntop(substr($packed, 12)));
        }

        if ($this->isRestrictedAddress($address)) {
            return false;
        }

        if (strlen($packed) === 4) {
            $long = (int)ip2long($address);

            return ($long & 0xFFC00000) !== 0x64400000 && ($long & 0xF0000000) !== 0xE0000000;
        }

        return !$this->startsWithAny(bin2hex($packed), self::NON_PUBLIC_IPV6_PREFIXES);
    }

    /**
     * Determine whether an IP address is restricted to non-global ranges.
     *
     * The validator returns the address unchanged on success, so any other
     * result means the address is restricted.
     *
     * @param string $address IP address
     * @return bool
     */
    protected function isRestrictedAddress(string $address): bool
    {
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE;

        return filter_var($address, FILTER_VALIDATE_IP, $flags) !== $address;
    }

    /**
     * Determine whether a value starts with any of the given prefixes.
     *
     * @param string $value Value to check
     * @param array<int, string> $prefixes Accepted prefixes
     * @return bool
     */
    protected function startsWithAny(string $value, array $prefixes): bool
    {
        return array_any($prefixes, fn(string $prefix): bool => str_starts_with($value, $prefix));
    }

    /**
     * Resolve a host to IP addresses, caching per instance.
     *
     * @param string $host Host name or IP address
     * @return array<int, string>
     */
    protected function resolve(string $host): array
    {
        return $this->resolved[$host] ??= $this->lookup($host);
    }

    /**
     * Look up IP addresses for a host.
     *
     * DNS warnings are suppressed while resolving.
     *
     * @param string $host Host name or IP address
     * @return array<int, string>
     */
    protected function lookup(string $host): array
    {
        if (static::$hostResolver instanceof Closure) {
            return (static::$hostResolver)($host);
        }

        set_error_handler(static fn(): bool => true);

        try {
            $addresses = gethostbynamel($host) ?: [];

            foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                $addresses[] = $record['ipv6'];
            }
        } finally {
            restore_error_handler();
        }

        return array_values(array_unique($addresses));
    }

    /**
     * Determine whether a host is localhost.
     *
     * @param string $host Host name or IP address
     * @return bool
     */
    protected function isLocalhost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * Normalize a host value for comparison.
     *
     * @param string $host Host name or IP address
     * @return string
     */
    protected function normalizedHost(string $host): string
    {
        return strtolower(trim($host, '[]'));
    }

    /**
     * Build an origin URL from parsed URL parts.
     *
     * @param array{scheme: string, host: string, port?: int, path?: string} $parts Parsed URL parts
     * @return string
     */
    protected function originFromParts(array $parts): string
    {
        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /**
     * Parse a URL into OAuth-safe components.
     *
     * @param string $url URL to parse
     * @return array{scheme: string, host: string, port?: int, path?: string}
     */
    protected function parse(string $url): array
    {
        $parts = parse_url($url);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new OAuthException("Unable to parse URL [{$url}] during OAuth discovery.");
        }

        /** @var array{scheme: string, host: string, port?: int, path?: string} $parts */
        return $parts;
    }
}
