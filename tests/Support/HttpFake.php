<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Support;

use Cake\Http\Client;
use Cake\Http\Client\Exception\NetworkException;
use Cake\Http\Client\Response;
use PHPUnit\Framework\AssertionFailedError;
use Psr\Http\Message\RequestInterface;

/**
 * HTTP fake built on Cake HttpClient mock responses.
 */
class HttpFake
{
    /**
     * Recorded outbound HTTP requests.
     *
     * @var array<int, \Crustum\Mcp\Test\Support\HttpFakeRequest>
     */
    public static array $recorded = [];

    /**
     * Clear mocked responses and recorded requests.
     *
     * @return void
     */
    public static function clear(): void
    {
        Client::clearMockResponses();
        static::$recorded = [];
    }

    /**
     * Build a mock HTTP response.
     *
     * @param mixed $body Response body
     * @param int $status HTTP status code
     * @param array<string, string> $headers Response headers
     * @return \Cake\Http\Client\Response
     */
    public static function response(mixed $body = '', int $status = 200, array $headers = []): Response
    {
        if (is_array($body)) {
            $body = json_encode($body);
            $headers = array_merge(['Content-Type' => 'application/json'], $headers);
        }

        $headerLines = array_merge(["HTTP/1.1 {$status}"], static::formatHeaders($headers));

        return new Response($headerLines, (string)$body);
    }

    /**
     * Register mocked HTTP responses.
     *
     * @param array<string, \Cake\Http\Client\Response> $map URL map
     * @return void
     */
    public static function fake(array $map = []): void
    {
        static::clear();

        foreach ($map as $url => $response) {
            static::register($url, $response);
        }
    }

    /**
     * Begin a sequential mock response queue.
     *
     * @return \Crustum\Mcp\Test\Support\HttpFakeSequence
     */
    public static function fakeSequence(): HttpFakeSequence
    {
        static::clear();

        return new HttpFakeSequence();
    }

    /**
     * Register a handler that throws for DELETE requests and a response for others.
     *
     * @param \Cake\Http\Client\Response $response Response for non-DELETE requests
     * @param string $message Network failure message
     * @return void
     */
    public static function failDeleteRequests(Response $response, string $message = 'connection lost'): void
    {
        static::clear();

        static::register('*', $response);

        Client::addMockResponse('DELETE', '*', static::response(), [
            'match' => static function (RequestInterface $request) use ($message): bool {
                static::record($request);

                throw new NetworkException($message, $request);
            },
        ]);
    }

    /**
     * Register a handler that always throws a network exception.
     *
     * @param string $message Network failure message
     * @return void
     */
    public static function throwNetworkException(string $message = 'timed out'): void
    {
        static::clear();

        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            Client::addMockResponse($method, '*', static::response(), [
                'match' => static function (RequestInterface $request) use ($message): bool {
                    static::record($request);

                    throw new NetworkException($message, $request);
                },
            ]);
        }
    }

    /**
     * Assert that at least one request satisfied the callback.
     *
     * @param callable(\Crustum\Mcp\Test\Support\HttpFakeRequest|array<string, mixed>): bool $callback Request matcher
     * @return void
     */
    public static function assertSent(callable $callback): void
    {
        foreach (static::$recorded as $request) {
            if ($callback($request)) {
                return;
            }
        }

        throw new AssertionFailedError('An expected HTTP request was not recorded.');
    }

    /**
     * Assert that no request satisfied the callback.
     *
     * @param callable(\Crustum\Mcp\Test\Support\HttpFakeRequest): bool $callback Request matcher
     * @return void
     */
    public static function assertNotSent(callable $callback): void
    {
        foreach (static::$recorded as $request) {
            if ($callback($request)) {
                throw new AssertionFailedError('An unexpected HTTP request was recorded.');
            }
        }
    }

    /**
     * Assert that no HTTP requests were recorded.
     *
     * @return void
     */
    public static function assertNothingSent(): void
    {
        if (static::$recorded !== []) {
            throw new AssertionFailedError('Unexpected HTTP requests were recorded.');
        }
    }

    /**
     * Register a mock response for all HTTP methods.
     *
     * @param string $url URL or wildcard pattern
     * @param \Cake\Http\Client\Response $response Mock response
     * @return void
     */
    public static function register(string $url, Response $response): void
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            Client::addMockResponse($method, $url, $response, [
                'match' => static function (RequestInterface $request) use ($method): bool {
                    if ($request->getMethod() !== $method) {
                        return false;
                    }

                    static::record($request);

                    return true;
                },
            ]);
        }
    }

    /**
     * Record an outbound HTTP request.
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @return void
     */
    public static function record(RequestInterface $request): void
    {
        static::$recorded[] = HttpFakeRequest::from($request);
    }

    /**
     * Format header map into HTTP header lines.
     *
     * @param array<string, string> $headers Header map
     * @return array<int, string>
     */
    protected static function formatHeaders(array $headers): array
    {
        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }

        return $lines;
    }
}

/**
 * Sequential HTTP fake response builder.
 */
final class HttpFakeSequence
{
    /**
     * Queued mock responses.
     *
     * @var array<int, \Cake\Http\Client\Response>
     */
    protected array $responses = [];

    /**
     * Fallback response when the queue is exhausted.
     *
     * @var \Cake\Http\Client\Response|null
     */
    protected ?Response $emptyResponse = null;

    /**
     * Push a response onto the sequence.
     *
     * @param mixed $body Response body
     * @param int $status HTTP status code
     * @param array<string, string> $headers Response headers
     * @return self
     */
    public function push(mixed $body = '', int $status = 200, array $headers = []): self
    {
        $this->responses[] = HttpFake::response($body, $status, $headers);

        return $this;
    }

    /**
     * Set the fallback response when the sequence is exhausted.
     *
     * @param \Cake\Http\Client\Response $response Fallback response
     * @return self
     */
    public function whenEmpty(Response $response): self
    {
        $this->emptyResponse = $response;

        return $this;
    }

    /**
     * Register the sequence with the HTTP client mock adapter.
     *
     * @return void
     */
    public function register(): void
    {
        foreach ($this->responses as $response) {
            HttpFake::register('*', $response);
        }

        if ($this->emptyResponse instanceof Response) {
            HttpFake::register('*', $this->emptyResponse);
        }

        $this->registered = true;
    }

    /**
     * Register pending responses when the builder is discarded.
     */
    public function __destruct()
    {
        if (!$this->registered && $this->responses !== []) {
            $this->register();
        }
    }

    /**
     * Whether the sequence has been registered.
     *
     * @var bool
     */
    protected bool $registered = false;
}
