<?php
declare(strict_types=1);

use Cake\Command\Command;
use Crustum\Mcp\Command\McpAppResourceCommand;
use Crustum\Mcp\Command\McpBakeCommand;
use Crustum\Mcp\Command\McpInspectorCommand;
use Crustum\Mcp\Command\McpPromptCommand;
use Crustum\Mcp\Command\McpResourceCommand;
use Crustum\Mcp\Command\McpServerCommand;
use Crustum\Mcp\Command\McpStartCommand;
use Crustum\Mcp\Command\McpToolCommand;
use Crustum\Mcp\Server\Contracts\Annotation as AnnotationContract;
use Crustum\Mcp\Server\Contracts\Method;
use Crustum\Mcp\Server\Testing\TestResponse;

/**
 * @param class-string $namespacePrefix
 * @return array<int, class-string>
 */
function mcpClassesInNamespace(string $namespacePrefix, string $directory): array
{
    $classes = [];

    foreach (glob($directory . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
        $class = $namespacePrefix . '\\' . basename($file, '.php');

        if (!class_exists($class) && !interface_exists($class)) {
            continue;
        }

        $classes[] = $class;
    }

    return $classes;
}

it('uses strict types and avoids debug helpers in shipped source', function (): void {
    $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $fileInfo) {
        if ($fileInfo->getExtension() !== 'php') {
            continue;
        }

        $path = $fileInfo->getPathname();

        if (str_ends_with(str_replace('\\', '/', $path), 'Server/Testing/TestResponse.php')) {
            continue;
        }

        $contents = (string)file_get_contents($path);

        expect($contents)->toContain('declare(strict_types=1);')
            ->and($contents)->not->toMatch('/\b(die|dd|dump|var_dump)\s*\(/');
    }
});

it('mcp methods implement the Method contract', function (): void {
    $classes = mcpClassesInNamespace(
        'Crustum\\Mcp\\Server\\Methods',
        dirname(__DIR__, 3) . '/src/Server/Methods',
    );

    expect($classes)->not->toBeEmpty();

    foreach ($classes as $class) {
        expect(is_a($class, Method::class, true))->toBeTrue();
    }
});

it('tool annotations implement the annotation contract', function (): void {
    $classes = mcpClassesInNamespace(
        'Crustum\\Mcp\\Server\\Tools\\Annotations',
        dirname(__DIR__, 3) . '/src/Server/Tools/Annotations',
    );

    foreach ($classes as $class) {
        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract()) {
            continue;
        }

        expect(is_a($class, AnnotationContract::class, true))->toBeTrue();
    }
});

it('resource annotations implement the annotation contract', function (): void {
    $classes = mcpClassesInNamespace(
        'Crustum\\Mcp\\Server\\Annotations',
        dirname(__DIR__, 3) . '/src/Server/Annotations',
    );

    foreach ($classes as $class) {
        $reflection = new ReflectionClass($class);
        if ($reflection->isAbstract()) {
            continue;
        }

        if ($class === \Crustum\Mcp\Server\Annotations\Annotation::class) {
            continue;
        }

        expect(is_a($class, AnnotationContract::class, true))->toBeTrue();
    }
});

it('server contracts are interfaces', function (): void {
    $classes = mcpClassesInNamespace(
        'Crustum\\Mcp\\Server\\Contracts',
        dirname(__DIR__, 3) . '/src/Server/Contracts',
    );

    expect($classes)->not->toBeEmpty();

    foreach ($classes as $class) {
        expect(interface_exists($class))->toBeTrue();
    }
});

it('commands extend Cake command bases', function (): void {
    $commands = [
        McpStartCommand::class,
        McpInspectorCommand::class,
        McpToolCommand::class,
        McpPromptCommand::class,
        McpResourceCommand::class,
        McpServerCommand::class,
        McpAppResourceCommand::class,
    ];

    foreach ($commands as $command) {
        expect(is_a($command, Command::class, true) || is_a($command, McpBakeCommand::class, true))->toBeTrue();
    }

    expect(class_exists(TestResponse::class))->toBeTrue();
});
