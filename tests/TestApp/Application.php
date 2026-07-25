<?php
declare(strict_types=1);

namespace TestApp;

use Bake\BakePlugin;
use Cake\Core\Plugin;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use Crustum\Mcp\McpPlugin;
use Override;

/**
 * Test application for Mcp plugin tests.
 */
class Application extends BaseApplication
{
    /**
     * @return void
     */
    #[Override]
    public function bootstrap(): void
    {
        // Avoid BaseApplication::bootstrap() re-including host config;
        // TestApp only needs Bake + Mcp for console command integration tests.
        if (!Plugin::isLoaded('Bake')) {
            $this->addPlugin(BakePlugin::class);
        }

        if (!Plugin::isLoaded('Mcp')) {
            $this->addPlugin(McpPlugin::class);
        }
    }

    /**
     * @param \Cake\Routing\RouteBuilder $routes Route builder
     * @return void
     */
    #[Override]
    public function routes(RouteBuilder $routes): void
    {
    }

    /**
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue Middleware queue.
     * @return \Cake\Http\MiddlewareQueue
     */
    #[Override]
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue;
    }
}
