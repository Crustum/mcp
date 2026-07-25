<?php
declare(strict_types=1);

use Crustum\Mcp\Server\Registrar;
use Crustum\Mcp\Test\Support\StubRegistrar;

/**
 * Reset the shared MCP registrar between command tests.
 *
 * @return void
 */
function resetMcpRegistrar(): void
{
    Registrar::setInstance(null);
}

/**
 * Install a stub registrar for command tests.
 *
 * @param \Crustum\Mcp\Test\Support\StubRegistrar $registrar Stub registrar instance
 * @return \Crustum\Mcp\Test\Support\StubRegistrar
 */
function useMcpRegistrar(StubRegistrar $registrar): StubRegistrar
{
    Registrar::setInstance($registrar);

    return $registrar;
}

/**
 * Invoke a protected command method for unit testing.
 *
 * @param object $command Command instance
 * @param string $method Method name
 * @param array<int, mixed> $arguments Method arguments
 * @return mixed
 */
function invokeMcpCommandMethod(object $command, string $method, array $arguments = []): mixed
{
    $reflection = new ReflectionClass($command);
    $reflectionMethod = $reflection->getMethod($method);

    return $reflectionMethod->invoke($command, ...$arguments);
}
