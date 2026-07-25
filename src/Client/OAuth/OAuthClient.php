<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\OAuth;

use Cake\Http\Response;
use Cake\Http\Session;
use Crustum\Mcp\Client\Exception\OAuthException;
use Crustum\Mcp\Client\OAuth\Enums\TokenEndpointAuthMethod;
use Crustum\Mcp\Client\Trait\InteractsWithOAuthEndpointsTrait;
use SensitiveParameter;

/**
 * OAuth browser and token flow client for MCP web servers.
 */
class OAuthClient
{
    use InteractsWithOAuthEndpointsTrait;

    /**
     * Cached discovery result.
     *
     * @var \Crustum\Mcp\Client\OAuth\DiscoveryResult|null
     */
    protected ?DiscoveryResult $discovered = null;

    /**
     * Return URL captured during callback exchange.
     *
     * @var string|null
     */
    protected ?string $returnTo = null;

    /**
     * Create a new OAuth client.
     *
     * @param \Crustum\Mcp\Client\OAuth\OAuthConfig $config OAuth configuration
     * @param string $resourceUrl Protected MCP resource URL
     * @param string|null $resourceMetadataUrl Explicit protected resource metadata URL
     * @param string|null $challengeScope Scope requested by the WWW-Authenticate challenge
     * @param \Crustum\Mcp\Client\OAuth\AuthServerDiscovery $discovery Authorization server discovery service
     * @param \Cake\Http\Session|null $session HTTP session for browser OAuth state
     */
    public function __construct(
        protected OAuthConfig $config,
        protected string $resourceUrl,
        protected ?string $resourceMetadataUrl = null,
        protected ?string $challengeScope = null,
        protected AuthServerDiscovery $discovery = new AuthServerDiscovery(),
        protected ?Session $session = null,
    ) {
        $this->resourceUrl = self::stripFragment($this->resourceUrl);
    }

    /**
     * Begin the OAuth authorization code flow.
     *
     * @param string|null $returnTo URL to return to after authorization
     * @return \Cake\Http\Response
     */
    public function redirect(?string $returnTo = null): Response
    {
        $session = $this->requireSession();
        $discovered = $this->discover();
        $metadata = $discovered->server;

        if ($metadata->codeChallengeMethodsSupported !== [] && !in_array('S256', $metadata->codeChallengeMethodsSupported, true)) {
            throw new OAuthException('The authorization server does not support the required S256 PKCE method.');
        }

        $clientId = $this->config->clientId;
        $clientSecret = $this->config->clientSecret;
        $redirectUri = $this->config->redirectUri ?? throw new OAuthException('A redirect URI is required.');

        if ($clientId === null) {
            $registration = $this->register($metadata, $redirectUri);

            $clientId = $registration->clientId;
            $clientSecret = $registration->clientSecret;
        }

        $pkce = Pkce::generate();
        $state = bin2hex(random_bytes(20));

        $session->write($this->sessionKey(), [
            'state' => $state,
            'verifier' => $pkce->verifier,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'token_endpoint' => $metadata->tokenEndpoint,
            'token_auth_method' => $this->resolveTokenAuthMethod($metadata, $clientSecret)->value,
            'redirect_uri' => $redirectUri,
            'return_to' => $returnTo,
            'issuer' => $metadata->issuer,
            'iss_supported' => $metadata->authorizationResponseIssParameterSupported,
        ]);

        $authorizeUrl = self::appendQuery($metadata->authorizationEndpoint, array_filter([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'code_challenge' => $pkce->challenge,
            'code_challenge_method' => 'S256',
            'scope' => $this->resolveScope(),
            'resource' => $this->resourceUrl,
        ], static fn(mixed $value): bool => $value !== null && $value !== ''));

        return (new Response(['status' => 302, 'charset' => 'UTF-8']))
            ->withHeader('Location', $authorizeUrl);
    }

    /**
     * Request an access token using the client credentials grant.
     *
     * @return \Crustum\Mcp\Client\OAuth\TokenSet
     */
    public function clientCredentials(): TokenSet
    {
        if ($this->config->clientId === null) {
            throw new OAuthException('A client_id is required for the client_credentials grant.');
        }

        $discovered = $this->discover();

        return $this->requestToken(
            $discovered->server->tokenEndpoint,
            [
                'grant_type' => 'client_credentials',
                'scope' => $this->resolveScope(),
                'resource' => $this->resourceUrl,
            ],
            $this->config->clientId,
            $this->config->clientSecret,
            $this->resolveTokenAuthMethod($discovered->server, $this->config->clientSecret),
        );
    }

    /**
     * Exchange an OAuth callback for an access token.
     *
     * @param array<string, mixed> $query OAuth callback query parameters
     * @param \Cake\Http\Session|null $session HTTP session containing pending OAuth state
     * @return \Crustum\Mcp\Client\OAuth\TokenSet
     */
    public function exchangeCallback(array $query, ?Session $session = null): TokenSet
    {
        $session ??= $this->requireSession();

        $this->throwOnServerError($query);

        $code = self::queryValue($query, 'code');

        if ($code === null) {
            throw new OAuthException('The OAuth callback did not include an authorization code.');
        }

        $state = self::queryValue($query, 'state') ?? '';
        $iss = self::queryValue($query, 'iss');

        return $this->exchangeAuthorizationCode(
            $code,
            $state,
            $iss,
            $session,
        );
    }

    /**
     * Get the return URL captured during callback exchange.
     *
     * @return string|null
     */
    public function returnTo(): ?string
    {
        return $this->returnTo;
    }

    /**
     * Refresh OAuth credentials using a refresh token.
     *
     * @param string $refreshToken Refresh token
     * @param string|null $clientId OAuth client identifier
     * @param string|null $clientSecret OAuth client secret
     * @return \Crustum\Mcp\Client\OAuth\TokenSet
     */
    public function refreshCredentials(
        #[SensitiveParameter]
        string $refreshToken,
        ?string $clientId = null,
        #[SensitiveParameter]
        ?string $clientSecret = null,
    ): TokenSet {
        $discovered = $this->discover();

        $clientId ??= $this->config->clientId;
        $clientSecret ??= $this->config->clientSecret;

        $token = $this->requestToken(
            $discovered->server->tokenEndpoint,
            [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'scope' => $this->resolveScope(),
                'resource' => $this->resourceUrl,
            ],
            $clientId,
            $clientSecret,
            $this->resolveTokenAuthMethod($discovered->server, $clientSecret),
        );

        $token->clientId = $clientId;
        $token->clientSecret = $clientSecret;

        return $token;
    }

    /**
     * Exchange an authorization code for an access token.
     *
     * @param string $code Authorization code
     * @param string $state OAuth state parameter
     * @param string|null $iss OAuth issuer parameter
     * @param \Cake\Http\Session $session HTTP session containing pending OAuth state
     * @return \Crustum\Mcp\Client\OAuth\TokenSet
     */
    protected function exchangeAuthorizationCode(
        #[SensitiveParameter]
        string $code,
        string $state,
        ?string $iss,
        Session $session,
    ): TokenSet {
        /** @var array<string, mixed>|null $stored */
        $stored = $session->read($this->sessionKey());

        if (!is_array($stored)) {
            throw new OAuthException('No pending OAuth authorization was found in the session.');
        }

        if (!is_string($stored['state'] ?? null) || !hash_equals($stored['state'], $state)) {
            throw new OAuthException('The OAuth state parameter did not match. Possible CSRF attempt.');
        }

        $this->validateIssuer($stored, $iss);

        $this->returnTo = is_string($stored['return_to'] ?? null) && $stored['return_to'] !== ''
            ? $stored['return_to']
            : null;

        $session->delete($this->sessionKey());

        $clientId = (string)$stored['client_id'];
        $clientSecret = isset($stored['client_secret']) ? (string)$stored['client_secret'] : null;

        $token = $this->requestToken(
            (string)$stored['token_endpoint'],
            [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => (string)$stored['redirect_uri'],
                'code_verifier' => (string)$stored['verifier'],
                'resource' => $this->resourceUrl,
            ],
            $clientId,
            $clientSecret,
            TokenEndpointAuthMethod::tryFrom((string)($stored['token_auth_method'] ?? '')) ?? TokenEndpointAuthMethod::ClientSecretPost,
        );

        $token->clientId = $clientId;
        $token->clientSecret = $clientSecret;

        return $token;
    }

    /**
     * Register a client dynamically when no client identifier is configured.
     *
     * @param \Crustum\Mcp\Client\OAuth\AuthServerMetadata $metadata Authorization server metadata
     * @param string $redirectUri Redirect URI
     * @return \Crustum\Mcp\Client\OAuth\ClientRegistration
     */
    protected function register(AuthServerMetadata $metadata, string $redirectUri): ClientRegistration
    {
        if ($metadata->registrationEndpoint === null) {
            throw new OAuthException('No client_id was configured and the authorization server does not support dynamic client registration.');
        }

        return (new DynamicClientRegistration())->register(
            $metadata->registrationEndpoint,
            $redirectUri,
            $this->resolveScope(),
            applicationType: $this->applicationType($redirectUri),
            tokenEndpointAuthMethod: $this->resolveTokenAuthMethod($metadata, 'confidential'),
        );
    }

    /**
     * Request an OAuth token from the token endpoint.
     *
     * @param string $tokenEndpoint Token endpoint URL
     * @param array<string, mixed> $params Token request parameters
     * @param string|null $clientId OAuth client identifier
     * @param string|null $clientSecret OAuth client secret
     * @param \Crustum\Mcp\Client\OAuth\Enums\TokenEndpointAuthMethod $authMethod Token endpoint auth method
     * @return \Crustum\Mcp\Client\OAuth\TokenSet
     */
    protected function requestToken(
        string $tokenEndpoint,
        array $params,
        ?string $clientId,
        #[SensitiveParameter]
        ?string $clientSecret,
        TokenEndpointAuthMethod $authMethod,
    ): TokenSet {
        $credentials = match ($authMethod) {
            TokenEndpointAuthMethod::ClientSecretBasic => [],
            TokenEndpointAuthMethod::ClientSecretPost => [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ],
            TokenEndpointAuthMethod::None => [
                'client_id' => $clientId,
            ],
        };

        $options = [];

        if ($authMethod === TokenEndpointAuthMethod::ClientSecretBasic) {
            $options['auth'] = [
                'username' => (string)$clientId,
                'password' => (string)$clientSecret,
                'type' => 'basic',
            ];
        }

        $response = $this->oAuthPostForm($tokenEndpoint, array_filter([
            ...$params,
            ...$credentials,
        ], static fn(mixed $value): bool => $value !== null), $options);

        if (!$response->isSuccess()) {
            throw new OAuthException("Token request to [{$tokenEndpoint}] failed with status [{$response->getStatusCode()}].");
        }

        $data = $response->getJson();

        if (!is_array($data) || empty($data['access_token'])) {
            throw new OAuthException('The token response did not include an access_token.');
        }

        return TokenSet::fromResponse($data);
    }

    /**
     * Throw when the OAuth callback includes an error response.
     *
     * @param array<string, mixed> $query OAuth callback query parameters
     * @return void
     */
    protected function throwOnServerError(array $query): void
    {
        $error = self::queryValue($query, 'error');

        if ($error === null) {
            return;
        }

        $description = self::queryValue($query, 'error_description');

        throw new OAuthException($description === null
            ? "The authorization server returned an error [{$error}]."
            : "The authorization server returned an error [{$error}]: {$description}");
    }

    /**
     * Discover OAuth metadata for the configured resource.
     *
     * @return \Crustum\Mcp\Client\OAuth\DiscoveryResult
     */
    protected function discover(): DiscoveryResult
    {
        return $this->discovered ??= $this->discovery->discover($this->resourceUrl, $this->resourceMetadataUrl);
    }

    /**
     * Resolve the OAuth scope to request.
     *
     * @return string|null
     */
    protected function resolveScope(): ?string
    {
        if ($this->challengeScope !== null && $this->challengeScope !== '') {
            return $this->challengeScope;
        }

        return $this->config->scope ?? 'mcp:use';
    }

    /**
     * Resolve the token endpoint authentication method.
     *
     * @param \Crustum\Mcp\Client\OAuth\AuthServerMetadata $metadata Authorization server metadata
     * @param string|null $clientSecret OAuth client secret
     * @return \Crustum\Mcp\Client\OAuth\Enums\TokenEndpointAuthMethod
     */
    protected function resolveTokenAuthMethod(
        AuthServerMetadata $metadata,
        #[SensitiveParameter]
        ?string $clientSecret,
    ): TokenEndpointAuthMethod {
        if ($clientSecret === null || $clientSecret === '') {
            return TokenEndpointAuthMethod::None;
        }

        $supported = $metadata->tokenEndpointAuthMethodsSupported;

        if (
            $supported !== []
            && !in_array(TokenEndpointAuthMethod::ClientSecretPost->value, $supported, true)
            && in_array(TokenEndpointAuthMethod::ClientSecretBasic->value, $supported, true)
        ) {
            return TokenEndpointAuthMethod::ClientSecretBasic;
        }

        return TokenEndpointAuthMethod::ClientSecretPost;
    }

    /**
     * Resolve the dynamic client registration application type.
     *
     * @param string $redirectUri Redirect URI
     * @return string
     */
    protected function applicationType(string $redirectUri): string
    {
        $host = parse_url($redirectUri, PHP_URL_HOST);

        return is_string($host) && in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            ? 'native'
            : 'web';
    }

    /**
     * Validate the OAuth issuer parameter when required.
     *
     * @param array<string, mixed> $stored Stored OAuth session state
     * @param string|null $iss OAuth issuer parameter
     * @return void
     */
    protected function validateIssuer(array $stored, ?string $iss): void
    {
        $expectedIssuer = is_string($stored['issuer'] ?? null) ? $stored['issuer'] : '';

        if ($iss !== null) {
            if ($expectedIssuer === '' || !hash_equals($expectedIssuer, $iss)) {
                throw new OAuthException('The OAuth issuer (iss) parameter did not match the expected issuer. Possible mix-up attack.');
            }

            return;
        }

        if ($stored['iss_supported'] ?? false) {
            throw new OAuthException('The authorization response is missing the required iss parameter.');
        }
    }

    /**
     * Get the session key used to store pending OAuth state.
     *
     * @return string
     */
    protected function sessionKey(): string
    {
        return 'mcp.oauth.' . sha1($this->resourceUrl);
    }

    /**
     * Require an HTTP session for browser OAuth flows.
     *
     * @return \Cake\Http\Session
     */
    protected function requireSession(): Session
    {
        if ($this->session instanceof Session) {
            return $this->session;
        }

        throw new OAuthException('An HTTP session is required for browser OAuth flows.');
    }

    /**
     * Strip the URL fragment from a resource URL.
     *
     * @param string $url Resource URL
     * @return string
     */
    protected static function stripFragment(string $url): string
    {
        $position = strpos($url, '#');

        if ($position === false) {
            return $url;
        }

        return substr($url, 0, $position);
    }

    /**
     * Append query parameters to a URL.
     *
     * @param string $url Base URL
     * @param array<string, mixed> $query Query parameters
     * @return string
     */
    protected static function appendQuery(string $url, array $query): string
    {
        if ($query === []) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Read a non-empty string value from query parameters.
     *
     * @param array<string, mixed> $query Query parameters
     * @param string $key Query parameter name
     * @return string|null
     */
    protected static function queryValue(array $query, string $key): ?string
    {
        $value = $query[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
