<?php
declare(strict_types=1);

namespace Crustum\Mcp\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Crustum\Mcp\Server\Registrar;
use Override;

/**
 * Start a registered local MCP server over STDIO.
 *
 * Usage:
 * ```
 * bin/cake mcp start example-server
 * ```
 */
class McpStartCommand extends Command
{
    /**
     * Execute the command.
     *
     * @param \Cake\Console\Arguments $args The command arguments
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return int|null
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $handle = $args->getArgument('handle');

        if (!is_string($handle) || $handle === '') {
            $io->error('Please pass a valid MCP server handle.');

            return static::CODE_ERROR;
        }

        $registrar = Registrar::getInstance();
        $registrar->ensureConfigured();

        $server = $registrar->getLocalServer($handle);

        if ($server === null) {
            $io->error("MCP Server with name [{$handle}] not found. Did you register it using Registrar::local()?");

            return static::CODE_ERROR;
        }

        $server();

        return static::CODE_SUCCESS;
    }

    /**
     * Build the option parser.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Option parser
     * @return \Cake\Console\ConsoleOptionParser
     */
    #[Override]
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription(static::getDescription())
            ->addArgument('handle', [
                'help' => 'The handle of the MCP server to start.',
                'required' => true,
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'mcp start';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Start the MCP Server for a given handle';
    }
}
