<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\OAuth;

use Cake\Http\Client;
use Cake\Http\Client\Exception\MissingResponseException;
use Cake\Http\Client\Exception\NetworkException;
use Cake\Utility\Hash;
use Crustum\Mcp\Client\Exception\OAuthException;
use Crustum\Mcp\Client\Trait\InteractsWithOAuthEndpointsTrait;

/**
 * Discovers OAuth authorization server metadata for MCP protected resources.
 */
class AuthServerDiscovery
{
    use InteractsWithOAuthEndpointsTrait;

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
        $this->requireNotInternal($serverMetadata->authorizationEndpoint, $resourceUrl);
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
     * Probe a resource with a ping request, following redirects manually.
     *
     * @param string $resourceUrl Protected resource URL
     * @param array<string, string> $headers Headers forwarded to the probe
     * @return \Cake\Http\Client\Response
     */
    protected function probe(string $resourceUrl, array $headers): Client\Response
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
                $response = $this->oAuthHttpClient()->post($url, $body, [
                    'headers' => array_merge([
                        'Accept' => 'application/json, text/event-stream',
                        'Content-Type' => 'application/json',
                    ], $headers),
                    'redirect' => 0,
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
        $response = $this->oAuthGet($metadataUrl);

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
            $response = $this->oAuthGet($metadataUrl);

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

        if ($this->isLocalhost($this->normalizedHost($parts['host']))) {
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
    protected function requireFetchable(string $url, string $resourceUrl): void
    {
        $this->requireSecure($url);
        $this->requireNotInternal($url, $resourceUrl);
    }

    /**
     * Require OAuth endpoints to avoid private or internal hosts.
     *
     * @param string $url Endpoint URL
     * @param string $resourceUrl Protected resource URL
     * @return void
     */
    protected function requireNotInternal(string $url, string $resourceUrl): void
    {
        $parts = $this->parse($url);
        $resourceParts = $this->parse($resourceUrl);
        $host = $this->normalizedHost($parts['host']);
        $resourceHost = $this->normalizedHost($resourceParts['host']);

        if ($this->isInternalHost($host) && (!$this->isLocalhost($host) || !$this->isLocalhost($resourceHost))) {
            throw new OAuthException("OAuth endpoint [{$url}] cannot use a private or internal host.");
        }
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

        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
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
