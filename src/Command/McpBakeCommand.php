<?php
declare(strict_types=1);

namespace Crustum\Mcp\Command;

use Bake\Command\BakeCommand;
use Bake\Utility\TemplateRenderer;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Override;

/**
 * Base bake command for MCP code generation.
 */
abstract class McpBakeCommand extends BakeCommand
{
    /**
     * Execute the bake command.
     *
     * @return int|null The exit code or null for success
     */
    public function execute(): ?int
    {
        $this->extractCommonProperties($this->args);
        $name = $this->args->getArgumentAt(0);
        if (empty($name)) {
            $this->io->err('<error>You must provide a class name.</error>');
            $this->io->out('Example: bin/cake ' . static::defaultName() . ' ExampleName');

            return static::CODE_ERROR;
        }

        $name = $this->getNameWithoutPrefix($name);
        $content = $this->getContent($name);
        if (empty($content)) {
            $this->io->err("<warning>No generated content for '{$name}', not generating template.</warning>");

            return static::CODE_ERROR;
        }

        $this->bake($name, $content);

        return static::CODE_SUCCESS;
    }

    /**
     * Write the generated class file.
     *
     * @param string $name Class name
     * @param string|bool $content Generated content
     * @return void
     */
    public function bake(string $name, string|bool $content): void
    {
        $path = $this->getPath();
        $filename = $path . $name . '.php';
        $this->io->out("\n" . sprintf('Baking %s for %s...', $this->typeLabel(), $name), 1, ConsoleIo::QUIET);

        if (is_string($content) && $this->args->getOption('verbose')) {
            $this->io->out($content);
        }

        if (is_string($content)) {
            $forceOption = $this->args->getOption('force');
            $force = is_bool($forceOption) && $forceOption;
            $this->io->createFile($filename, $content, $force);
        }

        $emptyFile = $path . '.gitkeep';
        $this->deleteEmptyFile($emptyFile);
    }

    /**
     * Generate class content from the bake template.
     *
     * @param string $name Class name
     * @return string|bool Generated content
     */
    public function getContent(string $name): string|bool
    {
        $namespace = Configure::read('App.namespace');
        if ($this->plugin) {
            $namespace = $this->pluginNamespace($this->plugin);
        }

        $namespace .= '\\' . $this->namespaceSuffix();

        $vars = array_merge(
            [
                'namespace' => $namespace,
                'class' => $name,
            ],
            $this->templateVariables($name),
        );

        $themeOption = $this->args->getOption('theme');
        $theme = is_string($themeOption) ? $themeOption : null;
        $renderer = new TemplateRenderer($theme);
        $renderer->set('plugin', $this->plugin);
        $renderer->set($vars);

        return $renderer->generate('Crustum/Mcp.Mcp/' . $this->templateName());
    }

    /**
     * Gets the path for output.
     *
     * @return string Path to output
     */
    #[Override]
    public function getPath(): string
    {
        $path = APP . $this->pathFragment();
        if ($this->plugin) {
            $path = $this->pluginPath($this->plugin) . 'src/' . $this->pathFragment();
        }

        $prefix = $this->getPrefix();
        if ($prefix !== '' && $prefix !== '0') {
            $path .= $prefix . DIRECTORY_SEPARATOR;
        }

        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Gets the option parser instance and configures it.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to configure
     * @return \Cake\Console\ConsoleOptionParser
     */
    #[Override]
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = $this->setCommonOptions($parser);
        $parser->setDescription(static::getDescription())
            ->addArgument('name', [
                'help' => sprintf('Name of the MCP %s class to generate.', $this->typeLabel()),
                'required' => true,
            ]);

        return $parser;
    }

    /**
     * Additional template variables for the bake stub.
     *
     * @param string $name Class name
     * @return array<string, mixed>
     */
    protected function templateVariables(string $name): array
    {
        return [];
    }

    /**
     * Output path fragment relative to the app or plugin src directory.
     *
     * @return string
     */
    abstract protected function pathFragment(): string;

    /**
     * Namespace suffix appended to the app or plugin namespace.
     *
     * @return string
     */
    abstract protected function namespaceSuffix(): string;

    /**
     * Bake template name without extension.
     *
     * @return string
     */
    abstract protected function templateName(): string;

    /**
     * Human-readable type label for console output.
     *
     * @return string
     */
    abstract protected function typeLabel(): string;
}
