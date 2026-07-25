<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\OAuth;

use Cake\Utility\Hash;
use Crustum\Mcp\Support\HttpHeaderUtils;

/**
 * Parsed WWW-Authenticate challenge for OAuth protected resources.
 */
class WwwAuthenticateChallenge
{
    /**
     * Create a new WWW-Authenticate challenge.
     *
     * @param string|null $resourceMetadataUrl Protected resource metadata URL
     * @param string|null $error OAuth error code
     * @param string|null $errorDescription OAuth error description
     * @param string|null $scope Requested OAuth scope
     */
    public function __construct(
        public ?string $resourceMetadataUrl = null,
        public ?string $error = null,
        public ?string $errorDescription = null,
        public ?string $scope = null,
    ) {
    }

    /**
     * Parse a WWW-Authenticate header value.
     *
     * @param string|null $header Raw WWW-Authenticate header value
     * @return self
     */
    public static function parse(?string $header): self
    {
        if ($header === null || $header === '') {
            return new self();
        }

        preg_match_all('/([\w-]+)\s*=\s*("[^"]*"|[^,\s]+)/', $header, $matches, PREG_SET_ORDER);

        $params = [];

        foreach ($matches as $match) {
            $params[strtolower($match[1])] = HttpHeaderUtils::unquote($match[2]);
        }

        return new self(
            resourceMetadataUrl: Hash::get($params, 'resource_metadata'),
            error: Hash::get($params, 'error'),
            errorDescription: Hash::get($params, 'error_description'),
            scope: Hash::get($params, 'scope'),
        );
    }
}
