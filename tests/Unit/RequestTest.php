<?php
declare(strict_types=1);

use Cake\Validation\Validator;
use Crustum\Mcp\Exception\ValidationException;
use Crustum\Mcp\Request;

it('may return all data', function (): void {
    $request = new Request([
        'name' => 'Alice',
        'age' => 30,
        'city' => 'Wonderland',
    ]);

    expect($request->all())->toBe([
        'name' => 'Alice',
        'age' => 30,
        'city' => 'Wonderland',
    ]);
});

it('may return specific set of keys', function (): void {
    $request = new Request([
        'name' => 'Alice',
        'age' => 30,
        'city' => 'Wonderland',
    ]);

    expect($request->all(['name', 'age']))->toBe([
        'name' => 'Alice',
        'age' => 30,
    ])->and($request->all('name'))->toBe([
        'name' => 'Alice',
    ]);
});

it('interact with data', function (): void {
    $request = new Request([
        'name' => 'Alice',
        'age' => 30,
        'city' => 'Wonderland',
    ]);

    expect($request->get('name'))->toBe('Alice')
        ->and($request->filled('name'))->toBeTrue()
        ->and($request->filled('country'))->toBeFalse()
        ->and($request->string('city')->value())->toBe('Wonderland')
        ->and($request->integer('city'))->toBe(0);
});

it('retrieves nested input using dot notation', function (): void {
    $request = new Request([
        'user' => [
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ],
        'products' => [
            ['name' => 'Widget'],
            ['name' => 'Gadget'],
        ],
    ]);

    // Cake convention only: `{n}`/`{s}`/`{*}` matchers go through `Hash::extract()`
    // (see `isWildcardPath()`). Laravel-style bare `*` is intentionally unsupported.
    expect($request->get('user.name'))->toBe('Alice')
        ->and($request->get('user.missing', 'fallback'))->toBe('fallback')
        ->and($request->get('products.0.name'))->toBe('Widget')
        ->and($request->get('products.{*}.name'))->toBe(['Widget', 'Gadget'])
        ->and($request->string('user.email')->value())->toBe('alice@example.com')
        ->and($request->filled('user.name'))->toBeTrue()
        ->and($request->filled('user.country'))->toBeFalse();
});

it('does not resolve literal dotted keys', function (): void {
    $request = new Request([
        'limit.items' => 5,
        'limit' => ['items' => 10],
    ]);

    expect($request->get('limit.items'))->toBe(10)
        ->and($request->get('limit.items', 'fallback'))->toBe(10)
        ->and((new Request(['limit.items' => 5]))->get('limit.items'))->toBeNull()
        ->and((new Request(['limit.items' => 5]))->all())->toBe(['limit.items' => 5]);
});

it('expands hash wildcard matchers', function (): void {
    $request = new Request([
        'user' => [
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ],
        'products' => [
            ['name' => 'Widget'],
            ['name' => 'Gadget'],
        ],
    ]);

    expect($request->get('products.{*}.name'))->toBe(['Widget', 'Gadget'])
        ->and($request->get('products.{n}.name'))->toBe(['Widget', 'Gadget'])
        ->and($request->get('user.{s}'))->toBe(['Alice', 'alice@example.com']);
});

it('returns the default when wildcards match nothing', function (): void {
    $request = new Request([
        'products' => [
            ['name' => 'Widget'],
        ],
    ]);

    expect($request->get('products.{*}.missing', 'fallback'))->toBe('fallback')
        ->and($request->get('products.{*}.missing'))->toBeNull()
        ->and($request->get('unknown.{s}'))->toBeNull();
});

it('may be returned as array', function (): void {
    $request = new Request([
        'name' => 'Alice',
        'age' => 30,
        'city' => 'Wonderland',
    ]);

    expect($request->toArray())->toBe([
        'name' => 'Alice',
        'age' => 30,
        'city' => 'Wonderland',
    ]);
});

it('may store and return an optional principal', function (): void {
    $principal = new stdClass();
    $principal->id = 42;

    $request = new Request();
    $request->setIdentity($principal);

    expect($request->getIdentity())->toBe($principal);
});

it('returns null when no principal is set', function (): void {
    $request = new Request();

    expect($request->getIdentity())->toBeNull();
});

it('validates and returns only validated data on success', function (): void {
    $request = new Request([
        'email' => 'alice@example.com',
        'extra' => 'keep out',
    ]);

    $validator = new Validator();
    $validator
        ->email('email', false, 'The email field must be a valid email address.');

    $validated = $request->validateWith($validator);

    expect($validated)->toBe([
        'email' => 'alice@example.com',
    ]);
});

it('throws ValidationException with custom validator messages', function (): void {
    $request = new Request([
        'email' => 'not-an-email',
    ]);

    $validator = new Validator();
    $validator
        ->notBlank('email', 'Please provide a valid email address.')
        ->email('email', false, 'Please provide a valid email address.');

    $closure = function () use ($request, $validator): void {
        $request->validateWith($validator);
    };

    expect($closure)->toThrow(ValidationException::class);
});

it('can get uri when set via constructor', function (): void {
    $request = new Request(
        arguments: ['name' => 'Alice'],
        meta: ['key' => 'value'],
        uri: 'file://resources/example',
    );

    expect($request->uri())->toBe('file://resources/example');
});

it('returns null for uri when not set via constructor', function (): void {
    $request = new Request(
        arguments: ['name' => 'Alice'],
        meta: ['key' => 'value'],
    );

    expect($request->uri())->toBeNull();
});

it('returns null for uri when explicitly set to null in constructor', function (): void {
    $request = new Request(
        arguments: ['name' => 'Alice'],
    );

    expect($request->uri())->toBeNull();
});

it('can set uri using setUri method', function (): void {
    $request = new Request(['name' => 'Alice']);

    $request->setUri('file://resources/test');

    expect($request->uri())->toBe('file://resources/test');
});

it('can update uri using setUri method', function (): void {
    $request = new Request(
        arguments: ['name' => 'Alice'],
        uri: 'file://resources/original',
    );

    $request->setUri('file://resources/updated');

    expect($request->uri())->toBe('file://resources/updated');
});

it('can set uri to null using setUri method', function (): void {
    $request = new Request(
        arguments: ['name' => 'Alice'],
        uri: 'file://resources/example',
    );

    $request->setUri(null);

    expect($request->uri())->toBeNull();
});

it('supports method chaining with merge and setUri', function (): void {
    $request = new Request(['name' => 'Alice']);

    $request->merge(['age' => 30])->setUri('file://resources/test');

    expect($request->uri())->toBe('file://resources/test')
        ->and($request->get('name'))->toBe('Alice')
        ->and($request->get('age'))->toBe(30);
});
