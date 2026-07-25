<?php

declare(strict_types=1);

use Crustum\Mcp\Request;
use Crustum\Mcp\Server;
use Crustum\Mcp\Server\Tool;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\AssertionFailedError;

class ShopT extends Server
{
    protected array $tools = [
        BuyTool::class,
    ];
}

class BuyTool extends Tool
{
    public function handle(Request $request): string
    {
        $request->validateWith(mcpPurchaseValidator());

        return 'Purchase successful!';
    }
}

it('may assert validation passes', function (): void {
    $response = ShopT::tool(BuyTool::class, ['id' => 1, 'quantity' => 3]);

    $response->assertHasNoErrors();
});

it('may assert that things are ok', function (): void {
    $response = ShopT::tool(BuyTool::class, ['id' => 1, 'quantity' => 3]);

    $response->assertOk();
});

it('may fail to assert that things are ok', function (): void {
    $response = ShopT::tool(BuyTool::class);

    $response->assertOk();
})->throws(AssertionFailedError::class);

it('may assert validation fails', function (): void {
    $response = ShopT::tool(BuyTool::class);

    $response->assertHasErrors();
});

it('may fail to assert validation fails', function (): void {
    $response = ShopT::tool(BuyTool::class, ['id' => 1]);

    $response->assertHasErrors([
        'The id field is required.',
    ]);
})->throws(AssertionFailedError::class);

it('may assert specific validation errors', function (): void {
    $response = ShopT::tool(BuyTool::class, ['id' => 1]);

    $response->assertHasErrors([
        'The quantity field is required.',
    ]);
});
