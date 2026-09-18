<?php
declare(strict_types=1);

namespace Crustum\Mcp\Transport;

use Stringable;

/**
 * MCP protocol header value with base64 sentinel encoding.
 */
final class HeaderValue implements Stringable
{
    /**
     * Base64 sentinel prefix.
     */
    protected const BASE64_PREFIX = '=?base64?';

    /**
     * Base64 sentinel suffix.
     */
    protected const BASE64_SUFFIX = '?=';

    /**
     * @param string $value Decoded header value
     */
    public function __construct(public readonly string $value)
    {
    }

    /**
     * Decode a header value carrying the base64 sentinel.
     *
     * @param string $header Raw header value
     * @return static
     */
    public static function fromHeader(string $header): self
    {
        if (!self::isBase64($header)) {
            return new self($header);
        }

        $decoded = base64_decode(substr(
            $header,
            strlen(self::BASE64_PREFIX),
            -strlen(self::BASE64_SUFFIX),
        ), true);

        return new self($decoded === false ? $header : $decoded);
    }

    /**
     * Whether the value matches the expected protocol value.
     *
     * @param string $value Expected value
     * @return bool
     */
    public function matches(string $value): bool
    {
        return $this->value === $value;
    }

    /**
     * Encode the value, applying the base64 sentinel when needed.
     *
     * @return string
     */
    public function __toString(): string
    {
        if (!self::isBase64($this->value) && preg_match('/^[\x21-\x7E]([\x20-\x7E\x09]*[\x21-\x7E])?\z/', $this->value) === 1) {
            return $this->value;
        }

        return self::BASE64_PREFIX . base64_encode($this->value) . self::BASE64_SUFFIX;
    }

    /**
     * Whether a value already carries the base64 sentinel.
     *
     * @param string $value Candidate value
     * @return bool
     */
    protected static function isBase64(string $value): bool
    {
        return str_starts_with($value, self::BASE64_PREFIX) && str_ends_with($value, self::BASE64_SUFFIX);
    }
}
