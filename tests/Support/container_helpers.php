<?php
declare(strict_types=1);

use Cake\Core\Container;

/**
 * Get or create a shared test container instance.
 *
 * @return \Cake\Core\Container
 */
function testContainer(): Container
{
    if (!isset($GLOBALS['mcp_test_container']) || !$GLOBALS['mcp_test_container'] instanceof Container) {
        $GLOBALS['mcp_test_container'] = new Container();
    }

    return $GLOBALS['mcp_test_container'];
}

/**
 * Bind an instance into the test container.
 *
 * @param string $abstract Service identifier
 * @param mixed $instance Instance to bind
 * @return void
 */
function instance(string $abstract, mixed $instance): void
{
    testContainer()->addShared($abstract, $instance, true);
}

/**
 * Reset the test container.
 *
 * @return void
 */
function resetTestContainer(): void
{
    unset($GLOBALS['mcp_test_container']);
}
