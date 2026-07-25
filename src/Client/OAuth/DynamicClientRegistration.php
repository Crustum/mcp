<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\OAuth;

use Crustum\Mcp\Client\Exception\OAuthException;
use Crustum\Mcp\Client\OAuth\Enums\TokenEndpointAuthMethod;
use Crustum\Mcp\Client\Trait\InteractsWithOAuthEndpointsTrait;

/**
 * Performs dynamic OAuth client registration.
 */
class DynamicClientRegistration
{
    use InteractsWithOAuthEndpointsTrait;

    /**
     * Register a new OAuth client dynamically.
     *
     * @param string $registrationEndpoint Registration endpoint URL
     * @param string $redirectUri Redirect URI
     * @param string|null $scope Requested OAuth scope
     * @param string $clientName Registered client name
     * @param string $applicationType OAuth application type
     * @param \Crustum\Mcp\Client\OAuth\Enums\TokenEndpointAuthMethod $tokenEndpointAuthMethod Token endpoint auth method
     * @return \Crustum\Mcp\Client\OAuth\ClientRegistration
     */
    public function register(
        string $registrationEndpoint,
        string $redirectUri,
        ?string $scope = null,
        string $clientName = 'CakePHP MCP Client',
        string $applicationType = 'web',
        TokenEndpointAuthMethod $tokenEndpointAuthMethod = TokenEndpointAuthMethod::ClientSecretPost,
    ): ClientRegistration {
        $response = $this->oAuthPostJson($registrationEndpoint, array_filter([
            'client_name' => $clientName,
            'redirect_uris' => [$redirectUri],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => $tokenEndpointAuthMethod->value,
            'application_type' => $applicationType,
            'scope' => $scope,
        ], static fn(mixed $value): bool => $value !== null));

        if (!$response->isSuccess()) {
            throw new OAuthException("Dynamic client registration failed with status [{$response->getStatusCode()}].");
        }

        $data = $response->getJson();

        if (!is_array($data) || empty($data['client_id'])) {
            throw new OAuthException('Dynamic client registration response did not include a client_id.');
        }

        return new ClientRegistration(
            clientId: (string)$data['client_id'],
            clientSecret: isset($data['client_secret']) ? (string)$data['client_secret'] : null,
        );
    }
}
