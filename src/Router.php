<?php
namespace Panadas\RouterModule;

use Panadas\EventManager\Event;
use Panadas\RouterModule\DataStructure\Routes;
use Panadas\Framework\Application;
use Panadas\Framework\AbstractApplicationAware;

class Router extends AbstractApplicationAware
{

    private $current;
    private $routes;

    public function __construct(Application $application, Routes $routes = null)
    {
        parent::__construct($application);

        if (null === $routes) {
            $routes = new Routes();
        }

        $this->setRoutes($routes);

        $application->before("handle", [$this, "beforeHandleEvent"]);
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    protected function setRoutes(Routes $routes)
    {
        $this->routes = $routes;

        return $this;
    }

    public function getCurrent($name = true)
    {
        if (!$name && (null !== $this->current)) {
            return $this->getRoutes()->get($this->current);
        }

        return $this->current;
    }

    public function hasCurrent()
    {
        return (null !== $this->getCurrent());
    }

    protected function setCurrent($current)
    {
        $this->current = $current;

        return $this;
    }

    protected function removeCurrent()
    {
        return $this->setCurrent(null);
    }

    public function beforeHandleEvent(Event $event)
    {
        if (null !== $event->getParams()->get("actionClass")) {
            return;
        }

        $logger = $event->getPublisher()->getServices()->get("logger");

        $eventParams = $event->getParams();
        $request = $eventParams->get("request");
        $method = $request->getMethod();
        $uri = $request->getUri(false, false);

        $name = null;
        $route = $this->findByUri($uri, $name);

        if (null === $route) {
            if (null !== $logger) {
                $logger->warn("Route not found for request: {$method} {$uri})");
            }
            return;
        }

        $this->setCurrent($name);

        if (null !== $logger) {
            $logger->info("Route \"{$name}\" matched for request: {$method} {$uri}");
        }

        $eventParams->set("actionClass", $route->getActionClass());
        $eventParams->get("actionArgs")->merge($route->getActionArgs());

        $request->getQueryParams()->merge($route->getPatternParamValues());
    }

    public function findByUri($uri, &$name = null)
    {
        $matched = false;
        $paramValues = [];

        foreach ($this->getRoutes() as $name => $route) {

            $regexp = str_replace("/", "\\/", $route->getPatternRegexp());

            if (preg_match("/^{$regexp}$/", $uri, $paramValues)) {
                $matched = true;
                break;
            }

        }

        if (!$matched) {
            $name = null;
            return;
        }

        $route = clone $route;

        foreach ($route->getPatternParamNames() as $paramName) {
            $route->setPatternParam($paramName, $paramValues[$paramName]);
        }

        return $route;
    }

    public function getUri($name, array $placeholders = [])
    {
        $routes = $this->getRoutes();

        if (!$routes->has($name)) {
            throw new \RuntimeException("Cannot generate URI for undefined route: {$name}");
        }

        return $routes->get($name)->getUri($placeholders);
    }
}
