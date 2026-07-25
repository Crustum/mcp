<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\OAuth;

use Crustum\Mcp\Client\Exception\OAuthException;

/**
 * OAuth authorization server metadata.
 */
class AuthServerMetadata
{
    /**
     * Create authorization server metadata.
     *
     * @param string $issuer Authorization server issuer
     * @param string $authorizationEndpoint Authorization endpoint URL
     * @param string $tokenEndpoint Token endpoint URL
     * @param string|null $registrationEndpoint Dynamic client registration endpoint URL
     * @param array<int, string> $codeChallengeMethodsSupported Supported PKCE methods
     * @param bool $authorizationResponseIssParameterSupported Whether iss is returned in authorization responses
     * @param array<int, string> $tokenEndpointAuthMethodsSupported Supported token endpoint auth methods
     */
    public function __construct(
        public string $issuer,
        public string $authorizationEndpoint,
        public string $tokenEndpoint,
        public ?string $registrationEndpoint = null,
        public array $codeChallengeMethodsSupported = [],
        public bool $authorizationResponseIssParameterSupported = false,
        public array $tokenEndpointAuthMethodsSupported = [],
    ) {
    }

    /**
     * Create metadata from a discovery document payload.
     *
     * @param array<string, mixed> $data Discovery document payload
     * @return self
     */
    public static function fromArray(array $data): self
    {
        if (empty($data['authorization_endpoint']) || empty($data['token_endpoint'])) {
            throw new OAuthException('Authorization server metadata is missing required endpoints.');
        }

        return new self(
            issuer: (string)($data['issuer'] ?? ''),
            authorizationEndpoint: (string)$data['authorization_endpoint'],
            tokenEndpoint: (string)$data['token_endpoint'],
            registrationEndpoint: isset($data['registration_endpoint']) ? (string)$data['registration_endpoint'] : null,
            codeChallengeMethodsSupported: array_values(array_map(strval(...), (array)($data['code_challenge_methods_supported'] ?? []))),
            authorizationResponseIssParameterSupported: (bool)($data['authorization_response_iss_parameter_supported'] ?? false),
            tokenEndpointAuthMethodsSupported: array_values(array_map(strval(...), (array)($data['token_endpoint_auth_methods_supported'] ?? []))),
        );
    }
}
