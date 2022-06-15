<?php

declare(strict_types=1);

namespace Spiral\Bootloader\Http;

use Psr\Http\Server\MiddlewareInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Router\GroupRegistry;
use Spiral\Router\Loader\Configurator\RoutingConfigurator;

abstract class RoutesBootloader extends Bootloader
{
    public function init(HttpBootloader $http): void
    {
        foreach ($this->globalMiddleware() as $middleware) {
            $http->addMiddleware($middleware);
        }
    }

    public function boot(RoutingConfigurator $routes, GroupRegistry $groups): void
    {
        $this->registerMiddlewareGroups($groups, $this->middlewareGroups());

        $this->defineRoutes($routes);
    }

    /**
     * Override this method to configure application routes
     */
    protected function defineRoutes(RoutingConfigurator $routes): void
    {
    }

    /**
     * @return array<string,array<MiddlewareInterface|class-string<MiddlewareInterface>>>
     */
    abstract protected function globalMiddleware(): array;

    /**
     * @return array<string,array<MiddlewareInterface|class-string<MiddlewareInterface>>>
     */
    abstract protected function middlewareGroups(): array;

    private function registerMiddlewareGroups(GroupRegistry $registry, array $groups)
    {
        foreach ($groups as $group => $middlewares) {
            foreach ($middlewares as $middleware) {
                $registry->getGroup($group)->addMiddleware($middleware);
            }
        }
    }
}
