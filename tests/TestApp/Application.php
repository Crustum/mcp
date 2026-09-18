<?php
declare(strict_types=1);

namespace TestApp;

use Bake\BakePlugin;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\Routing\RouteBuilder;
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
        $this->addPlugin(BakePlugin::class);
        $this->addPlugin('Crustum/Mcp', ['bootstrap' => true, 'routes' => true]);
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
        return $middlewareQueue->add(new RoutingMiddleware($this));
    }
}
