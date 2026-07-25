<?php
declare(strict_types=1);

/**
 * MCP App Resource HTML layout.
 *
 * @var \Cake\View\View $this
 * @var string|null $title
 */

use Cake\I18n\I18n;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Mcp\Support\McpContainerBindings;
use Crustum\Mcp\Support\McpSdk;

$container = ContainerRegistry::getInstance();

if ($container->has(McpContainerBindings::SDK)) {
    $mcpSdk = (string)$container->get(McpContainerBindings::SDK);
} else {
    $mcpSdk = McpSdk::contents();
}

if ($container->has(McpContainerBindings::LIBRARY_SCRIPTS)) {
    $libraryScripts = (string)$container->get(McpContainerBindings::LIBRARY_SCRIPTS);
} else {
    $libraryScripts = '';
}

$documentTitle = $title ?? $this->get('title');
?>
<!DOCTYPE html>
<html lang="<?= h(str_replace('_', '-', I18n::getLocale())) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($documentTitle !== null) : ?>
        <?php if ($documentTitle !== '') : ?>
    <title><?= h($documentTitle) ?></title>
        <?php endif; ?>
    <?php endif; ?>
    <script><?= $mcpSdk ?></script>
    <?= $libraryScripts ?>
    <?= $this->fetch('mcpHead') ?>
</head>
<body>
    <?= $this->fetch('content') ?>
</body>
</html>
