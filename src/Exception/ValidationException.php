<?php
declare(strict_types=1);

namespace Crustum\Mcp\Exception;

use Exception;

/**
 * MCP request validation exception.
 */
class ValidationException extends Exception
{
    /**
     * @param array<string, array<int, string>> $errors Validation errors
     * @param string $message Exception message
     */
    public function __construct(
        protected array $errors,
        string $message = 'The given data was invalid.',
    ) {
        parent::__construct($message);
    }

    /**
     * Get validation errors.
     *
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
