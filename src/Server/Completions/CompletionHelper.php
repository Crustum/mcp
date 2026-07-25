<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Completions;

/**
 * Completion suggestion filtering helpers.
 */
class CompletionHelper
{
    /**
     * Filter completion values by prefix match.
     *
     * @param array<int, string> $items Completion values
     * @param string $prefix Prefix to match
     * @return array<int, string>
     */
    public static function filterByPrefix(array $items, string $prefix): array
    {
        if ($prefix === '') {
            return $items;
        }

        $prefixLower = strtolower($prefix);

        return array_values(array_filter(
            $items,
            fn(string $item): bool => str_starts_with(strtolower($item), $prefixLower),
        ));
    }
}
