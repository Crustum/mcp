<?php

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server;
use Crustum\Mcp\Server\Resource;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\AssertionFailedError;

class RestaurantR extends Server
{
    protected array $resources = [
        ReservationResource::class,
    ];
}

class ReservationResource extends Resource
{
    public function handle(Request $request): Generator
    {
        yield Response::notification('booking/starting', ['step' => 1]);

        $date = $request->date('date');

        if ($date?->isPast()) {
            yield Response::error('The booking date cannot be in the past.');
        }

        if ($date?->year === 2999) {
            yield [
                'You must be joking! That date is too far in the future.',
                'Please select a more reasonable date.',
            ];
        }

        yield Response::notification('booking/completed', ['step' => 2]);

        yield 'Your booking is confirmed!';
    }
}

it('may assert that text is seen when returning string content', function (): void {
    $response = RestaurantR::resource(ReservationResource::class);

    $response->assertSee('Your booking is confirmed!');
});

it('may assert two notifications got sent', function (): void {
    $response = RestaurantR::resource(ReservationResource::class);

    $response->assertNotificationCount(2)
        ->assertSentNotification('booking/starting', ['step' => 1])
        ->assertSentNotification('booking/completed', ['step' => 2]);
});

it('may fail to assert the notification count is wrong', function (): void {
    $response = RestaurantR::resource(ReservationResource::class);

    $response->assertNotificationCount(3);
})->throws(ExpectationFailedException::class);

it('may fail to assert a notification that was not sent', function (): void {
    $response = RestaurantR::resource(ReservationResource::class);

    $response->assertSentNotification('booking/unknown');
})->throws(AssertionFailedError::class);

it('may fail to assert a notification that was sent with wrong params', function (): void {
    $response = RestaurantR::resource(ReservationResource::class);

    $response->assertSentNotification('booking/starting', ['step' => 2]);
})->throws(AssertionFailedError::class);
