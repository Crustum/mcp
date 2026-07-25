<?php
declare(strict_types=1);

namespace Crustum\Mcp;

use Cake\Http\Session;
use Closure;
use Crustum\Mcp\Client\Exception\OAuthException;
use Crustum\Mcp\Client\OAuth\OAuthClient;
use Crustum\Mcp\Client\OAuth\OAuthConfig;
use Crustum\Mcp\Client\OAuth\OAuthRouteRegistrar;
use Crustum\Mcp\Client\Transport\HttpTransport;
use Crustum\Mcp\Schema\Implementation;
use Override;
use SensitiveParameter;

/**
 * MCP client for remote HTTP servers.
 */
class WebClient extends Client
{
    /**
     * Create a new web MCP client.
     *
     * @param \Crustum\Mcp\Client\Transport\HttpTransport $httpTransport HTTP transport
     * @param \Crustum\Mcp\Schema\Implementation|null $clientInfo Client implementation metadata
     * @param \Crustum\Mcp\Client\OAuth\OAuthConfig|null $oAuthConfig OAuth configuration
     */
    public function __construct(
        protected HttpTransport $httpTransport,
        public ?Implementation $clientInfo = null,
        protected ?OAuthConfig $oAuthConfig = null,
    ) {
        parent::__construct($httpTransport, $clientInfo);
    }

    /**
     * Configure a bearer token for authenticated requests.
     *
     * @param \Closure(): string|string $token Bearer token or token resolver
     * @return static
     */
    public function withToken(#[SensitiveParameter]
    string|Closure $token,): static
    {
        $this->httpTransport->withToken($token);

        return $this;
    }

    /**
     * Merge custom HTTP headers into outbound requests.
     *
     * @param array<string, string> $headers HTTP headers
     * @return static
     */
    public function withHeaders(array $headers): static
    {
        $this->httpTransport->withHeaders($headers);

        return $this;
    }

    /**
     * Configure OAuth settings for browser-based authorization.
     *
     * @param string|null $clientId OAuth client identifier
     * @param string|null $clientSecret OAuth client secret
     * @param string|null $scope Requested OAuth scope
     * @param string|null $redirectUri OAuth redirect URI
     * @return static
     */
    public function withOAuth(
        ?string $clientId = null,
        #[SensitiveParameter]
        ?string $clientSecret = null,
        ?string $scope = null,
        ?string $redirectUri = null,
    ): static {
        $this->oAuthConfig = new OAuthConfig(
            clientId: $clientId,
            clientSecret: $clientSecret,
            scope: $scope,
            redirectUri: $redirectUri,
        );

        return $this;
    }

    /**
     * Create an OAuth client for the configured MCP server.
     *
     * @param string|null $resourceMetadataUrl Explicit protected resource metadata URL
     * @param string|null $challengeScope Scope requested by the WWW-Authenticate challenge
     * @param \Cake\Http\Session|null $session HTTP session for browser OAuth state
     * @return \Crustum\Mcp\Client\OAuth\OAuthClient
     */
    public function oAuthClient(
        ?string $resourceMetadataUrl = null,
        ?string $challengeScope = null,
        ?Session $session = null,
    ): OAuthClient {
        if (!$this->oAuthConfig instanceof OAuthConfig) {
            throw new OAuthException('No OAuth configuration found. Call withOAuth() before oAuthClient().');
        }

        $config = $this->oAuthConfig;

        if ($config->redirectUri === null && $this->name !== null) {
            $config = clone $config;

            $callbackUrl = OAuthRouteRegistrar::callbackUrl($this->name);

            if ($callbackUrl !== null && $callbackUrl !== '') {
                $config->redirectUri = $callbackUrl;
            }
        }

        return new OAuthClient(
            $config,
            $this->httpTransport->url(),
            $resourceMetadataUrl,
            $challengeScope,
            session: $session,
        );
    }

    /**
     * Restore a serialized web client instance.
     *
     * @param array<string, mixed> $data Serialized client data
     * @return void
     */
    #[Override]
    public function __unserialize(array $data): void
    {
        parent::__unserialize($data);

        $this->oAuthConfig = null;

        if ($this->transport instanceof HttpTransport) {
            $this->httpTransport = $this->transport;
        }
    }
}
