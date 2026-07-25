<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Transport;

use Closure;
use Crustum\Mcp\Server\Contracts\Transport;
use Psr\Http\Message\ServerRequestInterface;

/**
 * STDIO transport for local MCP server processes.
 */
class StdioTransport implements Transport
{
    /**
     * @param string $sessionId MCP session identifier
     * @param (\Closure(string): void)|null $handler Message handler
     */
    public function __construct(
        protected string $sessionId,
        protected ?Closure $handler = null,
    ) {
    }

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
    public function send(string $message, ?string $sessionId = null): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    /**
     * @inheritDoc
     */
    public function run(): null
    {
        stream_set_blocking(STDIN, false);

        while (!feof(STDIN)) {
            $line = fgets(STDIN);

            if ($line === false) {
                usleep(10000);

                continue;
            }

            if (is_callable($this->handler)) {
                ($this->handler)($line);
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function sessionId(): ?string
    {
        return $this->sessionId;
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
