<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Support;

use ArrayAccess;
use Psr\Http\Message\RequestInterface;

/**
 * Recorded HTTP request for test assertions.
 */
class HttpFakeRequest implements ArrayAccess
{
    /**
     * Parsed request payload cache.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $parsedData = null;

    /**
     * Create a recorded request wrapper.
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array<string, array<int, string>> $headers Request headers
     * @param string $body Request body
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers,
        public string $body,
    ) {
    }

    /**
     * Build a recorded request from a PSR-7 request.
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @return self
     */
    public static function from(RequestInterface $request): self
    {
        $body = (string)$request->getBody();
        $request->getBody()->rewind();

        return new self(
            method: $request->getMethod(),
            url: (string)$request->getUri(),
            headers: $request->getHeaders(),
            body: $body,
        );
    }

    /**
     * Determine whether a header is present with an optional value.
     *
     * @param string $name Header name
     * @param string|null $value Expected header value
     * @return bool
     */
    public function hasHeader(string $name, ?string $value = null): bool
    {
        foreach ($this->headers as $headerName => $values) {
            if (strcasecmp($headerName, $name) !== 0) {
                continue;
            }

            if ($value === null) {
                return true;
            }

            foreach ($values as $headerValue) {
                if ($headerValue === $value) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get header values by name.
     *
     * @param string $name Header name
     * @return array<int, string>
     */
    public function header(string $name): array
    {
        foreach ($this->headers as $headerName => $values) {
            if (strcasecmp($headerName, $name) === 0) {
                return $values;
            }
        }

        return [];
    }

    /**
     * Get the HTTP method in lowercase form for legacy assertions.
     *
     * @return string
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Parse JSON or form-encoded request data.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        if ($this->parsedData !== null) {
            return $this->parsedData;
        }

        if ($this->body === '') {
            return $this->parsedData = [];
        }

        $trimmed = ltrim($this->body);

        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            $decoded = json_decode($this->body, true);

            return $this->parsedData = is_array($decoded) ? $decoded : [];
        }

        parse_str($this->body, $parsed);

        return $this->parsedData = is_array($parsed) ? $parsed : [];
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        if ($name === 'method') {
            return $this->method;
        }

        return null;
    }

    /**
     * @param string $name
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        if ($name === 'headers') {
            return $this->headers;
        }

        if ($name === 'url') {
            return $this->url;
        }

        if ($name === 'hasHeader') {
            return $this->hasHeader($arguments[0], $arguments[1] ?? null);
        }

        if ($name === 'header') {
            return $this->header($arguments[0]);
        }

        if ($name === 'data') {
            return $this->data();
        }

        return null;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists((string)$offset, $this->data());
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->data()[(string)$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
    }

    /**
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
    }
}
