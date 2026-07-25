<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\OAuth;

/**
 * PKCE verifier and challenge pair.
 */
class Pkce
{
    /**
     * Create a PKCE pair.
     *
     * @param string $verifier PKCE code verifier
     * @param string $challenge PKCE code challenge
     */
    public function __construct(
        public string $verifier,
        public string $challenge,
    ) {
    }

    /**
     * Generate a new PKCE pair.
     *
     * @return self
     */
    public static function generate(): self
    {
        $verifier = self::base64Url(random_bytes(64));
        $challenge = self::base64Url(hash('sha256', $verifier, true));

        return new self($verifier, $challenge);
    }

    /**
     * Encode a value using base64url encoding.
     *
     * @param string $value Raw value
     * @return string
     */
    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
