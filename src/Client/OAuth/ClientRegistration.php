<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\OAuth;

use SensitiveParameter;

/**
 * Dynamic OAuth client registration result.
 */
class ClientRegistration
{
    /**
     * Create a client registration result.
     *
     * @param string $clientId Registered client identifier
     * @param string|null $clientSecret Registered client secret
     */
    public function __construct(
        public string $clientId,
        #[SensitiveParameter]
        public ?string $clientSecret = null,
    ) {
    }
}
