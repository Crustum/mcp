<?php
declare(strict_types=1);

use Cake\Validation\Validator;
use Crustum\Mcp\Exception\ValidationException;
use Crustum\Mcp\Support\ValidationMessages;

test('validates data with a CakePHP validator', function (): void {
    $validator = new Validator();
    $validator
        ->notBlank('name', 'The name field is required.')
        ->email('email', false, 'The email field must be a valid email address.');

    try {
        ValidationMessages::validate(
            ['name' => '', 'email' => 'invalid-email'],
            $validator,
        );

        expect(false)->toBeTrue('Expected ValidationException was not thrown.');
    } catch (ValidationException $validationException) {
        $messages = ValidationMessages::from($validationException);

        expect($messages)->toContain('The name field is required.');
        expect($messages)->toContain('The email field must be a valid email address.');
    }
});

test('returns validated fields on success', function (): void {
    $validator = new Validator();
    $validator
        ->email('email', false, 'The email field must be a valid email address.');

    $validated = ValidationMessages::validate(
        ['email' => 'alice@example.com', 'extra' => 'ignored'],
        $validator,
    );

    expect($validated)->toBe([
        'email' => 'alice@example.com',
    ]);
});

test('returns a generic message if no messages are available', function (): void {
    $exception = new ValidationException([]);

    $messages = ValidationMessages::from($exception);

    expect($messages)->toBe('The given data was invalid.');
});

test('fromErrors implodes field messages in order', function (): void {
    $messages = ValidationMessages::fromErrors([
        'name' => ['The name field is required.'],
        'email' => ['The email field must be a valid email address.'],
    ]);

    expect($messages)->toBe('The name field is required. The email field must be a valid email address.');
});
