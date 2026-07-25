<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\OAuth;

use SensitiveParameter;

/**
 * OAuth client configuration.
 */
class OAuthConfig
{
    /**
     * Create OAuth client configuration.
     *
     * @param string|null $clientId OAuth client identifier
     * @param string|null $clientSecret OAuth client secret
     * @param string|null $scope Requested OAuth scope
     * @param string|null $redirectUri OAuth redirect URI
     */
    public function __construct(
        public ?string $clientId = null,
        #[SensitiveParameter]
        public ?string $clientSecret = null,
        public ?string $scope = null,
        public ?string $redirectUri = null,
    ) {
    }
}
