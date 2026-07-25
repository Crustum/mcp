<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Exception;

use Crustum\Mcp\Client\OAuth\WwwAuthenticateChallenge;

/**
 * Exception thrown when MCP server requires OAuth authorization.
 */
class AuthorizationRequiredException extends OAuthException
{
    /**
     * Create a new authorization required exception.
     *
     * @param string $message Exception message
     * @param \Crustum\Mcp\Client\OAuth\WwwAuthenticateChallenge|null $challenge Parsed WWW-Authenticate challenge
     */
    public function __construct(
        string $message = 'Authorization is required to access the MCP server.',
        public ?WwwAuthenticateChallenge $challenge = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Get the OAuth protected resource metadata URL.
     *
     * @return string|null
     */
    public function resourceMetadataUrl(): ?string
    {
        return $this->challenge?->resourceMetadataUrl;
    }

    /**
     * Get the requested OAuth scope.
     *
     * @return string|null
     */
    public function scope(): ?string
    {
        return $this->challenge?->scope;
    }

    /**
     * Build OAuth query parameters from the challenge.
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        return array_filter([
            'resource_metadata' => $this->resourceMetadataUrl(),
            'scope' => $this->scope(),
        ], static fn(?string $value): bool => $value !== null && $value !== '');
    }
}
