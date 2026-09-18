<?php
declare(strict_types=1);

use Crustum\Mcp\Test\Fixtures\ExampleServer;
use Crustum\Mcp\Test\Fixtures\LastLogLineResource;
use Crustum\Mcp\Test\Fixtures\SayHiTool;

it('asserts resource is not registered', function (): void {
    ExampleServer::resources()
        ->assertNotRegistered(SayHiTool::class);
});

it('fails when resource is registered', function (): void {
    ExampleServer::resources()
        ->assertNotRegistered(LastLogLineResource::class);
})->throws(\PHPUnit\Framework\AssertionFailedError::class);
