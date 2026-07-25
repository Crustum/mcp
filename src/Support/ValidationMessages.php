<?php
declare(strict_types=1);

namespace Crustum\Mcp\Support;

use Cake\Validation\Validator;
use Crustum\Mcp\Exception\ValidationException;

/**
 * Validates MCP request data using CakePHP validation.
 */
class ValidationMessages
{
    /**
     * Validate data and return validated fields when validation passes.
     *
     * @param array<string, mixed> $data Data to validate
     * @param \Cake\Validation\Validator $validator CakePHP validator
     * @return array<string, mixed>
     * @throws \Crustum\Mcp\Exception\ValidationException
     */
    public static function validate(array $data, Validator $validator): array
    {
        $errors = $validator->validate($data);

        if ($errors !== []) {
            $normalizedErrors = static::normalizeErrors($errors);

            throw new ValidationException($normalizedErrors, static::fromErrors($normalizedErrors));
        }

        $fieldNames = static::fieldNames($validator);

        if ($fieldNames === []) {
            return $data;
        }

        return array_intersect_key($data, array_flip($fieldNames));
    }

    /**
     * Build a validation message from a validation exception.
     *
     * @param \Crustum\Mcp\Exception\ValidationException $exception Validation exception
     * @return string
     */
    public static function from(ValidationException $exception): string
    {
        return static::fromErrors($exception->errors());
    }

    /**
     * Build a single validation message from validator errors.
     *
     * @param array<string, array<int, string>> $errors Validation errors
     * @return string
     */
    public static function fromErrors(array $errors): string
    {
        $messages = [];

        foreach ($errors as $fieldErrors) {
            foreach ($fieldErrors as $message) {
                $messages[] = $message;
            }
        }

        return $messages === [] ? 'The given data was invalid.' : implode(' ', $messages);
    }

    /**
     * Get field names configured on a validator.
     *
     * @param \Cake\Validation\Validator $validator CakePHP validator
     * @return array<int, string>
     */
    public static function fieldNames(Validator $validator): array
    {
        return array_map(strval(...), array_keys(iterator_to_array($validator)));
    }

    /**
     * Normalize CakePHP validator errors to a flat message list per field.
     *
     * @param array<string, array<string, string>> $errors Raw validator errors
     * @return array<string, array<int, string>>
     */
    protected static function normalizeErrors(array $errors): array
    {
        $normalized = [];

        foreach ($errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $message) {
                $normalized[$field][] = $message;
            }
        }

        return $normalized;
    }
}
