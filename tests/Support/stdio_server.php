<?php
declare(strict_types=1);

/**
 * STDIO entry point for feature tests.
 *
 * Mirrors the production `mcp start` wiring (`Registrar::local()`): an
 * `ExampleServer` on a `StdioTransport` reading STDIN until EOF. Feature
 * tests spawn this script with piped input instead of driving the blocking
 * transport loop in-process.
 */

require __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/container_helpers.php';

// Fixture tool handlers reuse test support validators (e.g. SayHiTool uses
// mcpRequiredNameValidator()), which Pest loads in-process. Require the same
// helper sets here so the subprocess serves identical behavior.
require_once __DIR__ . '/client_helpers.php';
require_once __DIR__ . '/server_helpers.php';
require_once __DIR__ . '/command_helpers.php';
require_once __DIR__ . '/bake_helpers.php';
require_once __DIR__ . '/registrar_helpers.php';
require_once __DIR__ . '/feature_helpers.php';
require_once __DIR__ . '/validation_helpers.php';

use Crustum\Mcp\Server\ContainerInvoker;
use Crustum\Mcp\Server\Transport\StdioTransport;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Mcp\Test\Fixtures\ExampleServer;

$container = testContainer();
$container->addShared(
    ContainerInvoker::class,
    static fn(): ContainerInvoker => new ContainerInvoker($container),
);
ContainerRegistry::setInstance($container);

$transport = new StdioTransport();
$server = new ExampleServer($transport);
$server->start();
$transport->run();
