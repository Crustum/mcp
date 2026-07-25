<?php
declare(strict_types=1);

/**
 * Delete a directory tree if it exists.
 *
 * @param string $directory Absolute directory path
 * @return void
 */
function removeDirectoryTree(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isDir()) {
            rmdir($fileInfo->getPathname());

            continue;
        }

        unlink($fileInfo->getPathname());
    }

    rmdir($directory);
}

/**
 * Clean generated MCP bake artifacts under TestApp.
 *
 * @return void
 */
function cleanMcpBakeArtifacts(): void
{
    removeDirectoryTree(APP . 'Mcp');
    removeDirectoryTree(APP . 'templates' . DS . 'Mcp');
}

/**
 * Absolute path to a baked MCP class under TestApp.
 *
 * @param string $relativePath Path relative to TestApp/Mcp
 * @return string
 */
function mcpBakeClassPath(string $relativePath): string
{
    return APP . 'Mcp' . DS . str_replace(['/', '\\'], DS, $relativePath);
}

/**
 * Absolute path to a baked MCP view under TestApp.
 *
 * @param string $relativePath Path relative to TestApp/templates/Mcp
 * @return string
 */
function mcpBakeViewPath(string $relativePath): string
{
    return APP . 'templates' . DS . 'Mcp' . DS . str_replace(['/', '\\'], DS, $relativePath);
}

/**
 * Absolute path to a plugin MCP bake template.
 *
 * @param string $template Template basename without extension
 * @return string
 */
function mcpBakeTemplatePath(string $template): string
{
    return dirname(__DIR__, 2) . DS . 'templates' . DS . 'bake' . DS . 'Mcp' . DS . $template . '.twig';
}
