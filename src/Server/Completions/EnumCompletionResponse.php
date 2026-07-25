<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Completions;

use BackedEnum;
use InvalidArgumentException;
use UnitEnum;

/**
 * Completion response backed by a PHP enum class.
 */
class EnumCompletionResponse extends CompletionResponse
{
    /**
     * @param class-string<\UnitEnum> $enumClass Enum class name
     */
    public function __construct(private string $enumClass)
    {
        if (!enum_exists($enumClass)) {
            throw new InvalidArgumentException("Class [{$enumClass}] is not an enum.");
        }

        parent::__construct([]);
    }

    /**
     * Resolve completion values for the current partial input.
     *
     * @param string $value Current partial value
     * @return \Crustum\Mcp\Server\Completions\CompletionResponse
     */
    public function resolve(string $value): CompletionResponse
    {
        $enumValues = array_map(
            fn(UnitEnum $case): string => $case instanceof BackedEnum ? (string)$case->value : $case->name,
            $this->enumClass::cases(),
        );

        $filtered = CompletionHelper::filterByPrefix($enumValues, $value);
        $hasMore = count($filtered) > static::MAX_VALUES;
        $truncated = array_slice($filtered, 0, static::MAX_VALUES);

        return new DirectCompletionResponse($truncated, $hasMore);
    }
}
