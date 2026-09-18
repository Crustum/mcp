<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Testing;

use PHPUnit\Framework\Assert;

/**
 * Response from listing server primitives (tools, resources, or prompts).
 */
class TestListResponse
{
    /**
     * Create a new test list response.
     *
     * @param array<int, array<string, mixed>> $items Listed primitive data
     */
    public function __construct(
        protected array $items,
    ) {
    }

    /**
     * Assert that the listed primitives contain the given classes.
     *
     * @param array<class-string<\Crustum\Mcp\Server\Primitive>>|class-string<\Crustum\Mcp\Server\Primitive> $classes Primitive classes to check
     */
    public function assertRegistered(array|string $classes): static
    {
        $itemNames = array_column($this->items, 'name');
        $classes = is_array($classes) ? $classes : [$classes];

        foreach ($classes as $class) {
            $name = (new $class())->name();

            Assert::assertContains($name, $itemNames, "The expected class [{$class}] was not found in the response content.");
        }

        return $this;
    }

    /**
     * Assert that the listed primitives do not contain the given classes.
     *
     * @param array<class-string<\Crustum\Mcp\Server\Primitive>>|class-string<\Crustum\Mcp\Server\Primitive> $classes Primitive classes to check
     */
    public function assertNotRegistered(array|string $classes): static
    {
        $itemNames = array_column($this->items, 'name');
        $classes = is_array($classes) ? $classes : [$classes];

        foreach ($classes as $class) {
            $name = (new $class())->name();

            Assert::assertNotContains($name, $itemNames, "The expected class [{$class}] was found in the response content.");
        }

        return $this;
    }

    /**
     * Get the raw listed items.
     *
     * @return array<int, array<string, mixed>>
     */
    public function items(): array
    {
        return $this->items;
    }
}
