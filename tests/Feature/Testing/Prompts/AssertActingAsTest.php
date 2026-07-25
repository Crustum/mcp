<?php

use Crustum\Mcp\Request;
use Crustum\Mcp\Server;
use Crustum\Mcp\Server\Prompt;
use PHPUnit\Framework\ExpectationFailedException;

class AirportP extends Server
{
    protected array $prompts = [
        TicketPrompt::class,
    ];
}

class TicketPrompt extends Prompt
{
    public function handle(Request $request): string
    {
        return $request->getIdentity() !== null
            ? 'Here is your ticket!'
            : 'You must be logged in to get a ticket.';
    }
}

it('may assert the user is acting as the given user', function (): void {
    $user = new stdClass();

    $response = AirportP::actingAs($user)
        ->prompt(TicketPrompt::class);

    $response->assertSee('Here is your ticket!');
});

it('may assert the user is not acting as a user', function (): void {
    $response = AirportP::prompt(TicketPrompt::class);

    $response->assertSee('You must be logged in to get a ticket.');
});

it('may assert authenticated and authenticated as a specific user', function (): void {
    $user = new stdClass();
    $user->id = 1;

    $response = AirportP::actingAs($user)
        ->prompt(TicketPrompt::class);

    $response->assertAuthenticated()
        ->assertAuthenticatedAs($user);
});

it('may assert guest when no user is authenticated', function (): void {
    $response = AirportP::prompt(TicketPrompt::class);

    $response->assertGuest();
});

it('fails when asserting authenticated as a different user', function (): void {
    $userA = new stdClass();
    $userA->id = 1;

    $userB = new stdClass();
    $userB->id = 2;

    $response = AirportP::actingAs($userA)
        ->prompt(TicketPrompt::class);

    $response->assertAuthenticatedAs($userB);
})->throws(ExpectationFailedException::class);
