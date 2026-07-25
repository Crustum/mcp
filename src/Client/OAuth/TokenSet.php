<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\OAuth;

use SensitiveParameter;

/**
 * OAuth token response.
 */
class TokenSet
{
    /**
     * Create an OAuth token set.
     *
     * @param string $accessToken Access token
     * @param string|null $refreshToken Refresh token
     * @param int|null $expiresAt Expiration timestamp
     * @param string $tokenType Token type
     * @param string|null $scope Granted scope
     * @param string|null $clientId OAuth client identifier
     * @param string|null $clientSecret OAuth client secret
     */
    public function __construct(
        #[SensitiveParameter]
        public string $accessToken,
        #[SensitiveParameter]
        public ?string $refreshToken = null,
        public ?int $expiresAt = null,
        public string $tokenType = 'Bearer',
        public ?string $scope = null,
        public ?string $clientId = null,
        #[SensitiveParameter]
        public ?string $clientSecret = null,
    ) {
    }

    /**
     * Create a token set from an OAuth token response payload.
     *
     * @param array<string, mixed> $data Token response payload
     * @return self
     */
    public static function fromResponse(array $data): self
    {
        $expiresIn = isset($data['expires_in']) ? (int)$data['expires_in'] : null;

        return new self(
            accessToken: (string)($data['access_token'] ?? ''),
            refreshToken: isset($data['refresh_token']) ? (string)$data['refresh_token'] : null,
            expiresAt: $expiresIn !== null ? time() + $expiresIn : null,
            tokenType: (string)($data['token_type'] ?? 'Bearer'),
            scope: isset($data['scope']) ? (string)$data['scope'] : null,
        );
    }
}
