<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Cake\Http\Response;
use Closure;
use Crustum\Mcp\Server\Contracts\Transport;
use Psr\Http\Message\ServerRequestInterface;

/**
 * In-memory transport for server event and integration tests.
 */
class ArrayTransport implements Transport
{
    /**
     * @var (\Closure(string): void)|null
     */
    public ?Closure $handler = null;

    /**
     * @var array<int, string>
     */
    public array $sent = [];

    /**
     * @inheritDoc
     */
    public function onReceive(Closure $handler): void
    {
        $this->handler = $handler;
    }

    /**
     * @inheritDoc
     */
    public function run(): ?Response
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function send(string $message): void
    {
        $this->sent[] = $message;
    }

    /**
     * @inheritDoc
     */
    public function stream(Closure $stream): void
    {
        $stream();
    }

    /**
     * @inheritDoc
     */
    public function httpRequest(): ?ServerRequestInterface
    {
        return null;
    }
}
