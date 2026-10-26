<?php
declare(strict_types=1);

namespace Crustum\Mcp\Command;

use Bake\Utility\TemplateRenderer;
use Crustum\Mcp\Support\Str;
use Override;

/**
 * Bake command for MCP app resource classes and linked templates.
 *
 * Usage:
 * ```
 * bin/cake bake mcp_app_resource ExampleAppResource
 * ```
 */
class McpAppResourceCommand extends McpBakeCommand
{
    /**
     * Execute the bake command.
     *
     * @return int|null The exit code or null for success
     */
    #[Override]
    public function execute(): ?int
    {
        $result = parent::execute();
        if ($result !== static::CODE_SUCCESS) {
            return $result;
        }

        $name = $this->args->getArgumentAt(0);
        if (!is_string($name) || $name === '') {
            return static::CODE_ERROR;
        }

        $this->bakeView($this->getNameWithoutPrefix($name));

        return static::CODE_SUCCESS;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'bake mcp_app_resource';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Bake an MCP app resource class and view';
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'mcp_app_resource';
    }

    /**
     * @inheritDoc
     */
    protected function pathFragment(): string
    {
        return 'Mcp/Resources/';
    }

    /**
     * @inheritDoc
     */
    protected function namespaceSuffix(): string
    {
        return 'Mcp\\Resources';
    }

    /**
     * @inheritDoc
     */
    protected function templateName(): string
    {
        return 'app_resource';
    }

    /**
     * @inheritDoc
     */
    protected function typeLabel(): string
    {
        return 'app resource';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function templateVariables(string $name): array
    {
        $viewName = $this->getViewName($name);
        $viewTemplate = 'Mcp/' . $viewName;

        if ($this->plugin) {
            $viewTemplate = $this->plugin . '.' . $viewTemplate;
        }

        return [
            'viewTemplate' => $viewTemplate,
        ];
    }

    /**
     * Write the linked MCP app view template.
     *
     * @param string $name Class name
     * @return void
     */
    protected function bakeView(string $name): void
    {
        $viewPath = $this->getViewPath($name);
        $forceOption = $this->args->getOption('force');
        $force = is_bool($forceOption) && $forceOption;

        if (file_exists($viewPath) && !$force) {
            $this->io->warning("View [{$viewPath}] already exists.");

            return;
        }

        $themeOption = $this->args->getOption('theme');
        $theme = is_string($themeOption) ? $themeOption : null;
        $renderer = new TemplateRenderer($theme);
        $renderer->set('plugin', $this->plugin);
        $renderer->set([
            'title' => Str::headline($name),
        ]);

        $content = $renderer->generate('Crustum/Mcp.Mcp/app_resource_view');
        $this->io->createFile($viewPath, $content, $force);
        $this->io->out("View [{$viewPath}] created successfully.");
    }

    /**
     * Resolve the view output path via Bake template path resolution.
     *
     * Uses `App.paths.templates` for the application, or the target plugin's
     * `templates/` directory when `--plugin` / `Plugin.Name` is set.
     *
     * @param string $name Class name
     * @return string
     */
    protected function getViewPath(string $name): string
    {
        $viewName = str_replace('/', DIRECTORY_SEPARATOR, $this->getViewName($name));

        return $this->getTemplatePath('Mcp') . $viewName . '.php';
    }

    /**
     * Resolve the view file name from the class name.
     *
     * @param string $name Class name
     * @return string
     */
    protected function getViewName(string $name): string
    {
        $segments = explode('/', str_replace('\\', '/', $name));

        return implode('/', array_map(
            Str::kebab(...),
            $segments,
        ));
    }
}
