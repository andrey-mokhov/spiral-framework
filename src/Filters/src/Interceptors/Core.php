<?php

declare(strict_types=1);

namespace Spiral\Filters\Interceptors;

use Spiral\Core\CoreInterface;
use Spiral\Filters\FilterBag;
use Spiral\Filters\FilterInterface;

final class Core implements CoreInterface
{
    /**
     * @param array{filterBag: FilterBag}|array<string, mixed> $parameters
     */
    public function callAction(string $controller, string $action, array $parameters = []): FilterInterface
    {
        return $parameters['filterBag']->filter;
    }
}
