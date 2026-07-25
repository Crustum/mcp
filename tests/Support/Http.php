<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Support;

use Cake\Http\Client\Response;

/**
 * Static helpers for HTTP client mock registration and assertions in tests.
 */
final class Http
{
    /**
     * Register mocked HTTP responses.
     *
     * @param array<string, \Cake\Http\Client\Response> $map URL map
     * @return void
     */
    public static function fake(array $map = []): void
    {
        HttpFake::fake($map);
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
        return HttpFake::response($body, $status, $headers);
    }

    /**
     * Begin a sequential mock response queue.
     *
     * @return \Crustum\Mcp\Test\Support\HttpFakeSequence
     */
    public static function fakeSequence(): HttpFakeSequence
    {
        return HttpFake::fakeSequence();
    }

    /**
     * Assert that at least one request satisfied the callback.
     *
     * @param callable(\Crustum\Mcp\Test\Support\HttpFakeRequest|array<string, mixed>): bool $callback Request matcher
     * @return void
     */
    public static function assertSent(callable $callback): void
    {
        HttpFake::assertSent($callback);
    }

    /**
     * Assert that no request satisfied the callback.
     *
     * @param callable(\Crustum\Mcp\Test\Support\HttpFakeRequest): bool $callback Request matcher
     * @return void
     */
    public static function assertNotSent(callable $callback): void
    {
        HttpFake::assertNotSent($callback);
    }

    /**
     * Assert that no HTTP requests were recorded.
     *
     * @return void
     */
    public static function assertNothingSent(): void
    {
        HttpFake::assertNothingSent();
    }
}
