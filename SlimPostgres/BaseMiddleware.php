<?php
declare(strict_types=1);

namespace SlimPostgres;

/** The base middleware class that can be extended by any registered middleware which needs access to the slim container */
class BaseMiddleware
{
    protected $container;

    public function __construct($container)
    {
        $this->container = $container;
    }
}
