<?php
declare(strict_types=1);

use Cake\Chronos\Chronos;
use Crustum\Mcp\Server\McpRequestBuilder;

/**
 * Reset MCP request builder state between feature tests.
 *
 * @return void
 */
function resetMcpRequestBuilder(): void
{
    McpRequestBuilder::reset();
}

/**
 * Build a date string for feature test arguments.
 *
 * @param string $modifier Chronos date modifier
 * @return string
 */
function mcpTestDate(string $modifier): string
{
    return Chronos::parse('today')->modify($modifier)->toDateString();
}
