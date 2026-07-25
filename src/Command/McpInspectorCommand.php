<?php
declare(strict_types=1);

namespace Crustum\Mcp\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Crustum\Mcp\Server\Registrar;
use Crustum\Mcp\Server\ServerUrl;
use Crustum\Mcp\Server\WebServerRegistration;
use Override;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Open the MCP Inspector tool to debug and test MCP servers.
 *
 * Usage:
 * ```
 * bin/cake mcp inspector example-server
 * ```
 */
class McpInspectorCommand extends Command
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

        $io->info("Starting the MCP Inspector for server [{$handle}]");

        $localServer = $registrar->getLocalServer($handle);
        $webServer = $registrar->getWebServer($handle);
        $servers = $registrar->servers();

        if ($servers === []) {
            $io->error('No MCP servers found. Please run `bin/cake bake mcp_server [name]` and register the server.');

            return static::CODE_ERROR;
        }

        if (count($servers) === 1) {
            $server = array_shift($servers);

            if (is_callable($server)) {
                $localServer = $server;
                $webServer = null;
            } elseif ($server instanceof WebServerRegistration) {
                $localServer = null;
                $webServer = $server;
            }
        }

        if ($localServer === null && !$webServer instanceof WebServerRegistration) {
            $availableServers = array_map(
                static fn(string $server): string => "[{$server}]",
                array_keys($servers),
            );
            $io->error(
                'MCP Server with name [' . $handle . '] not found. Available servers: ' . implode(', ', $availableServers),
            );

            return static::CODE_ERROR;
        }

        $env = [];
        $host = $args->getOption('host');
        $port = $args->getOption('port');

        if (is_string($host) && $host !== '') {
            $env['HOST'] = $host;
        }

        if (is_string($port) && $port !== '') {
            $env['CLIENT_PORT'] = $port;
        }

        if ($localServer !== null) {
            $cakePath = $this->externalProcessPath(ROOT . DS . 'bin' . DS . 'cake.php');
            $phpBinary = $this->externalProcessPath($this->phpBinary());
            $command = [
                'npx',
                '@modelcontextprotocol/inspector',
                '--transport',
                'stdio',
                $phpBinary,
                $cakePath,
                'mcp',
                'start',
                $handle,
            ];

            $guidance = [
                'Transport Type' => 'STDIO',
                'Command' => $phpBinary,
                'Arguments' => implode(' ', [
                    $cakePath,
                    'mcp',
                    'start',
                    $handle,
                ]),
            ];
        } else {
            $serverUrl = $this->resolveWebServerUrl($args, $webServer->uri);

            if ($serverUrl === null) {
                $io->error('MCP Inspector requires an absolute server URL.');
                $io->out('Configure one of: App.fullBaseUrl, Mcp.base_url, APP_URL, or pass --url=http://your-host/mcp/qa');

                return static::CODE_ERROR;
            }

            if (parse_url($serverUrl, PHP_URL_SCHEME) === 'https') {
                $env['NODE_TLS_REJECT_UNAUTHORIZED'] = '0';
            }

            $command = [
                'npx',
                '@modelcontextprotocol/inspector',
                '--transport',
                'http',
                '--server-url',
                $serverUrl,
            ];

            $guidance = [
                'Transport Type' => 'Streamable HTTP',
                'URL' => $serverUrl,
                'Secure' => 'Your project must be accessible on HTTP for this to work due to how node manages SSL trust',
            ];
        }

        $process = new Process($command, ROOT, $env);
        $process->setTimeout(null);

        try {
            foreach ($guidance as $guidanceKey => $guidanceValue) {
                $io->out(sprintf('%s => %s', $guidanceKey, $guidanceValue));
            }

            $io->out('');

            $process->mustRun(function (int|string $type, string $buffer): void {
                echo $buffer;
            });
        } catch (Throwable $throwable) {
            $io->error('Failed to start MCP Inspector: ' . $throwable->getMessage());

            return static::CODE_ERROR;
        }

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
                'help' => 'The handle or route of the MCP server to inspect.',
                'required' => true,
            ])
            ->addOption('host', [
                'help' => 'The host the inspector should bind to.',
                'default' => null,
            ])
            ->addOption('port', [
                'help' => 'The port the inspector should bind to.',
                'default' => null,
            ])
            ->addOption('url', [
                'help' => 'Absolute MCP server URL for HTTP transport (overrides App.fullBaseUrl / Mcp.base_url).',
                'default' => null,
            ]);

        return $parser;
    }

    /**
     * Resolve the PHP executable path.
     *
     * @return string
     */
    protected function phpBinary(): string
    {
        return (new PhpExecutableFinder())->find(false) ?: 'php';
    }

    /**
     * Resolve the absolute MCP web server URL for the inspector.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param string $uri Registered MCP server URI
     * @return string|null
     */
    protected function resolveWebServerUrl(Arguments $args, string $uri): ?string
    {
        $url = $args->getOption('url');

        $serverUrl = is_string($url) && $url !== '' ? rtrim($url, '/') : ServerUrl::forPath($uri);

        if (!ServerUrl::isAbsolute($serverUrl)) {
            return null;
        }

        return $serverUrl;
    }

    /**
     * Normalize a filesystem path for external process arguments.
     *
     * Node-based MCP clients pass stdio command arguments through layers that
     * treat backslashes as escape sequences on Windows.
     *
     * @param string $path Filesystem path
     * @return string
     */
    protected function externalProcessPath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'mcp inspector';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Open the MCP Inspector tool to debug and test MCP Servers';
    }
}
