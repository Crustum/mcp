<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Testing;

use Cake\Collection\Collection;
use Cake\Utility\Hash;
use Crustum\Mcp\Server\Primitive;
use Crustum\Mcp\Server\Prompt;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Transport\JsonRpcResponse;
use PHPUnit\Framework\Assert;
use RuntimeException;

/**
 * MCP server test response assertion helper.
 */
class TestResponse
{
    /**
     * @var \Crustum\Mcp\Transport\JsonRpcResponse
     */
    protected JsonRpcResponse $response;

    /**
     * @var array<int, \Crustum\Mcp\Transport\JsonRpcResponse>
     */
    protected array $notifications = [];

    /**
     * @param \Crustum\Mcp\Server\Primitive $primitive Primitive under test
     * @param \Crustum\Mcp\Transport\JsonRpcResponse|iterable<int, \Crustum\Mcp\Transport\JsonRpcResponse> $response Response payload
     * @param mixed|null $actingAs Authenticated principal under test
     */
    public function __construct(
        protected Primitive $primitive,
        iterable|JsonRpcResponse $response,
        protected mixed $actingAs = null,
    ) {
        $responses = is_iterable($response)
            ? iterator_to_array($response)
            : [$response];

        foreach ($responses as $item) {
            $content = $item->toArray();

            if (isset($content['id'])) {
                $this->response = $item;
            } else {
                $this->notifications[] = $item;
            }
        }
    }

    /**
     * Assert that a user is authenticated for the test request.
     *
     * @return static
     */
    public function assertAuthenticated(): static
    {
        Assert::assertNotNull($this->actingAs, 'The user is not authenticated.');

        return $this;
    }

    /**
     * Assert that no user is authenticated for the test request.
     *
     * @return static
     */
    public function assertGuest(): static
    {
        Assert::assertNull($this->actingAs, 'The user is authenticated.');

        return $this;
    }

    /**
     * Assert that the authenticated user matches the expected user.
     *
     * @param mixed $principal Expected authenticated principal
     * @return static
     */
    public function assertAuthenticatedAs(mixed $principal): static
    {
        Assert::assertSame(
            $principal,
            $this->actingAs,
            'The authenticated principal does not match the expected principal.',
        );

        return $this;
    }

    /**
     * Assert that the response contains the given text segments.
     *
     * @param array<string>|string $text Expected text segments
     * @return static
     */
    public function assertSee(array|string $text): static
    {
        $seeable = (new Collection([
            ...$this->content(),
            ...$this->errors(),
        ]))->filter()->unique()->toList();

        foreach (is_array($text) ? $text : [$text] as $segment) {
            foreach ($seeable as $message) {
                if (str_contains($message, $segment)) {
                    continue 2;
                }
            }

            Assert::fail("The expected text [{$segment}] was not found in the response content.");
        }

        return $this;
    }

    /**
     * Assert that the response does not contain the given text segments.
     *
     * @param array<string>|string $text Unexpected text segments
     * @return static
     */
    public function assertDontSee(array|string $text): static
    {
        $seeable = (new Collection([
            ...$this->content(),
            ...$this->errors(),
        ]))->filter()->unique()->toList();

        foreach (is_array($text) ? $text : [$text] as $segment) {
            foreach ($seeable as $message) {
                if (str_contains($message, $segment)) {
                    Assert::fail("The unexpected text [{$segment}] was found in the response content.");
                }
            }
        }

        return $this;
    }

    /**
     * Assert structured content in the response.
     *
     * @param array<string, mixed> $structuredContent Expected structured content
     * @return static
     */
    public function assertStructuredContent(array $structuredContent): static
    {
        $actual = $this->structuredContent();

        Assert::assertSame(
            $this->toJsonRepresentation($structuredContent),
            $actual,
            'The expected structured content does not match the actual structured content.',
        );

        return $this;
    }

    /**
     * Assert the number of notifications in the response stream.
     *
     * @param int $count Expected notification count
     * @return static
     */
    public function assertNotificationCount(int $count): static
    {
        Assert::assertCount($count, $this->notifications, "The expected number of notifications [{$count}] does not match the actual count.");

        return $this;
    }

    /**
     * Assert that a notification was sent.
     *
     * @param string $method Notification method name
     * @param array<string, mixed>|null $params Expected notification parameters
     * @return static
     */
    public function assertSentNotification(string $method, ?array $params = null): static
    {
        foreach ($this->notifications as $notification) {
            $content = $notification->toArray();

            if ($content['method'] === $method && (is_array($params) === false || $this->toJsonRepresentation($content['params']) === $this->toJsonRepresentation($params))) {
                return $this;
            }
        }

        Assert::fail("The expected notification [{$method}], but it was not found.");
    }

    /**
     * Assert the primitive name.
     *
     * @param string $name Expected primitive name
     * @return static
     */
    public function assertName(string $name): static
    {
        Assert::assertEquals(
            $name,
            $this->primitive->name(),
            "The expected name [{$name}] does not match the actual name [{$this->primitive->name()}].",
        );

        return $this;
    }

    /**
     * Assert the primitive title.
     *
     * @param string $title Expected primitive title
     * @return static
     */
    public function assertTitle(string $title): static
    {
        Assert::assertEquals(
            $title,
            $this->primitive->title(),
            "The expected title [{$title}] does not match the actual title [{$this->primitive->title()}].",
        );

        return $this;
    }

    /**
     * Assert the primitive description.
     *
     * @param string $description Expected primitive description
     * @return static
     */
    public function assertDescription(string $description): static
    {
        Assert::assertEquals(
            $description,
            $this->primitive->description(),
            "The expected description [{$description}] does not match the actual description [{$this->primitive->description()}].",
        );

        return $this;
    }

    /**
     * Assert that the response has no errors.
     *
     * @return static
     */
    public function assertOk(): static
    {
        return $this->assertHasNoErrors();
    }

    /**
     * Assert that the response has no errors.
     *
     * @return static
     */
    public function assertHasNoErrors(): static
    {
        Assert::assertSame([], $this->errors(), 'The response has errors.');

        return $this;
    }

    /**
     * Assert that the response contains expected error messages.
     *
     * @param array<string> $messages Expected error message fragments
     * @return static
     */
    public function assertHasErrors(array $messages = []): static
    {
        $errors = $this->errors();

        Assert::assertNotEmpty($errors, 'The response has no errors.');

        foreach ($messages as $message) {
            foreach ($errors as $error) {
                if (str_contains($error, $message)) {
                    continue 2;
                }
            }

            Assert::fail("The expected error message [{$message}] was not found in the response.");
        }

        return $this;
    }

    /**
     * Assert that expected completion values are present.
     *
     * @param array<int, string> $expectedValues Expected completion values
     * @return static
     */
    public function assertHasCompletions(array $expectedValues = []): static
    {
        $actualValues = $this->completionValues();

        Assert::assertNotNull(
            $this->response->toArray()['result']['completion'] ?? null,
            'No completion data found in response.',
        );

        foreach ($expectedValues as $expected) {
            Assert::assertContains(
                $expected,
                $actualValues,
                "Expected completion value [{$expected}] not found.",
            );
        }

        return $this;
    }

    /**
     * Assert the exact completion values in the response.
     *
     * @param array<int, string> $values Expected completion values
     * @return static
     */
    public function assertCompletionValues(array $values): static
    {
        Assert::assertEquals(
            $values,
            $this->completionValues(),
            'Completion values do not match expected values.',
        );

        return $this;
    }

    /**
     * Assert the number of completion values in the response.
     *
     * @param int $count Expected completion count
     * @return static
     */
    public function assertCompletionCount(int $count): static
    {
        $values = $this->completionValues();

        Assert::assertCount(
            $count,
            $values,
            "Expected {$count} completions, but got " . count($values),
        );

        return $this;
    }

    /**
     * Dump the response payload and halt execution.
     *
     * @return void
     */
    public function dd(): void
    {
        dd($this->response->toArray());
    }

    /**
     * Dump the response payload.
     *
     * @return static
     */
    public function dump(): static
    {
        fwrite(STDERR, print_r($this->response->toArray(), true) . PHP_EOL);

        return $this;
    }

    /**
     * Dump response errors and halt execution.
     *
     * @return void
     */
    public function ddErrors(): void
    {
        dd($this->errors());
    }

    /**
     * Extract readable content strings from the response.
     *
     * @return array<int, string>
     */
    protected function content(): array
    {
        return (match (true) {
            $this->primitive instanceof Tool => (new Collection($this->response->toArray()['result']['content'] ?? []))
                ->map(fn(array $message): string => $message['text'] ?? $message['data'] ?? ''),
            $this->primitive instanceof Prompt => (new Collection($this->response->toArray()['result']['messages'] ?? []))
                ->map(fn(array $message): array => $message['content'])
                ->map(fn(array $content): string => $content['text'] ?? $content['data'] ?? ''),
            $this->primitive instanceof Resource => (new Collection($this->response->toArray()['result']['contents'] ?? []))
                ->map(fn(array $item): string => $item['text'] ?? $item['blob'] ?? ''),
            default => throw new RuntimeException('This primitive type is not supported.'),
        })->filter()->unique()->toList();
    }

    /**
     * Extract error messages from the response.
     *
     * @return array<int, string>
     */
    protected function errors(): array
    {
        $response = $this->response->toArray();

        if (Hash::get($response, 'result.isError', false)) {
            return $this->content();
        }

        if (array_key_exists('error', $response)) {
            return [$response['error']['message']];
        }

        return [];
    }

    /**
     * Extract completion values from the response.
     *
     * @return array<int, string>
     */
    protected function completionValues(): array
    {
        $response = $this->response->toArray();

        return $response['result']['completion']['values'] ?? [];
    }

    /**
     * Resolve structured content from the response after JSON serialization.
     *
     * @return array<string, mixed>|null
     */
    protected function structuredContent(): ?array
    {
        $structuredContent = $this->response->toArray()['result']['structuredContent'] ?? null;

        if (is_array($structuredContent) === false) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = $this->toJsonRepresentation($structuredContent);

        return $decoded;
    }

    /**
     * Normalize a value through JSON encode/decode for assertion comparison.
     *
     * @param mixed $value Value to normalize
     * @return mixed
     */
    protected function toJsonRepresentation(mixed $value): mixed
    {
        return json_decode(
            json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
