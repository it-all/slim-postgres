<?php
declare(strict_types=1);

namespace SlimPostgres\Security\Authentication;

use SlimPostgres\BaseMiddleware;

class GuestMiddleware extends BaseMiddleware
{
    public function __invoke($request, $response, $next)
    {
        // if user signed in redirect to admin home
        if ($this->container->authentication->check()) {
            return $response->withRedirect($this->container->router->pathFor(ROUTE_ADMIN_HOME_DEFAULT));
        }

        $response = $next($request, $response);
        return $response;
    }
}