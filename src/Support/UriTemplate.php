<?php
declare(strict_types=1);

namespace Crustum\Mcp\Support;

use InvalidArgumentException;
use Stringable;

/**
 * RFC 6570 URI template matcher.
 */
class UriTemplate implements Stringable
{
    /**
     * Maximum template length in characters.
     */
    private const MAX_TEMPLATE_LENGTH = 1_000_000;

    /**
     * Maximum variable name length in characters.
     */
    private const MAX_VARIABLE_LENGTH = 1_000_000;

    /**
     * Maximum number of template expressions.
     */
    private const MAX_TEMPLATE_EXPRESSIONS = 10_000;

    /**
     * Maximum compiled regex length in characters.
     */
    private const MAX_REGEX_LENGTH = 1_000_000;

    /**
     * URI template validation pattern.
     */
    private const URI_TEMPLATE_PATTERN = '/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\/.*{[^{}]+}.*/';

    /**
     * Extracted variable names from the template.
     *
     * @var list<string>
     */
    private array $variableNames = [];

    /**
     * Compiled regex pattern.
     *
     * @var string|null
     */
    private ?string $compiledRegex = null;

    /**
     * Create a new URI template instance.
     *
     * @param string $template URI template string
     */
    public function __construct(private readonly string $template)
    {
        $this->validateLength($template, self::MAX_TEMPLATE_LENGTH, 'Template');

        if (!preg_match(self::URI_TEMPLATE_PATTERN, $template)) {
            throw new InvalidArgumentException('Invalid URI template: must be a valid URI template with at least one placeholder.');
        }

        $this->variableNames = $this->extractVariableNames($template);
    }

    /**
     * Match a URI against the template.
     *
     * @param string $uri URI to match
     * @return array<string, string>|null
     */
    public function match(string $uri): ?array
    {
        $this->validateLength($uri, self::MAX_TEMPLATE_LENGTH, 'URI');
        $this->compiledRegex ??= $this->compileRegex();

        if (!preg_match($this->compiledRegex, $uri, $matches)) {
            return null;
        }

        $result = [];

        foreach ($this->variableNames as $i => $name) {
            $result[$name] = $matches[$i + 1] ?? '';
        }

        return $result;
    }

    /**
     * Get the variable names used by the template.
     *
     * @return list<string>
     */
    public function variableNames(): array
    {
        return array_values(array_unique($this->variableNames));
    }

    /**
     * Convert the template to a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->template;
    }

    /**
     * Validate string length against a maximum.
     *
     * @param string $str String to validate
     * @param int $max Maximum allowed length
     * @param string $context Validation context label
     * @return void
     */
    private function validateLength(string $str, int $max, string $context): void
    {
        if (mb_strlen($str) > $max) {
            throw new InvalidArgumentException(sprintf(
                '%s exceeds the maximum length of %d characters (received %d)',
                $context,
                $max,
                mb_strlen($str),
            ));
        }
    }

    /**
     * Extract variable names from the template.
     *
     * @param string $template URI template string
     * @return list<string>
     */
    private function extractVariableNames(string $template): array
    {
        $expressionCount = 0;
        $names = [];

        if (!preg_match_all('/\{(\w+)}/', $template, $matches)) {
            return [];
        }

        foreach ($matches[1] as $name) {
            $expressionCount++;

            if ($expressionCount > self::MAX_TEMPLATE_EXPRESSIONS) {
                throw new InvalidArgumentException(sprintf(
                    'Template contains too many expressions (max %d)',
                    self::MAX_TEMPLATE_EXPRESSIONS,
                ));
            }

            $this->validateLength($name, self::MAX_VARIABLE_LENGTH, 'Variable name');
            $names[] = $name;
        }

        return $names;
    }

    /**
     * Compile the template into a regex pattern.
     *
     * @return string
     */
    private function compileRegex(): string
    {
        $regexParts = [];
        $segments = preg_split('/(\{\w+})/', $this->template, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($segments === false) {
            throw new InvalidArgumentException('Failed to compile URI template regex: preg_split error');
        }

        foreach ($segments as $segment) {
            $isVariable = preg_match('/^\{(\w+)}$/', $segment);

            if ($isVariable === false) {
                throw new InvalidArgumentException('Failed to validate template segment: preg_match error');
            }

            $regexParts[] = $isVariable === 1 ? '([^/]+)' : preg_quote($segment, '#');
        }

        $pattern = '#^' . implode('', $regexParts) . '$#';
        $this->validateLength($pattern, self::MAX_REGEX_LENGTH, 'Generated regex pattern');

        return $pattern;
    }
}
