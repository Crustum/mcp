<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Transport;

use Crustum\Mcp\Client\Contracts\Transport;
use Crustum\Mcp\Exception\ClientException;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * MCP client transport over stdio subprocess communication.
 */
class StdioTransport implements Transport
{
    /**
     * Running subprocess instance.
     *
     * @var \Symfony\Component\Process\Process|null
     */
    protected ?Process $process = null;

    /**
     * Subprocess stdin stream.
     *
     * @var \Symfony\Component\Process\InputStream|null
     */
    protected ?InputStream $input = null;

    /**
     * Buffered stdout data not yet consumed as a line.
     *
     * @var string
     */
    protected string $buffer = '';

    /**
     * Idle timeout while waiting for subprocess output.
     *
     * @var float
     */
    protected float $timeoutSeconds = 30.0;

    /**
     * Create a new stdio transport.
     *
     * @param string $command Subprocess command
     * @param array<int, string> $args Subprocess arguments
     */
    public function __construct(
        protected string $command,
        protected array $args = [],
    ) {
    }

    /**
     * @inheritDoc
     */
    public function connect(): void
    {
        if ($this->process?->isRunning()) {
            return;
        }

        $this->input = new InputStream();
        $this->process = new Process([$this->command, ...$this->args]);
        $this->process->setInput($this->input);
        $this->process->setTimeout(null);

        try {
            $this->process->start();
        } catch (ExceptionInterface) {
            throw new ClientException("Failed to start process [{$this->command}]. Make sure the command exists.");
        }
    }

    /**
     * @inheritDoc
     */
    public function disconnect(): void
    {
        $this->input?->close();
        $this->input = null;

        if ($this->process?->isRunning()) {
            $this->process->stop(0.1);
        }

        $this->process = null;
        $this->buffer = '';
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
    public function setProtocolVersion(string $version): void
    {
    }

    /**
     * @inheritDoc
     */
    public function recipe(): array
    {
        return [
            'driver' => 'stdio',
            'command' => $this->command,
            'args' => $this->args,
            'timeoutSeconds' => $this->timeoutSeconds,
        ];
    }

    /**
     * @inheritDoc
     */
    public function send(string $message): void
    {
        if (!$this->input instanceof InputStream || !$this->process?->isRunning()) {
            throw new ClientException('Transport is not connected.');
        }

        $this->input->write($message . "\n");
    }

    /**
     * @inheritDoc
     */
    public function receive(): string
    {
        if (!$this->process instanceof Process) {
            throw new ClientException('Transport is not connected.');
        }

        return $this->popLine() ?? $this->readNextLine($this->process);
    }

    /**
     * Read the next complete line from subprocess stdout.
     *
     * @param \Symfony\Component\Process\Process $process Running subprocess
     * @return string Complete line including trailing newline
     */
    protected function readNextLine(Process $process): string
    {
        $process->setIdleTimeout($this->timeoutSeconds);

        try {
            $found = $process->waitUntil($this->bufferUntilNewline(...));
        } catch (ProcessTimedOutException) {
            $this->failWith('Timed out while waiting for server response.');
        }

        if (!$found) {
            $stderr = trim($process->getErrorOutput());
            $suffix = $stderr === '' ? '' : " stderr: {$stderr}";

            $this->failWith("Subprocess [{$this->command}] closed its output before sending a complete response.{$suffix}");
        }

        return $this->popLine() ?? $this->failWith('Subprocess output stream did not yield a complete line.');
    }

    /**
     * Append subprocess output chunks until a newline is available.
     *
     * @param string $type Output stream type
     * @param string $chunk Output chunk
     * @return bool Whether a complete line is buffered
     */
    protected function bufferUntilNewline(string $type, string $chunk): bool
    {
        if ($type !== Process::OUT) {
            return false;
        }

        $this->buffer .= $chunk;

        return str_contains($this->buffer, "\n");
    }

    /**
     * Pop the next buffered line from stdout.
     *
     * @return string|null Line including trailing newline, if available
     */
    protected function popLine(): ?string
    {
        $newlinePos = strpos($this->buffer, "\n");

        if ($newlinePos === false) {
            return null;
        }

        $line = substr($this->buffer, 0, $newlinePos + 1);
        $this->buffer = substr($this->buffer, $newlinePos + 1);

        return $line;
    }

    /**
     * Reset the transport and throw a client exception.
     *
     * @param string $message Failure message
     * @return never
     */
    protected function failWith(string $message): never
    {
        $this->disconnect();

        throw new ClientException($message);
    }

    /**
     * Ensure subprocess resources are released.
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
