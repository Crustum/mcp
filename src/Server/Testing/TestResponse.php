<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Testing;

use BackedEnum;
use Cake\Collection\Collection;
use Cake\Utility\Hash;
use Closure;
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
        $seeable = new Collection([
            ...$this->content(),
            ...$this->errors(),
        ])->filter()->unique()->toList();

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
        $seeable = new Collection([
            ...$this->content(),
            ...$this->errors(),
        ])->filter()->unique()->toList();

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
     * Assert the number of items in the structured content or at a key.
     *
     * @param int $count Expected item count
     * @param string|null $key Dotted path within the structured content
     * @return static
     */
    public function assertStructuredContentCount(int $count, ?string $key = null): static
    {
        $actual = $this->structuredContentPath($this->requireStructuredContent(), $key);

        Assert::assertCount($count, $actual, "The expected structured content count [{$count}] does not match the actual count.");

        return $this;
    }

    /**
     * Assert the value at a structured content path.
     *
     * @param string $path Dotted path within the structured content
     * @param \Closure(mixed): bool|mixed $expect Expected value or truth test
     * @return static
     */
    public function assertStructuredContentPath(string $path, mixed $expect): static
    {
        $actual = $this->requireStructuredContent();

        $value = $this->structuredContentPath($actual, $path);

        if ($expect instanceof Closure) {
            Assert::assertTrue($expect($value), "The structured content at [{$path}] does not pass the given truth test.");

            return $this;
        }

        Assert::assertSame(
            $expect instanceof BackedEnum ? $expect->value : $expect,
            $value,
            "The expected structured content at [{$path}] does not match the actual value.",
        );

        return $this;
    }

    /**
     * Assert several structured content paths at once.
     *
     * @param array<string, mixed> $paths Paths mapped to expected values
     * @return static
     */
    public function assertStructuredContentPaths(array $paths): static
    {
        foreach ($paths as $path => $expected) {
            $this->assertStructuredContentPath($path, $expected);
        }

        return $this;
    }

    /**
     * Assert the value at a path ignoring order.
     *
     * @param string $path Dotted path within the structured content
     * @param array<array-key, mixed> $expect Expected value
     * @return static
     */
    public function assertStructuredContentPathCanonicalizing(string $path, array $expect): static
    {
        $actual = $this->structuredContentPath($this->requireStructuredContent(), $path);

        Assert::assertEqualsCanonicalizing($expect, $actual, "The expected structured content at [{$path}] does not match the actual value.");

        return $this;
    }

    /**
     * Assert several structured content paths ignoring order.
     *
     * @param array<string, array<array-key, mixed>> $paths Paths mapped to expected values
     * @return static
     */
    public function assertStructuredContentPathsCanonicalizing(array $paths): static
    {
        foreach ($paths as $path => $expected) {
            $this->assertStructuredContentPathCanonicalizing($path, $expected);
        }

        return $this;
    }

    /**
     * Assert the structured content matches exactly, ignoring key order.
     *
     * @param array<array-key, mixed> $data Expected structured content
     * @return static
     */
    public function assertExactStructuredContent(array $data): static
    {
        Assert::assertEquals(
            $this->sortStructure($data, false),
            $this->sortStructure($this->requireStructuredContent(), false),
            'The expected structured content does not match the actual structured content.',
        );

        return $this;
    }

    /**
     * Assert the structured content matches, ignoring order entirely.
     *
     * @param array<array-key, mixed> $data Expected structured content
     * @return static
     */
    public function assertSimilarStructuredContent(array $data): static
    {
        Assert::assertEquals(
            $this->sortStructure($data, true),
            $this->sortStructure($this->requireStructuredContent(), true),
            'The expected structured content does not match the actual structured content.',
        );

        return $this;
    }

    /**
     * Assert the structured content contains the given fragment.
     *
     * @param array<array-key, mixed> $data Expected fragment
     * @return static
     */
    public function assertStructuredContentFragment(array $data): static
    {
        Assert::assertTrue(
            $this->containsFragment($this->requireStructuredContent(), $data),
            'The expected structured content fragment was not found.',
        );

        return $this;
    }

    /**
     * Assert the structured content contains every given fragment.
     *
     * @param array<array-key, array<array-key, mixed>> $data Expected fragments
     * @return static
     */
    public function assertStructuredContentFragments(array $data): static
    {
        foreach ($data as $fragment) {
            $this->assertStructuredContentFragment($fragment);
        }

        return $this;
    }

    /**
     * Assert the structured content does not contain the given fragment.
     *
     * @param array<array-key, mixed> $data Unexpected fragment
     * @param bool $exact Whether the fragment must match exactly
     * @return static
     */
    public function assertStructuredContentMissing(array $data, bool $exact = false): static
    {
        $actual = $this->requireStructuredContent();

        Assert::assertFalse(
            $exact ? $this->containsExact($actual, $data) : $this->containsFragment($actual, $data),
            'The unexpected structured content fragment was found.',
        );

        return $this;
    }

    /**
     * Assert the structured content does not contain the exact fragment.
     *
     * @param array<array-key, mixed> $data Unexpected fragment
     * @return static
     */
    public function assertStructuredContentMissingExact(array $data): static
    {
        if ($this->containsExact($this->requireStructuredContent(), $data)) {
            Assert::fail('The unexpected structured content fragment was found.');
        }

        return $this;
    }

    /**
     * Assert no value exists at the given structured content path.
     *
     * @param string $path Dotted path within the structured content
     * @return static
     */
    public function assertStructuredContentMissingPath(string $path): static
    {
        Assert::assertNull(
            $this->structuredContentPath($this->requireStructuredContent(), $path),
            "The structured content at [{$path}] is not missing.",
        );

        return $this;
    }

    /**
     * Assert no values exist at the given structured content paths.
     *
     * @param array<int, string> $paths Dotted paths within the structured content
     * @return static
     */
    public function assertStructuredContentMissingPaths(array $paths): static
    {
        foreach ($paths as $path) {
            $this->assertStructuredContentMissingPath($path);
        }

        return $this;
    }

    /**
     * Assert the structured content has the given structure.
     *
     * @param array<array-key, mixed>|null $structure Expected structure
     * @param array<array-key, mixed>|null $responseData Structured content to check instead of the response
     * @return static
     */
    public function assertStructuredContentStructure(?array $structure = null, ?array $responseData = null): static
    {
        $this->assertContentStructure($responseData ?? $this->requireStructuredContent(), $structure ?? [], false);

        return $this;
    }

    /**
     * Assert the structured content has exactly the given structure.
     *
     * @param array<array-key, mixed>|null $structure Expected structure
     * @param array<array-key, mixed>|null $responseData Structured content to check instead of the response
     * @return static
     */
    public function assertExactStructuredContentStructure(?array $structure = null, ?array $responseData = null): static
    {
        $this->assertContentStructure($responseData ?? $this->requireStructuredContent(), $structure ?? [], true);

        return $this;
    }

    /**
     * Assert the structured content (or value at a key) is a list.
     *
     * @param string|null $key Dotted path within the structured content
     * @return static
     */
    public function assertStructuredContentIsArray(?string $key = null): static
    {
        $data = $this->structuredContentPath($this->requireStructuredContent(), $key);

        $encoded = json_encode($data, JSON_THROW_ON_ERROR);

        Assert::assertTrue(
            is_array($data)
            && str_starts_with($encoded, '[')
            && str_ends_with($encoded, ']'),
            'The structured content is not an array.',
        );

        return $this;
    }

    /**
     * Assert the structured content (or value at a key) is an object.
     *
     * @param string|null $key Dotted path within the structured content
     * @return static
     */
    public function assertStructuredContentIsObject(?string $key = null): static
    {
        $data = $this->structuredContentPath($this->requireStructuredContent(), $key);

        $encoded = json_encode($data, JSON_THROW_ON_ERROR);

        Assert::assertTrue(
            is_array($data)
            && str_starts_with($encoded, '{')
            && str_ends_with($encoded, '}'),
            'The structured content is not an object.',
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
     * Assert that the primitive was not registered (not found by the server).
     *
     * @return static
     */
    public function assertNotRegistered(): static
    {
        [$primitiveType, $primitiveIdentifier] = match (true) {
            $this->primitive instanceof Tool => ['tool', $this->primitive->name()],
            $this->primitive instanceof Prompt => ['prompt', $this->primitive->name()],
            $this->primitive instanceof Resource => ['resource', $this->primitive->uri()],
            default => throw new RuntimeException('This primitive type is not supported.'),
        };

        Assert::assertTrue(
            count(array_filter($this->errors(), fn(string $error): bool => str_contains($error, ucfirst($primitiveType) . ' [' . $primitiveIdentifier . '] not found.'))) > 0,
            'The ' . $primitiveType . ' [' . $this->primitive::class . '] is registered.',
        );

        return $this;
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
            $this->primitive instanceof Tool => new Collection($this->response->toArray()['result']['content'] ?? [])
                ->map(fn(array $message): string => $message['text'] ?? $message['data'] ?? ''),
            $this->primitive instanceof Prompt => new Collection($this->response->toArray()['result']['messages'] ?? [])
                ->map(fn(array $message): array => $message['content'])
                ->map(fn(array $content): string => $content['text'] ?? $content['data'] ?? ''),
            $this->primitive instanceof Resource => new Collection($this->response->toArray()['result']['contents'] ?? [])
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
     * Require structured content to be present in the response.
     *
     * @return array<string, mixed>
     */
    protected function requireStructuredContent(): array
    {
        $actual = $this->structuredContent();

        Assert::assertNotNull($actual, 'The response does not contain any structured content.');

        return $actual;
    }

    /**
     * Read a value from structured content using dotted path notation.
     *
     * A `*` segment fans out over every item, returning all matches.
     *
     * @param array<string, mixed> $data Structured content
     * @param string|null $key Dotted path, or null for the whole content
     * @return mixed
     */
    protected function structuredContentPath(array $data, ?string $key): mixed
    {
        if ($key === null || $key === '') {
            return $data;
        }

        $segments = explode('.', $key);
        $values = [$data];

        foreach ($segments as $segment) {
            $next = [];

            foreach ($values as $value) {
                if (!is_array($value)) {
                    continue;
                }

                if ($segment === '*') {
                    foreach ($value as $item) {
                        $next[] = $item;
                    }

                    continue;
                }

                if (array_key_exists($segment, $value)) {
                    $next[] = $value[$segment];
                } elseif (is_numeric($segment) && array_key_exists((int)$segment, $value)) {
                    $next[] = $value[(int)$segment];
                }
            }

            $values = $next;
        }

        if (in_array('*', $segments, true)) {
            return $values;
        }

        return $values[0] ?? null;
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

    /**
     * Sort structured content for order-insensitive comparison.
     *
     * @param mixed $value Value to sort
     * @param bool $sortLists Whether list order is insignificant
     * @return mixed
     */
    protected function sortStructure(mixed $value, bool $sortLists): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortStructure($item, $sortLists);
        }

        if (array_is_list($value)) {
            if ($sortLists) {
                usort($value, static fn(mixed $left, mixed $right): int => json_encode($left) <=> json_encode($right));
            }

            return $value;
        }

        ksort($value);

        return $value;
    }

    /**
     * Determine whether structured content contains a fragment.
     *
     * @param mixed $actual Structured content to search
     * @param array<array-key, mixed> $fragment Fragment to find
     * @return bool
     */
    protected function containsFragment(mixed $actual, array $fragment): bool
    {
        if (is_array($actual) && $this->isSubset($fragment, $actual)) {
            return true;
        }

        if (is_array($actual)) {
            foreach ($actual as $item) {
                if ($this->containsFragment($item, $fragment)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determine whether an array contains every fragment entry.
     *
     * @param array<array-key, mixed> $fragment Fragment to find
     * @param array<array-key, mixed> $actual Array to search
     * @return bool
     */
    protected function isSubset(array $fragment, array $actual): bool
    {
        foreach ($fragment as $key => $value) {
            if (!array_key_exists($key, $actual)) {
                return false;
            }

            if (is_array($value) && is_array($actual[$key])) {
                if (!$this->isSubset($value, $actual[$key])) {
                    return false;
                }

                continue;
            }

            if ($actual[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether structured content contains an exact match.
     *
     * @param mixed $actual Structured content to search
     * @param mixed $expected Value to find
     * @return bool
     */
    protected function containsExact(mixed $actual, mixed $expected): bool
    {
        if ($actual == $expected) {
            return true;
        }

        if (is_array($actual)) {
            foreach ($actual as $item) {
                if ($this->containsExact($item, $expected)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Assert structured content matches an expected structure.
     *
     * List specs name required keys; a `*` key applies its spec to every item.
     *
     * @param mixed $actual Structured content to check
     * @param array<array-key, mixed> $structure Expected structure
     * @param bool $exact Whether extra keys fail the assertion
     * @return void
     */
    protected function assertContentStructure(mixed $actual, array $structure, bool $exact): void
    {
        Assert::assertIsArray($actual, 'The structured content structure does not match.');

        if (array_is_list($structure)) {
            foreach ($structure as $requiredKey) {
                Assert::assertArrayHasKey($requiredKey, $actual, 'The structured content structure does not match.');
            }

            if ($exact) {
                $actualKeys = array_keys($actual);
                $expectedKeys = $structure;
                sort($actualKeys);
                sort($expectedKeys);

                Assert::assertSame($expectedKeys, $actualKeys, 'The structured content structure does not match exactly.');
            }

            return;
        }

        foreach ($structure as $key => $nested) {
            if ($key === '*') {
                foreach ($actual as $item) {
                    $this->assertContentStructure($item, $nested, $exact);
                }

                continue;
            }

            Assert::assertArrayHasKey($key, $actual, 'The structured content structure does not match.');
            $this->assertContentStructure($actual[$key], $nested, $exact);
        }

        if ($exact && !array_key_exists('*', $structure)) {
            $extra = array_diff(array_keys($actual), array_keys($structure));

            Assert::assertSame([], array_values($extra), 'The structured content structure does not match exactly.');
        }
    }
}
