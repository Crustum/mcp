<?php
declare(strict_types=1);

use Cake\Core\Plugin;

it('ships CakeDC public permission rules in config/permissions.php', function (): void {
    $path = Plugin::path('Crustum/Mcp') . 'config' . DS . 'permissions.php';
    /** @var list<array<string, mixed>> $rules */
    $rules = require $path;

    expect($rules)->not->toBeEmpty();
    expect($rules[0]['bypassAuth'])->toBeTrue();
    expect($rules[0]['plugin'])->toBe('Crustum/Mcp');

    $controllers = array_map(
        static fn(array $rule): string => is_string($rule['controller']) ? $rule['controller'] : '',
        $rules,
    );

    expect($controllers)->toContain('OAuthMetadata');
    expect($controllers)->toContain('OAuthRegister');
    expect($controllers)->toContain('Server');
});
