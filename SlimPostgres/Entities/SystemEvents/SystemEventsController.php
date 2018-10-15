<?php
declare(strict_types=1);

namespace SlimPostgres\Entities\SystemEvents;

use SlimPostgres\BaseMVC\Controller\BaseController;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class SystemEventsController extends BaseController
{
    private $view;
    private $mapper;

    public function __construct(Container $container)
    {
        $this->view = new SystemEventsView($container);
        parent::__construct($container);
        $this->mapper = $this->systemEvents; // already in container as a service
    }

    public function routePostIndexFilter(Request $request, Response $response, $args)
    {
        $this->setIndexFilter($request, $response, $args, $this->mapper::SELECT_COLUMNS, $this->view);
        return $this->view->indexView($response);
    }
}
