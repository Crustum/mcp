<?php
declare(strict_types=1);

use Crustum\Mcp\Test\Fixtures\ExampleServer;
use Crustum\Mcp\Test\Fixtures\LastLogLineResource;
use Crustum\Mcp\Test\Fixtures\SayHiTool;

it('asserts registered resources', function (): void {
    ExampleServer::resources()
        ->assertRegistered([LastLogLineResource::class]);
});

it('asserts registered resources by class name string', function (): void {
    ExampleServer::resources()
        ->assertRegistered(LastLogLineResource::class);
});

it('fails when resource is not registered', function (): void {
    ExampleServer::resources()
        ->assertRegistered(SayHiTool::class);
})->throws(\PHPUnit\Framework\AssertionFailedError::class);
