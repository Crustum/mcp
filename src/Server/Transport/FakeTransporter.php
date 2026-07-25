<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Transport;

use Closure;
use Crustum\Mcp\Server\Contracts\Transport;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Fake transport used by server testing helpers.
 */
class FakeTransporter implements Transport
{
    /**
     * @inheritDoc
     */
    public function onReceive(Closure $handler): void
    {
    }

    /**
     * @inheritDoc
     */
    public function send(string $message, ?string $sessionId = null): void
    {
    }

    /**
     * @inheritDoc
     */
    public function run(): null
    {
        throw new LogicException('Not implemented.');
    }

    /**
     * @inheritDoc
     */
    public function sessionId(): ?string
    {
        return uniqid();
    }

    /**
     * @inheritDoc
     */
    public function stream(Closure $stream): void
    {
    }

    /**
     * @inheritDoc
     */
    public function httpRequest(): ?ServerRequestInterface
    {
        return null;
    }
}
