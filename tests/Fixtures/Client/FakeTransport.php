<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures\Client;

use Crustum\Mcp\Client\Contracts\Transport;
use Crustum\Mcp\Client\Contracts\UsesProtocol;
use Crustum\Mcp\Client\Exception\TimeoutException;
use Crustum\Mcp\Enums\ProtocolVersion;
use RuntimeException;
use Throwable;

/**
 * In-memory test transport with scripted responses.
 */
class FakeTransport implements Transport, UsesProtocol
{
    public bool $connected = false;

    /**
     * @var array<int, string>
     */
    public array $sent = [];

    /**
     * @var array<int, array<string, string>>
     */
    public array $sentHeaders = [];

    /**
     * @var array<int, string>
     */
    public array $responses = [];

    public float $timeoutSeconds = 30.0;

    public ?ProtocolVersion $protocolVersion = null;

    /**
     * Whether the transport implements the discovery handshake.
     *
     * @var bool
     */
    public bool $negotiates = false;

    /**
     * Whether discovery probes time out.
     *
     * @var bool
     */
    public bool $timesOutOnDiscover = false;

    /**
     * Exception to throw on the next send.
     *
     * @var \Throwable|null
     */
    public ?Throwable $throwOnNextSend = null;

    /**
     * Identifier of the last request sent.
     *
     * @var string|int|null
     */
    protected string|int|null $pendingId = null;

    /**
     * @inheritDoc
     */
    public function connect(): void
    {
        $this->connected = true;
    }

    /**
     * @inheritDoc
     */
    public function disconnect(): void
    {
        $this->connected = false;
    }

    /**
     * @inheritDoc
     */
    public function send(string $message, array $headers = []): void
    {
        if ($this->throwOnNextSend instanceof Throwable) {
            $throwable = $this->throwOnNextSend;
            $this->throwOnNextSend = null;

            throw $throwable;
        }

        $frame = json_decode($message, true);
        $frame = is_array($frame) ? $frame : [];

        $id = $frame['id'] ?? null;

        if (is_string($frame['method'] ?? null) && (is_string($id) || is_int($id))) {
            $this->pendingId = $id;
        }

        if ($this->timesOutOnDiscover && ($frame['method'] ?? null) === 'server/discover') {
            $this->connected = false;

            throw new TimeoutException('Timed out while waiting for server response.');
        }

        if (!$this->negotiates && ($frame['method'] ?? null) === 'server/discover') {
            array_unshift($this->responses, (string)json_encode([
                'jsonrpc' => '2.0',
                'id' => $this->pendingId,
                'error' => ['code' => -32601, 'message' => 'Method not found.'],
            ]));

            return;
        }

        $this->sent[] = $message;
        $this->sentHeaders[] = $headers;
    }

    /**
     * @inheritDoc
     */
    public function setTimeoutSeconds(float $seconds): void
    {
        $this->timeoutSeconds = $seconds;
    }

    /**
     * @inheritDoc
     */
    public function useProtocol(ProtocolVersion $protocolVersion): void
    {
        $this->protocolVersion = $protocolVersion;
    }

    /**
     * @inheritDoc
     */
    public function recipe(): array
    {
        return ['driver' => 'fake'];
    }

    /**
     * @inheritDoc
     */
    public function receive(): string
    {
        if ($this->responses === []) {
            throw new RuntimeException('No queued responses in FakeTransport.');
        }

        return $this->answering(array_shift($this->responses));
    }

    /**
     * Align a scripted response id with the pending request id.
     *
     * @param string $raw Raw JSON-RPC frame
     * @return string
     */
    protected function answering(string $raw): string
    {
        $frame = json_decode($raw, true);

        if (!is_array($frame) || !array_key_exists('id', $frame)) {
            return $raw;
        }

        if (!array_key_exists('result', $frame) && !array_key_exists('error', $frame)) {
            return $raw;
        }

        return (string)json_encode([...$frame, 'id' => $this->pendingId]);
    }
}
